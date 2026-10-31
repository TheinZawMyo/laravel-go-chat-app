package auth

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"testing"
	"time"
)

const testSecret = "test-gateway-secret-123"

func sign(t *testing.T, secret string, claims map[string]any) string {
	t.Helper()

	payload, err := json.Marshal(claims)
	if err != nil {
		t.Fatalf("marshal claims: %v", err)
	}

	encoded := base64.RawURLEncoding.EncodeToString(payload)
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte(encoded))

	return encoded + "." + base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}

func validClaims() map[string]any {
	now := time.Now()

	return map[string]any{
		"sub": "user-123",
		"aud": "gateway",
		"iat": now.Unix(),
		"exp": now.Add(5 * time.Minute).Unix(),
		"jti": "token-123",
	}
}

func TestVerifyAcceptsValidToken(t *testing.T) {
	claims, err := NewVerifier(testSecret).Verify(sign(t, testSecret, validClaims()))
	if err != nil {
		t.Fatalf("expected token to verify, got %v", err)
	}

	if claims.Subject != "user-123" || claims.TokenID != "token-123" {
		t.Fatalf("unexpected claims: %+v", claims)
	}
}

func TestVerifyRejectsTamperedSignature(t *testing.T) {
	token := sign(t, testSecret, validClaims())

	_, err := NewVerifier("different-secret-123").Verify(token)
	if !errors.Is(err, ErrInvalidToken) {
		t.Fatalf("expected ErrInvalidToken, got %v", err)
	}
}

func TestVerifyRejectsExpiredToken(t *testing.T) {
	claims := validClaims()
	claims["iat"] = time.Now().Add(-10 * time.Minute).Unix()
	claims["exp"] = time.Now().Add(-time.Minute).Unix()

	_, err := NewVerifier(testSecret).Verify(sign(t, testSecret, claims))
	if !errors.Is(err, ErrExpiredToken) {
		t.Fatalf("expected ErrExpiredToken, got %v", err)
	}
}

func TestVerifyRejectsWrongAudienceAndFutureIssuedAt(t *testing.T) {
	wrongAudience := validClaims()
	wrongAudience["aud"] = "someone-else"

	if _, err := NewVerifier(testSecret).Verify(sign(t, testSecret, wrongAudience)); !errors.Is(err, ErrInvalidToken) {
		t.Fatalf("expected ErrInvalidToken for audience, got %v", err)
	}

	future := validClaims()
	future["iat"] = time.Now().Add(5 * time.Minute).Unix()
	future["exp"] = time.Now().Add(10 * time.Minute).Unix()

	if _, err := NewVerifier(testSecret).Verify(sign(t, testSecret, future)); !errors.Is(err, ErrInvalidToken) {
		t.Fatalf("expected ErrInvalidToken for iat, got %v", err)
	}
}

func TestVerifyRejectsMalformedTokens(t *testing.T) {
	for _, token := range []string{"", "one-part", "a.b.c", "***.***"} {
		if _, err := NewVerifier(testSecret).Verify(token); !errors.Is(err, ErrInvalidToken) {
			t.Fatalf("expected ErrInvalidToken for %q, got %v", token, err)
		}
	}
}
