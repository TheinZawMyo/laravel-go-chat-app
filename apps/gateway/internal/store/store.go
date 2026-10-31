package store

import (
	"context"

	"github.com/jackc/pgx/v5/pgxpool"
)

type Store struct {
	db *pgxpool.Pool
}

func New(db *pgxpool.Pool) *Store {
	return &Store{db: db}
}

func (s *Store) UserIsActive(ctx context.Context, userID string) (bool, error) {
	var active bool

	err := s.db.QueryRow(ctx, `
		SELECT status = 'active' AND deleted_at IS NULL
		FROM users
		WHERE id = $1
	`, userID).Scan(&active)

	if err != nil {
		return false, err
	}

	return active, nil
}

func (s *Store) CanAccessConversation(ctx context.Context, userID, conversationID string) (bool, error) {
	var allowed bool

	err := s.db.QueryRow(ctx, `
		SELECT EXISTS (
			SELECT 1
			FROM conversation_members
			WHERE conversation_id = $1
				AND user_id = $2
				AND left_at IS NULL
		)
	`, conversationID, userID).Scan(&allowed)

	if err != nil {
		return false, err
	}

	return allowed, nil
}
