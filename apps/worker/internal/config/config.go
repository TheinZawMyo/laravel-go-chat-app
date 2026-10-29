package config

import (
	"fmt"
	"os"

	"github.com/joho/godotenv"
	"strconv"
	"time"
)

type Config struct {
	InstanceID  string
	Addr        string
	DatabaseURL string
	RedisURL    string
	NATSURL     string
	PollEvery   time.Duration
}

func Load() (Config, error) {
	_ = godotenv.Load()

	pollSeconds, err := strconv.Atoi(env("WORKER_POLL_SECONDS", "2"))
	if err != nil || pollSeconds < 1 {
		return Config{}, fmt.Errorf("WORKER_POLL_SECONDS must be a positive integer")
	}

	cfg := Config{
		InstanceID:  env("WORKER_INSTANCE_ID", "worker-local"),
		Addr:        env("WORKER_ADDR", ":8082"),
		DatabaseURL: env("DATABASE_URL", "postgres://postgres:postgres@127.0.0.1:5432/chat_app?sslmode=disable"),
		RedisURL:    env("REDIS_URL", "redis://127.0.0.1:6379/0"),
		NATSURL:     env("NATS_URL", "nats://127.0.0.1:4222"),
		PollEvery:   time.Duration(pollSeconds) * time.Second,
	}

	return cfg, nil
}

func env(key, fallback string) string {
	if value, ok := os.LookupEnv(key); ok {
		return value
	}

	return fallback
}
