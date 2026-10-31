package registry

import "testing"

type fakeClient struct {
	id     string
	userID string
}

func (c fakeClient) ID() string     { return c.id }
func (c fakeClient) UserID() string { return c.userID }

func TestRegistryTracksUsersAndSubscriptions(t *testing.T) {
	registry := New()
	first := fakeClient{id: "c1", userID: "u1"}
	second := fakeClient{id: "c2", userID: "u1"}
	other := fakeClient{id: "c3", userID: "u2"}

	registry.Add(first)
	registry.Add(second)
	registry.Add(other)
	registry.Subscribe(first, "conversation-1")
	registry.Subscribe(second, "conversation-1")
	registry.Subscribe(other, "conversation-2")

	if registry.Count() != 3 || registry.UserCount() != 2 || registry.SubscriptionTotal() != 3 {
		t.Fatalf("unexpected counts: %d %d %d", registry.Count(), registry.UserCount(), registry.SubscriptionTotal())
	}

	if got := len(registry.ByUser("u1")); got != 2 {
		t.Fatalf("expected 2 connections for user, got %d", got)
	}

	if got := registry.UserConnectionCount("u1"); got != 2 {
		t.Fatalf("expected 2 connections for user, got %d", got)
	}

	if got := registry.UserConnectionCount("u3"); got != 0 {
		t.Fatalf("expected 0 connections for an unknown user, got %d", got)
	}

	if got := len(registry.Subscribers("conversation-1")); got != 2 {
		t.Fatalf("expected 2 subscribers, got %d", got)
	}

	registry.Unsubscribe(first, "conversation-1")

	if registry.IsSubscribed(first, "conversation-1") {
		t.Fatal("expected first client to be unsubscribed")
	}

	if got := len(registry.Subscribers("conversation-1")); got != 1 {
		t.Fatalf("expected 1 subscriber, got %d", got)
	}
}

func TestRemoveCleansUpAllIndexes(t *testing.T) {
	registry := New()
	client := fakeClient{id: "c1", userID: "u1"}

	registry.Add(client)
	registry.Subscribe(client, "conversation-1")
	registry.Remove(client)

	if registry.Count() != 0 || registry.UserCount() != 0 || registry.SubscriptionTotal() != 0 {
		t.Fatalf("registry was not cleaned up")
	}

	if len(registry.Subscribers("conversation-1")) != 0 {
		t.Fatal("subscriber index was not cleaned up")
	}
}
