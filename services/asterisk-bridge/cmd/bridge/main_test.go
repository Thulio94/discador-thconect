package main

import "testing"

func TestSupervisionTargetUsesExtensionPrefixForPersistentMonitoring(t *testing.T) {
	target, err := supervisionTarget(superviseRequest{
		AgentChannel:   "PJSIP/1301-0000001a",
		AgentExtension: "1301",
		Persistent:     true,
	})

	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if target != "PJSIP/1301" {
		t.Fatalf("expected persistent extension prefix, got %q", target)
	}
}

func TestSupervisionTargetRejectsInvalidPersistentExtension(t *testing.T) {
	_, err := supervisionTarget(superviseRequest{
		AgentExtension: "invalid",
		Persistent:     true,
	})

	if err == nil {
		t.Fatal("expected invalid extension error")
	}
}

func TestSupervisionTargetKeepsSpecificChannelForSingleCall(t *testing.T) {
	target, err := supervisionTarget(superviseRequest{
		AgentChannel: "PJSIP/1301-0000001a",
	})

	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if target != "PJSIP/1301-0000001a" {
		t.Fatalf("expected exact channel, got %q", target)
	}
}
