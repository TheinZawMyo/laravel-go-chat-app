package protocol

import (
	"encoding/json"
	"regexp"
	"testing"
)

func TestNewIDIsUUIDShapedAndUnique(t *testing.T) {
	pattern := regexp.MustCompile(`^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$`)

	first := NewID()
	second := NewID()

	if !pattern.MatchString(first) {
		t.Fatalf("unexpected id format: %q", first)
	}

	if first == second {
		t.Fatal("expected unique ids")
	}
}

func TestEnvelopeIncludesVersionAndData(t *testing.T) {
	conversationID := "conversation-1"
	seq := int64(12)

	envelope, err := NewEnvelope(ServerMessageCreated, &conversationID, &seq, map[string]string{"message_id": "m1"})
	if err != nil {
		t.Fatalf("build envelope: %v", err)
	}

	if envelope.Version != Version || envelope.EventID == "" || envelope.Type != ServerMessageCreated {
		t.Fatalf("unexpected envelope: %+v", envelope)
	}

	if envelope.ConversationID == nil || *envelope.ConversationID != conversationID {
		t.Fatal("conversation id was not preserved")
	}

	if envelope.Seq == nil || *envelope.Seq != seq {
		t.Fatal("seq was not preserved")
	}

	raw, err := json.Marshal(envelope)
	if err != nil {
		t.Fatalf("marshal envelope: %v", err)
	}

	var decoded map[string]any
	if err := json.Unmarshal(raw, &decoded); err != nil {
		t.Fatalf("unmarshal envelope: %v", err)
	}

	if decoded["version"] != float64(Version) || decoded["event_id"] == "" || decoded["occurred_at"] == "" {
		t.Fatalf("unexpected encoded envelope: %s", raw)
	}
}

func TestErrorEnvelopeCarriesRequestID(t *testing.T) {
	envelope := ErrorEnvelope("request-1", "forbidden", "Not allowed")

	var data ErrorData
	if err := json.Unmarshal(envelope.Data, &data); err != nil {
		t.Fatalf("decode error data: %v", err)
	}

	if envelope.Type != ServerError || data.RequestID != "request-1" || data.Code != "forbidden" {
		t.Fatalf("unexpected error envelope: %+v", data)
	}
}
