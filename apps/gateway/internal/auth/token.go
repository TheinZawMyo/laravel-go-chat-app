package auth

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"strings"
	"time"
)

const audience = "gateway"

var (
	ErrInvalidToken = errors.New("invalid gateway token")
	ErrExpiredToken = errors.New("gateway token expired")
)

type Claims struct {
	Subject   string
	IssuedAt  time.Time
	ExpiresAt time.Time
	TokenID   string
}

type Verifier struct {
	secret []byte
	now    func() time.Time
}

func NewVerifier(secret string) *Verifier {
	return &Verifier{
		secret: []byte(secret),
		now:    time.Now,
	}
}

type payload struct {
	Subject   string `json:"sub"`
	Audience  string `json:"aud"`
	IssuedAt  int64  `json:"iat"`
	ExpiresAt int64  `json:"exp"`
	TokenID   string `json:"jti"`
}

func (v *Verifier) Verify(token string) (Claims, error) {
	parts := strings.Split(token, ".")

	if len(parts) != 2 || parts[0] == "" || parts[1] == "" {
		return Claims{}, ErrInvalidToken
	}

	signature, err := base64.RawURLEncoding.DecodeString(parts[1])
	if err != nil {
		return Claims{}, ErrInvalidToken
	}

	expected := hmac.New(sha256.New, v.secret)
	_, _ = expected.Write([]byte(parts[0]))

	if !hmac.Equal(signature, expected.Sum(nil)) {
		return Claims{}, ErrInvalidToken
	}

	encoded, err := base64.RawURLEncoding.DecodeString(parts[0])
	if err != nil {
		return Claims{}, ErrInvalidToken
	}

	var claims payload
	if err := json.Unmarshal(encoded, &claims); err != nil {
		return Claims{}, ErrInvalidToken
	}

	if claims.Subject == "" || claims.Audience != audience || claims.ExpiresAt == 0 || claims.IssuedAt == 0 {
		return Claims{}, ErrInvalidToken
	}

	now := v.now().Unix()

	if claims.ExpiresAt <= now {
		return Claims{}, ErrExpiredToken
	}

	if claims.IssuedAt > now+60 {
		return Claims{}, ErrInvalidToken
	}

	return Claims{
		Subject:   claims.Subject,
		IssuedAt:  time.Unix(claims.IssuedAt, 0).UTC(),
		ExpiresAt: time.Unix(claims.ExpiresAt, 0).UTC(),
		TokenID:   claims.TokenID,
	}, nil
}
