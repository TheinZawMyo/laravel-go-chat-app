package config

import (
	"os"
	"strings"
	"testing"
)

// clearEnv removes the variables that affect Config so a developer's local
// .env file cannot leak into the assertions, restoring them afterwards.
func clearEnv(t *testing.T, keys ...string) {
	t.Helper()

	for _, key := range keys {
		key := key

		value, ok := os.LookupEnv(key)
		if err := os.Unsetenv(key); err != nil {
			t.Fatalf("failed to unset %s: %v", key, err)
		}

		t.Cleanup(func() {
			if ok {
				_ = os.Setenv(key, value)

				return
			}

			_ = os.Unsetenv(key)
		})
	}
}

func TestLoadAppliesConnectionLimitDefaults(t *testing.T) {
	clearEnv(t,
		"GATEWAY_MAX_CONNECTIONS",
		"GATEWAY_MAX_CONNECTIONS_PER_USER",
		"GATEWAY_INBOUND_RATE_PER_SECOND",
		"GATEWAY_INBOUND_BURST",
	)

	cfg, err := Load()
	if err != nil {
		t.Fatalf("Load() returned an error: %v", err)
	}

	if cfg.MaxConnections != 10000 {
		t.Errorf("MaxConnections = %d, want 10000", cfg.MaxConnections)
	}

	if cfg.MaxConnectionsPerUser != 10 {
		t.Errorf("MaxConnectionsPerUser = %d, want 10", cfg.MaxConnectionsPerUser)
	}

	if cfg.InboundRatePerSecond != 30 {
		t.Errorf("InboundRatePerSecond = %v, want 30", cfg.InboundRatePerSecond)
	}

	if cfg.InboundBurst != 60 {
		t.Errorf("InboundBurst = %d, want 60", cfg.InboundBurst)
	}
}

func TestLoadParsesConnectionLimits(t *testing.T) {
	t.Setenv("GATEWAY_MAX_CONNECTIONS", "500")
	t.Setenv("GATEWAY_MAX_CONNECTIONS_PER_USER", "4")
	t.Setenv("GATEWAY_INBOUND_RATE_PER_SECOND", "12.5")
	t.Setenv("GATEWAY_INBOUND_BURST", "25")

	cfg, err := Load()
	if err != nil {
		t.Fatalf("Load() returned an error: %v", err)
	}

	if cfg.MaxConnections != 500 {
		t.Errorf("MaxConnections = %d, want 500", cfg.MaxConnections)
	}

	if cfg.MaxConnectionsPerUser != 4 {
		t.Errorf("MaxConnectionsPerUser = %d, want 4", cfg.MaxConnectionsPerUser)
	}

	if cfg.InboundRatePerSecond != 12.5 {
		t.Errorf("InboundRatePerSecond = %v, want 12.5", cfg.InboundRatePerSecond)
	}

	if cfg.InboundBurst != 25 {
		t.Errorf("InboundBurst = %d, want 25", cfg.InboundBurst)
	}
}

func TestLoadRejectsPerUserLimitAboveGlobalLimit(t *testing.T) {
	t.Setenv("GATEWAY_MAX_CONNECTIONS", "5")
	t.Setenv("GATEWAY_MAX_CONNECTIONS_PER_USER", "6")

	_, err := Load()
	if err == nil {
		t.Fatal("expected Load() to reject a per user limit above the global limit")
	}

	if !strings.Contains(err.Error(), "GATEWAY_MAX_CONNECTIONS_PER_USER") {
		t.Errorf("error = %q, want it to mention GATEWAY_MAX_CONNECTIONS_PER_USER", err)
	}
}

func TestLoadRejectsNonPositiveConnectionLimits(t *testing.T) {
	cases := map[string]string{
		"GATEWAY_MAX_CONNECTIONS_PER_USER": "0",
		"GATEWAY_INBOUND_RATE_PER_SECOND":  "0",
		"GATEWAY_INBOUND_BURST":            "0",
	}

	for key, value := range cases {
		t.Run(key, func(t *testing.T) {
			t.Setenv(key, value)

			if _, err := Load(); err == nil {
				t.Fatalf("expected Load() to reject %s=%s", key, value)
			}
		})
	}
}

func TestLoadRejectsUnparseableNumbers(t *testing.T) {
	t.Setenv("GATEWAY_INBOUND_RATE_PER_SECOND", "fast")
	t.Setenv("GATEWAY_INBOUND_BURST", "many")

	_, err := Load()
	if err == nil {
		t.Fatal("expected Load() to reject unparseable numbers")
	}
}
