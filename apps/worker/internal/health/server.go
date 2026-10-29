package health

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"sync"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/nats-io/nats.go"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"github.com/redis/go-redis/v9"
)

type Checker struct {
	DB    *pgxpool.Pool
	Redis *redis.Client
	NATS  *nats.Conn
}

type Server struct {
	addr    string
	logger  *slog.Logger
	checker Checker
	http    *http.Server
	stop    sync.Once
}

func New(addr string, logger *slog.Logger, checker Checker) *Server {
	s := &Server{addr: addr, logger: logger, checker: checker}

	mux := http.NewServeMux()
	mux.HandleFunc("GET /healthz", s.healthz)
	mux.HandleFunc("GET /readyz", s.readyz)
	mux.Handle("GET /metrics", promhttp.Handler())

	s.http = &http.Server{
		Addr:              addr,
		Handler:           mux,
		ReadHeaderTimeout: 10 * time.Second,
	}

	return s
}

func (s *Server) Run(ctx context.Context) error {
	errCh := make(chan error, 1)

	go func() {
		s.logger.Info("worker health server listening", "addr", s.addr)
		errCh <- s.http.ListenAndServe()
	}()

	select {
	case err := <-errCh:
		return err
	case <-ctx.Done():
	}

	shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	return s.http.Shutdown(shutdownCtx)
}

func (s *Server) healthz(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

func (s *Server) readyz(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
	defer cancel()

	if err := s.checker.DB.Ping(ctx); err != nil {
		s.logger.Warn("postgres not ready", "error", err)
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "postgres"})
		return
	}

	if err := s.checker.Redis.Ping(ctx).Err(); err != nil {
		s.logger.Warn("redis not ready", "error", err)
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "redis"})
		return
	}

	if !s.checker.NATS.IsConnected() {
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "degraded", "dependency": "nats"})
		return
	}

	writeJSON(w, http.StatusOK, map[string]string{"status": "ready"})
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)

	_ = json.NewEncoder(w).Encode(payload)
}
