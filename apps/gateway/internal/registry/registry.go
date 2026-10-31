package registry

import "sync"

type Client interface {
	ID() string
	UserID() string
}

type Registry struct {
	mu             sync.RWMutex
	clients        map[Client]struct{}
	byUser         map[string]map[Client]struct{}
	byConversation map[string]map[Client]struct{}
	subscriptions  map[Client]map[string]struct{}
}

func New() *Registry {
	return &Registry{
		clients:        make(map[Client]struct{}),
		byUser:         make(map[string]map[Client]struct{}),
		byConversation: make(map[string]map[Client]struct{}),
		subscriptions:  make(map[Client]map[string]struct{}),
	}
}

func (r *Registry) Add(client Client) {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.clients[client] = struct{}{}

	userID := client.UserID()
	if r.byUser[userID] == nil {
		r.byUser[userID] = make(map[Client]struct{})
	}
	r.byUser[userID][client] = struct{}{}
}

func (r *Registry) Remove(client Client) {
	r.mu.Lock()
	defer r.mu.Unlock()

	delete(r.clients, client)

	if userID := client.UserID(); userID != "" {
		if clients := r.byUser[userID]; clients != nil {
			delete(clients, client)
			if len(clients) == 0 {
				delete(r.byUser, userID)
			}
		}
	}

	for conversationID := range r.subscriptions[client] {
		if clients := r.byConversation[conversationID]; clients != nil {
			delete(clients, client)
			if len(clients) == 0 {
				delete(r.byConversation, conversationID)
			}
		}
	}

	delete(r.subscriptions, client)
}

func (r *Registry) Subscribe(client Client, conversationID string) {
	r.mu.Lock()
	defer r.mu.Unlock()

	if r.subscriptions[client] == nil {
		r.subscriptions[client] = make(map[string]struct{})
	}
	r.subscriptions[client][conversationID] = struct{}{}

	if r.byConversation[conversationID] == nil {
		r.byConversation[conversationID] = make(map[Client]struct{})
	}
	r.byConversation[conversationID][client] = struct{}{}
}

func (r *Registry) Unsubscribe(client Client, conversationID string) {
	r.mu.Lock()
	defer r.mu.Unlock()

	delete(r.subscriptions[client], conversationID)

	if clients := r.byConversation[conversationID]; clients != nil {
		delete(clients, client)
		if len(clients) == 0 {
			delete(r.byConversation, conversationID)
		}
	}
}

func (r *Registry) IsSubscribed(client Client, conversationID string) bool {
	r.mu.RLock()
	defer r.mu.RUnlock()

	_, ok := r.subscriptions[client][conversationID]

	return ok
}

func (r *Registry) SubscriptionCount(client Client) int {
	r.mu.RLock()
	defer r.mu.RUnlock()

	return len(r.subscriptions[client])
}

func (r *Registry) ByUser(userID string) []Client {
	r.mu.RLock()
	defer r.mu.RUnlock()

	clients := make([]Client, 0, len(r.byUser[userID]))
	for client := range r.byUser[userID] {
		clients = append(clients, client)
	}

	return clients
}

func (r *Registry) Subscribers(conversationID string) []Client {
	r.mu.RLock()
	defer r.mu.RUnlock()

	clients := make([]Client, 0, len(r.byConversation[conversationID]))
	for client := range r.byConversation[conversationID] {
		clients = append(clients, client)
	}

	return clients
}

func (r *Registry) All() []Client {
	r.mu.RLock()
	defer r.mu.RUnlock()

	clients := make([]Client, 0, len(r.clients))
	for client := range r.clients {
		clients = append(clients, client)
	}

	return clients
}

func (r *Registry) Count() int {
	r.mu.RLock()
	defer r.mu.RUnlock()

	return len(r.clients)
}

func (r *Registry) UserCount() int {
	r.mu.RLock()
	defer r.mu.RUnlock()

	return len(r.byUser)
}

func (r *Registry) UserConnectionCount(userID string) int {
	r.mu.RLock()
	defer r.mu.RUnlock()

	return len(r.byUser[userID])
}

func (r *Registry) SubscriptionTotal() int {
	r.mu.RLock()
	defer r.mu.RUnlock()

	total := 0
	for _, conversations := range r.subscriptions {
		total += len(conversations)
	}

	return total
}
