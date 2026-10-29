package server

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"sync"
	"time"

	"github.com/coder/websocket"
	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"github.com/redis/go-redis/v9"
	"golang.org/x/time/rate"

	"chatapp/gateway/internal/config"
)

type Server struct {
	cfg     config.Config
	logger  *slog.Logger
	db      *pgxpool.Pool
	redis   *redis.Client
	limiter *rate.Limiter
	http    *http.Server
	conns   sync.WaitGroup
	ctx     context.Context
	cancel  context.CancelFunc
}

func New(cfg config.Config, logger *slog.Logger) (*Server, error) {
	db, err := pgxpool.New(context.Background(), cfg.DatabaseURL)
	if err != nil {
		return nil, err
	}

	redisOpts, err := redis.ParseURL(cfg.RedisURL)
	if err != nil {
		db.Close()
		return nil, err
	}

	ctx, cancel := context.WithCancel(context.Background())

	s := &Server{
		cfg:     cfg,
		logger:  logger,
		db:      db,
		redis:   redis.NewClient(redisOpts),
		limiter: rate.NewLimiter(rate.Every(100*time.Millisecond), 20),
		ctx:     ctx,
		cancel:  cancel,
	}

	mux := http.NewServeMux()
	mux.HandleFunc("GET /healthz", s.healthz)
	mux.HandleFunc("GET /readyz", s.readyz)
	mux.Handle("GET /metrics", promhttp.Handler())
	mux.HandleFunc("GET /ws", s.websocket)

	s.http = &http.Server{
		Addr:              cfg.Addr,
		Handler:           mux,
		ReadHeaderTimeout: 10 * time.Second,
	}

	return s, nil
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

	shutdownCtx, cancel := context.WithTimeout(context.Background(), 20*time.Second)
	defer cancel()

	s.logger.Info("gateway shutting down")
	s.cancel()

	if err := s.http.Shutdown(shutdownCtx); err != nil {
		return err
	}

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

	return nil
}

func (s *Server) Close() {
	s.cancel()
	s.db.Close()
	_ = s.redis.Close()
}

func (s *Server) healthz(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

func (s *Server) readyz(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
	defer cancel()

	if err := s.db.Ping(ctx); err != nil {
		s.logger.Warn("postgres not ready", "error", err)
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "postgres"})
		return
	}

	if err := s.redis.Ping(ctx).Err(); err != nil {
		s.logger.Warn("redis not ready", "error", err)
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "redis"})
		return
	}

	writeJSON(w, http.StatusOK, map[string]string{"status": "ready"})
}

func (s *Server) websocket(w http.ResponseWriter, r *http.Request) {
	if !s.limiter.Allow() {
		http.Error(w, http.StatusText(http.StatusTooManyRequests), http.StatusTooManyRequests)
		return
	}

	conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
		OriginPatterns: s.cfg.AllowedOrigins,
	})
	if err != nil {
		s.logger.Warn("websocket upgrade rejected", "error", err)
		return
	}

	s.conns.Add(1)
	defer s.conns.Done()
	defer conn.CloseNow()

	conn.SetReadLimit(64 * 1024)

	for {
		_, _, err := conn.Read(s.ctx)
		if err != nil {
			return
		}
	}
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)

	_ = json.NewEncoder(w).Encode(payload)
}
