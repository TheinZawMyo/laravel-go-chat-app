package config

import (
	"fmt"
	"os"

	"github.com/joho/godotenv"
	"strings"
)

type Config struct {
	InstanceID     string
	Addr           string
	DatabaseURL    string
	RedisURL       string
	NATSURL        string
	AllowedOrigins []string
}

func Load() (Config, error) {
	_ = godotenv.Load()

	cfg := Config{
		InstanceID:     env("GATEWAY_INSTANCE_ID", "gateway-local"),
		Addr:           env("GATEWAY_ADDR", ":8081"),
		DatabaseURL:    env("DATABASE_URL", "postgres://postgres:postgres@127.0.0.1:5432/chat_app?sslmode=disable"),
		RedisURL:       env("REDIS_URL", "redis://127.0.0.1:6379/0"),
		NATSURL:        env("NATS_URL", "nats://127.0.0.1:4222"),
		AllowedOrigins: split(env("ALLOWED_ORIGINS", "http://localhost:8000")),
	}

	if cfg.Addr == "" {
		return Config{}, fmt.Errorf("GATEWAY_ADDR must not be empty")
	}

	return cfg, nil
}

func env(key, fallback string) string {
	if value, ok := os.LookupEnv(key); ok {
		return value
	}

	return fallback
}

func split(value string) []string {
	parts := strings.Split(value, ",")
	values := make([]string, 0, len(parts))

	for _, part := range parts {
		if trimmed := strings.TrimSpace(part); trimmed != "" {
			values = append(values, trimmed)
		}
	}

	return values
}
