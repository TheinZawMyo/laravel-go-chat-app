package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/nats-io/nats.go"
	"github.com/redis/go-redis/v9"

	"chatapp/worker/internal/config"
	"chatapp/worker/internal/health"
)

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
		Level: slog.LevelInfo,
	}))
	slog.SetDefault(logger)

	cfg, err := config.Load()
	if err != nil {
		logger.Error("invalid configuration", "error", err)
		os.Exit(1)
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	db, err := pgxpool.New(ctx, cfg.DatabaseURL)
	if err != nil {
		logger.Error("failed to connect to postgres", "error", err)
		os.Exit(1)
	}
	defer db.Close()

	redisOpts, err := redis.ParseURL(cfg.RedisURL)
	if err != nil {
		logger.Error("invalid redis url", "error", err)
		os.Exit(1)
	}
	cache := redis.NewClient(redisOpts)
	defer func() { _ = cache.Close() }()

	nc, err := nats.Connect(cfg.NATSURL, nats.Name(cfg.InstanceID), nats.MaxReconnects(-1))
	if err != nil {
		logger.Error("failed to connect to nats", "error", err)
		os.Exit(1)
	}
	defer nc.Drain()

	healthServer := health.New(cfg.Addr, logger, health.Checker{
		DB:    db,
		Redis: cache,
		NATS:  nc,
	})

	logger.Info("worker started", "instance", cfg.InstanceID, "poll", cfg.PollEvery.String())

	if err := healthServer.Run(ctx); err != nil && !errors.Is(err, http.ErrServerClosed) {
		logger.Error("worker stopped", "error", err)
		os.Exit(1)
	}

	logger.Info("worker stopped")
}
