package server

import (
	"context"
	"encoding/json"
	"fmt"
	"log/slog"
	"net"
	"net/http"
	"strings"
	"sync"
	"time"

	"github.com/coder/websocket"
	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/nats-io/nats.go"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"github.com/redis/go-redis/v9"
	"golang.org/x/time/rate"

	"chatapp/gateway/internal/auth"
	"chatapp/gateway/internal/config"
	"chatapp/gateway/internal/protocol"
	"chatapp/gateway/internal/registry"
	"chatapp/gateway/internal/store"
)

type Authorizer interface {
	UserIsActive(ctx context.Context, userID string) (bool, error)
	CanAccessConversation(ctx context.Context, userID, conversationID string) (bool, error)
}

type dependencies struct {
	DB         *pgxpool.Pool
	Redis      *redis.Client
	NATS       *nats.Conn
	Authorizer Authorizer
}

type limiterEntry struct {
	limiter  *rate.Limiter
	lastSeen time.Time
}

type Server struct {
	cfg     config.Config
	logger  *slog.Logger
	db      *pgxpool.Pool
	redis   *redis.Client
	nats    *nats.Conn
	store   Authorizer
	verify  *auth.Verifier
	clients *registry.Registry
	metrics *metrics
	http    *http.Server
	conns   sync.WaitGroup
	ctx     context.Context
	cancel  context.CancelFunc

	limitersMu sync.Mutex
	limiters   map[string]*limiterEntry

	brokerMu   sync.Mutex
	brokerSubs []*nats.Subscription
}

func New(cfg config.Config, logger *slog.Logger) (*Server, error) {
	db, err := pgxpool.New(context.Background(), cfg.DatabaseURL)
	if err != nil {
		return nil, fmt.Errorf("connect to postgres: %w", err)
	}

	redisOpts, err := redis.ParseURL(cfg.RedisURL)
	if err != nil {
		db.Close()

		return nil, fmt.Errorf("parse redis url: %w", err)
	}

	nc, err := nats.Connect(
		cfg.NATSURL,
		nats.Name(cfg.InstanceID),
		nats.MaxReconnects(-1),
		nats.ReconnectWait(time.Second),
	)
	if err != nil {
		db.Close()

		return nil, fmt.Errorf("connect to nats: %w", err)
	}

	s := newServer(cfg, logger, dependencies{
		DB:         db,
		Redis:      redis.NewClient(redisOpts),
		NATS:       nc,
		Authorizer: store.New(db),
	})

	if err := s.subscribeBroker(); err != nil {
		s.Close()

		return nil, err
	}

	return s, nil
}

func newServer(cfg config.Config, logger *slog.Logger, deps dependencies) *Server {
	ctx, cancel := context.WithCancel(context.Background())

	s := &Server{
		cfg:      cfg,
		logger:   logger,
		db:       deps.DB,
		redis:    deps.Redis,
		nats:     deps.NATS,
		store:    deps.Authorizer,
		verify:   auth.NewVerifier(cfg.TokenSecret),
		clients:  registry.New(),
		ctx:      ctx,
		cancel:   cancel,
		limiters: make(map[string]*limiterEntry),
	}

	s.metrics = newMetrics(s.clients)

	mux := http.NewServeMux()
	mux.HandleFunc("GET /healthz", s.healthz)
	mux.HandleFunc("GET /readyz", s.readyz)
	mux.Handle("GET /metrics", promhttp.HandlerFor(s.metrics.registry, promhttp.HandlerOpts{}))
	mux.HandleFunc("GET /ws", s.websocket)

	s.http = &http.Server{
		Addr:              cfg.Addr,
		Handler:           mux,
		ReadHeaderTimeout: 10 * time.Second,
	}

	return s
}

func (s *Server) Run(ctx context.Context) error {
	errCh := make(chan error, 1)

	go func() {
		s.logger.Info("gateway listening", "addr", s.cfg.Addr, "instance", s.cfg.InstanceID)
		errCh <- s.http.ListenAndServe()
	}()

	select {
	case err := <-errCh:
		return err
	case <-ctx.Done():
	}

	shutdownCtx, cancel := context.WithTimeout(context.Background(), s.cfg.ShutdownTimeout)
	defer cancel()

	s.logger.Info("gateway shutting down", "connections", s.clients.Count())

	s.drainConnections()

	if err := s.http.Shutdown(shutdownCtx); err != nil {
		s.logger.Warn("gateway http shutdown failed", "error", err)
	}

	s.cancel()

	done := make(chan struct{})
	go func() {
		s.conns.Wait()
		close(done)
	}()

	select {
	case <-done:
	case <-shutdownCtx.Done():
		s.logger.Warn("timed out draining connections")
	}

	s.unsubscribeBroker()
	s.logger.Info("gateway stopped")

	return nil
}

// drainConnections tells every live client that the gateway is going away so
// they reconnect instead of treating the shutdown as a transport failure.
func (s *Server) drainConnections() {
	for _, client := range s.clients.All() {
		if connection, ok := client.(*Connection); ok {
			connection.drain()
		}
	}
}

func (s *Server) Close() {
	s.cancel()

	for _, client := range s.clients.All() {
		if connection, ok := client.(*Connection); ok {
			connection.forceClose()
		}
	}

	s.unsubscribeBroker()

	if s.nats != nil {
		s.nats.Close()
	}

	if s.redis != nil {
		_ = s.redis.Close()
	}

	if s.db != nil {
		s.db.Close()
	}
}

func (s *Server) healthz(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

func (s *Server) readyz(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
	defer cancel()

	if s.db != nil {
		if err := s.db.Ping(ctx); err != nil {
			s.logger.Warn("postgres not ready", "error", err)
			writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "postgres"})
			return
		}
	}

	if s.redis != nil {
		if err := s.redis.Ping(ctx).Err(); err != nil {
			s.logger.Warn("redis not ready", "error", err)
			writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "redis"})
			return
		}
	}

	if s.nats != nil && !s.nats.IsConnected() {
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "nats"})
		return
	}

	writeJSON(w, http.StatusOK, map[string]string{"status": "ready"})
}

func (s *Server) websocket(w http.ResponseWriter, r *http.Request) {
	remote := remoteIP(r)

	if !s.allow(remote) {
		s.metrics.upgradeRejections.WithLabelValues("rate_limited").Inc()
		http.Error(w, http.StatusText(http.StatusTooManyRequests), http.StatusTooManyRequests)
		return
	}

	if s.clients.Count() >= s.cfg.MaxConnections {
		s.metrics.upgradeRejections.WithLabelValues("connection_limit").Inc()
		http.Error(w, http.StatusText(http.StatusServiceUnavailable), http.StatusServiceUnavailable)
		return
	}

	conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
		OriginPatterns:  s.cfg.AllowedOrigins,
		CompressionMode: websocket.CompressionDisabled,
	})
	if err != nil {
		s.metrics.upgradeRejections.WithLabelValues("handshake").Inc()
		s.logger.Warn("websocket upgrade rejected", "remote", remote, "error", err)
		return
	}

	connection := newConnection(s, conn, remote)

	s.conns.Add(1)
	go func() {
		defer s.conns.Done()
		connection.run()
	}()
}

func (s *Server) allow(remote string) bool {
	now := time.Now()

	s.limitersMu.Lock()
	defer s.limitersMu.Unlock()

	entry, ok := s.limiters[remote]
	if !ok || now.Sub(entry.lastSeen) > 10*time.Minute {
		entry = &limiterEntry{limiter: rate.NewLimiter(rate.Every(250*time.Millisecond), 10)}
		s.limiters[remote] = entry
	}
	entry.lastSeen = now

	if len(s.limiters) > 10000 {
		for key, candidate := range s.limiters {
			if now.Sub(candidate.lastSeen) > 10*time.Minute {
				delete(s.limiters, key)
			}
		}
	}

	return entry.limiter.Allow()
}

func (s *Server) subscribeBroker() error {
	if s.nats == nil {
		return nil
	}

	subjects := []string{s.cfg.EventSubjectPrefix + ">", s.cfg.TypingSubjectPrefix + ">"}

	s.brokerMu.Lock()
	defer s.brokerMu.Unlock()

	for _, subject := range subjects {
		sub, err := s.nats.Subscribe(subject, func(message *nats.Msg) {
			s.handleBrokerMessage(message.Subject, message.Data)
		})
		if err != nil {
			return fmt.Errorf("subscribe to %s: %w", subject, err)
		}

		s.brokerSubs = append(s.brokerSubs, sub)
	}

	return nil
}

func (s *Server) unsubscribeBroker() {
	s.brokerMu.Lock()
	defer s.brokerMu.Unlock()

	for _, sub := range s.brokerSubs {
		_ = sub.Unsubscribe()
	}
	s.brokerSubs = nil
}

func (s *Server) handleBrokerMessage(subject string, raw []byte) {
	if len(raw) == 0 || len(raw) > 1024*1024 {
		s.metrics.eventsDropped.WithLabelValues("invalid_size").Inc()
		return
	}

	var envelope protocol.Envelope
	if err := json.Unmarshal(raw, &envelope); err != nil || envelope.Version != protocol.Version || envelope.EventID == "" || envelope.Type == "" {
		s.metrics.eventsDropped.WithLabelValues("invalid_envelope").Inc()
		s.logger.Warn("invalid broker event dropped", "subject", subject, "error", err)
		return
	}

	var clients []registry.Client

	switch {
	case strings.HasPrefix(subject, s.cfg.EventSubjectPrefix):
		target := strings.TrimPrefix(subject, s.cfg.EventSubjectPrefix)
		switch {
		case strings.HasPrefix(target, "user."):
			userID := strings.TrimPrefix(target, "user.")
			if strings.TrimSpace(userID) == "" {
				s.metrics.eventsDropped.WithLabelValues("invalid_subject").Inc()

				return
			}

			clients = s.clients.ByUser(userID)
		default:
			conversationID := strings.TrimPrefix(target, "conversation.")
			if !s.subjectMatchesEnvelope(subject, conversationID, envelope) {
				return
			}

			clients = s.clients.Subscribers(conversationID)
		}
	case strings.HasPrefix(subject, s.cfg.TypingSubjectPrefix):
		conversationID := strings.TrimPrefix(subject, s.cfg.TypingSubjectPrefix)
		if !s.subjectMatchesEnvelope(subject, conversationID, envelope) {
			return
		}

		clients = s.clients.Subscribers(conversationID)
	default:
		s.metrics.eventsDropped.WithLabelValues("unknown_subject").Inc()
		return
	}

	s.deliver(clients, raw, "broker")
}

// subjectMatchesEnvelope rejects events whose envelope targets a conversation
// other than the one encoded in the NATS subject. Without this check a single
// mis-published event would leak messages into every other conversation.
func (s *Server) subjectMatchesEnvelope(subject, conversationID string, envelope protocol.Envelope) bool {
	if !validID(conversationID) || envelope.ConversationID == nil || *envelope.ConversationID != conversationID {
		s.metrics.eventsDropped.WithLabelValues("conversation_mismatch").Inc()
		s.logger.Warn("broker event dropped because the subject does not match the envelope",
			"subject", subject,
			"conversation_id", conversationID,
		)

		return false
	}

	if envelope.Type == protocol.ServerTypingUpdated {
		var data protocol.TypingData
		if err := json.Unmarshal(envelope.Data, &data); err != nil || data.ConversationID != conversationID {
			s.metrics.eventsDropped.WithLabelValues("conversation_mismatch").Inc()

			return false
		}
	}

	return true
}

func (s *Server) deliver(clients []registry.Client, raw []byte, source string) {
	for _, client := range clients {
		connection, ok := client.(*Connection)
		if !ok || !connection.enqueue(raw) {
			continue
		}

		s.metrics.eventsDelivered.WithLabelValues(source).Inc()
	}
}

func (s *Server) publishEphemeral(envelope protocol.Envelope, conversationID string) {
	raw, err := json.Marshal(envelope)
	if err != nil {
		s.logger.Error("failed to encode ephemeral event", "error", err)
		return
	}

	if s.nats != nil {
		if err := s.nats.Publish(s.cfg.TypingSubjectPrefix+conversationID, raw); err != nil {
			s.metrics.eventsDropped.WithLabelValues("publish_failed").Inc()
			s.logger.Warn("failed to publish ephemeral event", "error", err)
		}

		return
	}

	s.deliver(s.clients.Subscribers(conversationID), raw, "local")
}

func remoteIP(r *http.Request) string {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}

	return host
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)

	_ = json.NewEncoder(w).Encode(payload)
}
