package main

import (
	"bufio"
	"bytes"
	"strings"
	"testing"
)

func TestSupervisionTargetUsesExtensionPrefixForPersistentMonitoring(t *testing.T) {
	target, err := supervisionTarget(superviseRequest{
		CompanyID:      1,
		AgentChannel:   "PJSIP/1301-0000001a",
		AgentExtension: "1301",
		Persistent:     true,
	})

	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if target != "PJSIP/t1-e1301" {
		t.Fatalf("expected persistent extension prefix, got %q", target)
	}
}

func TestRouteStateEncryptionRoundTrip(t *testing.T) {
	key := bytes.Repeat([]byte{0x42}, 32)
	plaintext := []byte(`[{"sip_password":"must-not-be-plaintext"}]`)

	ciphertext, err := encryptState(key, plaintext)
	if err != nil {
		t.Fatalf("unexpected encryption error: %v", err)
	}
	if bytes.Contains(ciphertext, []byte("must-not-be-plaintext")) {
		t.Fatal("encrypted state leaked the SIP password")
	}
	decrypted, err := decryptState(key, ciphertext)
	if err != nil {
		t.Fatalf("unexpected decryption error: %v", err)
	}
	if !bytes.Equal(decrypted, plaintext) {
		t.Fatalf("state round trip mismatch: %q", decrypted)
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

func TestValidateRouteRejectsPJSIPInjection(t *testing.T) {
	config := validRouteConfig()
	config.Codecs = "alaw\n[attacker]\ntype=endpoint"

	if err := validateRoute(config); err == nil {
		t.Fatal("expected codec configuration injection to be rejected")
	}
}

func TestRenderAgentPJSIPConfigCreatesRestrictedWebRTCEndpoint(t *testing.T) {
	content, err := renderAgentPJSIPConfig([]agentConfig{{
		UserID: 9, CompanyID: 3, Extension: "1301",
		Password: strings.Repeat("A", 48), Codecs: "opus,ulaw,alaw",
	}})
	if err != nil {
		t.Fatalf("unexpected render error: %v", err)
	}
	for _, expected := range []string{
		"[t3-e1301]", "transport=transport-ws", "context=thconect-agent-denied",
		"webrtc=yes", "max_contacts=1", "remove_existing=yes", "username=t3-e1301",
	} {
		if !strings.Contains(content, expected) {
			t.Fatalf("expected generated agent config to contain %q", expected)
		}
	}
}

func TestValidateAgentRejectsConfigurationInjection(t *testing.T) {
	err := validateAgent(agentConfig{
		UserID: 9, CompanyID: 3, Extension: "1301",
		Password: strings.Repeat("A", 48), Codecs: "opus\n[attacker]",
	})
	if err == nil {
		t.Fatal("expected WebRTC endpoint injection to be rejected")
	}
}

func TestRenderPJSIPConfigSupportsSecureDigestTLSProfile(t *testing.T) {
	config := validRouteConfig()
	config.Transport = "tls"
	config.AuthenticationMode = "digest"
	config.SIPPort = 5061
	config.SIPUsername = "conta-sip"
	config.SIPPassword = "segredo-de-teste"
	config.FromUser = "551130000000"
	config.ContactUser = "551130000000"
	config.DTMFMode = "rfc4733"
	config.CallerIDHeader = "pai"

	content, err := renderPJSIPConfig([]routeConfig{config})
	if err != nil {
		t.Fatalf("unexpected render error: %v", err)
	}

	for _, expected := range []string{
		"transport=transport-tls",
		"contact=sip:sip.example.test:5061;transport=tls",
		"outbound_auth=thconect-route-7-auth",
		"from_user=551130000000",
		"send_pai=yes",
		"dtmf_mode=rfc4733",
	} {
		if !strings.Contains(content, expected) {
			t.Fatalf("expected generated config to contain %q", expected)
		}
	}
	if strings.Contains(content, "type=registration") {
		t.Fatal("digest-only route must not create an outbound registration")
	}
}

func TestReadAMIGreetingAcceptsAsteriskBanner(t *testing.T) {
	reader := bufio.NewReader(strings.NewReader("Asterisk Call Manager/10.0.0\r\n"))

	if err := readAMIGreeting(reader); err != nil {
		t.Fatalf("expected valid AMI greeting, got %v", err)
	}
}

func TestReadAMIGreetingRejectsUnexpectedProtocol(t *testing.T) {
	reader := bufio.NewReader(strings.NewReader("HTTP/1.1 200 OK\r\n"))

	if err := readAMIGreeting(reader); err == nil {
		t.Fatal("expected invalid AMI greeting to be rejected")
	}
}

func TestSuccessfulSIPResponseAcceptsOnlyTwoHundreds(t *testing.T) {
	for _, test := range []struct {
		line string
		want bool
	}{
		{line: "SIP/2.0 200 OK", want: true},
		{line: "SIP/2.0 202 Accepted", want: true},
		{line: "SIP/2.0 100 Trying", want: false},
		{line: "SIP/2.0 401 Unauthorized", want: false},
		{line: "SIP/2.0 403 Forbidden", want: false},
		{line: "SIP/2.0 503 Service Unavailable", want: false},
		{line: "invalid response", want: false},
	} {
		if got := successfulSIPResponse(test.line); got != test.want {
			t.Errorf("successfulSIPResponse(%q) = %t, want %t", test.line, got, test.want)
		}
	}
}

func validRouteConfig() routeConfig {
	return routeConfig{
		CompanyID: 1, RouteID: 7,
		Host: "asterisk", Port: 5038, Username: "manager", Secret: "manager-secret",
		Context: "thconect-outbound", Extension: "s",
		SIPHost: "sip.example.test", SIPPort: 5060, FromDomain: "sip.example.test",
		AuthenticationMode: "ip", Transport: "udp", Codecs: "alaw,ulaw",
		DTMFMode: "rfc4733", CallerIDHeader: "none", QualifyFrequency: 15,
		RegistrationExpiration: 3600,
	}
}
