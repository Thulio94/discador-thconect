package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"errors"
	"net"
	"net/http/httptest"
	"strconv"
	"strings"
	"testing"
	"time"
)

func TestCancelCallRejectsAnotherCompany(t *testing.T) {
	m := newRouteManager(&bridge{}, routeConfig{CompanyID: 2})
	m.companies[7] = 2
	if err := m.cancelCall(7, 3); err == nil {
		t.Fatal("expected cross-company cancellation to be rejected")
	}
	if m.cancelled[7] {
		t.Fatal("cross-company cancellation changed the call")
	}
}

func TestCancelCallWaitsForAsteriskChannel(t *testing.T) {
	m := newRouteManager(&bridge{}, routeConfig{CompanyID: 2})
	m.companies[7] = 2
	if err := m.cancelCall(7, 2); err != nil {
		t.Fatalf("unexpected cancellation error: %v", err)
	}
	if !m.cancelled[7] || m.cancelSent[7] {
		t.Fatal("cancellation should wait for the real route channel")
	}
}

func TestWebhooksForOneCallKeepAMIEventOrder(t *testing.T) {
	m := newRouteManager(&bridge{}, routeConfig{CompanyID: 2})
	m.actions["call-action"] = actionRef{CallID: 7, CompanyID: 2}
	m.companies[7] = 2
	m.webhookQueues = make([]chan map[string]any, 8)
	for i := range m.webhookQueues {
		m.webhookQueues[i] = make(chan map[string]any, 2)
	}
	m.processEvent(map[string]string{
		"Event": "OriginateResponse", "ActionID": "call-action", "Response": "Success", "Uniqueid": "call-uniqueid",
	})
	m.processEvent(map[string]string{
		"Event": "DialEnd", "Uniqueid": "call-uniqueid", "DialStatus": "ANSWER",
	})
	queue := m.webhookQueues[7%len(m.webhookQueues)]
	if first, second := <-queue, <-queue; first["event"] != "originate_succeeded" || second["event"] != "answered" {
		t.Fatalf("call events reached the webhook queue out of order: %v, %v", first, second)
	}
}

func TestAgentDeliveryFailureIsForwardedInsteadOfBeingDropped(t *testing.T) {
	m := newRouteManager(&bridge{}, routeConfig{CompanyID: 2})
	m.companies[7] = 2
	m.ids["linked-call"] = 7
	m.webhookQueues = make([]chan map[string]any, 8)
	for i := range m.webhookQueues {
		m.webhookQueues[i] = make(chan map[string]any, 1)
	}

	m.processEvent(map[string]string{
		"Event": "DialEnd", "Linkedid": "linked-call", "DialStatus": "CHANUNAVAIL",
		"DestChannel": "PJSIP/t2-e1002-0000002a", "TechCause": "3",
	})

	select {
	case event := <-m.webhookQueues[7%len(m.webhookQueues)]:
		if event["event"] != "agent_delivery_failed" || event["cause"] != "CHANUNAVAIL (3)" {
			t.Fatalf("unexpected agent delivery event: %#v", event)
		}
	default:
		t.Fatal("expected failed agent delivery to reach the application")
	}
}

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

func TestRouteCheckUsesReadOnlyAMICommandInsteadOfReloadingPJSIP(t *testing.T) {
	listener, err := net.Listen("tcp4", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	_, portText, err := net.SplitHostPort(listener.Addr().String())
	if err != nil {
		t.Fatal(err)
	}
	port, err := strconv.Atoi(portText)
	if err != nil {
		t.Fatal(err)
	}
	commands := make(chan map[string]string, 1)
	serverErrors := make(chan error, 1)
	go func() {
		conn, err := listener.Accept()
		if err != nil {
			serverErrors <- err
			return
		}
		defer conn.Close()
		_, _ = conn.Write([]byte("Asterisk Call Manager/10.0.0\r\n"))
		reader := bufio.NewReader(conn)
		login, err := readAMIMessage(reader)
		if err != nil {
			serverErrors <- err
			return
		}
		if login["Action"] != "Login" {
			serverErrors <- errors.New("expected AMI login")
			return
		}
		_, _ = conn.Write([]byte("Response: Success\r\nMessage: Authentication accepted\r\n\r\n"))
		command, err := readAMIMessage(reader)
		if err != nil {
			serverErrors <- err
			return
		}
		commands <- command
		_, _ = conn.Write([]byte("Response: Follows\r\nPrivilege: Command\r\n\r\nAsterisk version\r\n--END COMMAND--\r\n\r\n"))
	}()

	if err := testAMI(routeConfig{Host: "127.0.0.1", Port: port, Username: "test", Secret: "secret"}); err != nil {
		t.Fatalf("route AMI check failed: %v", err)
	}
	select {
	case command := <-commands:
		if command["Action"] != "Command" || command["Command"] != "core show version" {
			t.Fatalf("route check must not reload PJSIP; got AMI action %#v", command)
		}
	case err := <-serverErrors:
		t.Fatalf("mock AMI server failed: %v", err)
	}
}

func TestRouteEventListenerDoesNotReloadPJSIPWhenReconnecting(t *testing.T) {
	listener, err := net.Listen("tcp4", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	_, portText, err := net.SplitHostPort(listener.Addr().String())
	if err != nil {
		t.Fatal(err)
	}
	port, err := strconv.Atoi(portText)
	if err != nil {
		t.Fatal(err)
	}
	nextAction := make(chan map[string]string, 1)
	serverErrors := make(chan error, 1)
	go func() {
		conn, err := listener.Accept()
		if err != nil {
			serverErrors <- err
			return
		}
		defer conn.Close()
		_, _ = conn.Write([]byte("Asterisk Call Manager/10.0.0\r\n"))
		reader := bufio.NewReader(conn)
		login, err := readAMIMessage(reader)
		if err != nil {
			serverErrors <- err
			return
		}
		if login["Action"] != "Login" {
			serverErrors <- errors.New("expected AMI login")
			return
		}
		_, _ = conn.Write([]byte("Response: Success\r\nMessage: Authentication accepted\r\n\r\n"))
		_ = conn.SetReadDeadline(time.Now().Add(300 * time.Millisecond))
		action, err := readAMIMessage(reader)
		if err == nil {
			nextAction <- action
			return
		}
		if timeout, ok := err.(net.Error); !ok || !timeout.Timeout() {
			serverErrors <- err
			return
		}
		nextAction <- nil
	}()

	manager := newRouteManager(&bridge{}, routeConfig{Host: "127.0.0.1", Port: port, Username: "test", Secret: "secret"})
	err = manager.connectOnce()
	if err == nil {
		t.Fatal("expected the mock AMI connection to close after checking for unexpected commands")
	}
	select {
	case action := <-nextAction:
		if action != nil {
			t.Fatalf("event listener unexpectedly sent an AMI action after login: %#v", action)
		}
	case err := <-serverErrors:
		t.Fatalf("mock AMI server failed: %v", err)
	case <-time.After(2 * time.Second):
		t.Fatal("timed out waiting for the mock AMI server")
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

func TestAMICorrelationUsesLinkedIDAfterOriginateResponse(t *testing.T) {
	manager := newRouteManager(&bridge{}, routeConfig{CompanyID: 3})
	manager.actions["call-action"] = actionRef{CallID: 42, CompanyID: 3}
	manager.companies[42] = 3

	manager.processEvent(map[string]string{
		"Event": "OriginateResponse", "ActionID": "call-action", "Response": "Success",
		"Uniqueid": "1720000000.10", "Linkedid": "1720000000.10",
	})

	if got := manager.callIDFor(map[string]string{"Event": "Hangup", "Linkedid": "1720000000.10"}); got != 42 {
		t.Fatalf("expected Linkedid to resolve call 42, got %d", got)
	}
}

func TestPowerBatchIDIsForwardedToTheAsteriskDialplan(t *testing.T) {
	variables := originateVariables(originateRequest{
		CallID:         42,
		AgentExtension: "1301",
		AgentEndpoint:  "t3-e1301",
		BatchID:        "power-batch-42",
	})

	for _, expected := range []string{
		"THCONECT_CALL_ID=42",
		"THCONECT_AGENT_EXTENSION=1301",
		"THCONECT_AGENT_ENDPOINT=t3-e1301",
		"THCONECT_BATCH_ID=power-batch-42",
	} {
		if !strings.Contains(variables, expected) {
			t.Fatalf("expected AMI variables to contain %q, got %q", expected, variables)
		}
	}
}

func TestSynchronizingSharedRouteForAnotherCompanyDoesNotReloadPJSIP(t *testing.T) {
	b := &bridge{}
	config := validRouteConfig()
	sharedRoute := config
	sharedRoute.CompanyID = 2
	if !sameRouteConfig(config, sharedRoute) {
		t.Fatal("company association alone must not make the shared PJSIP route different")
	}
	changedRoute := sharedRoute
	changedRoute.SIPHost = "new-sip.example.test"
	if sameRouteConfig(config, changedRoute) {
		t.Fatal("a real SIP host change must still reconfigure the route")
	}

	manager := newRouteManager(b, config)
	b.routes.Store(config.RouteID, manager)

	body, err := json.Marshal(config)
	if err != nil {
		t.Fatal(err)
	}
	request := httptest.NewRequest("PUT", "/v1/routes/2", bytes.NewReader(body))
	response := httptest.NewRecorder()
	b.syncRoute(response, request)

	if response.Code != 200 {
		t.Fatalf("expected a shared route to reuse its existing PJSIP config, got %d: %s", response.Code, response.Body.String())
	}
	current, ok := b.routes.Load(config.RouteID)
	if !ok || current.(*routeManager) != manager {
		t.Fatal("syncing the same shared route must not replace its AMI listener")
	}
}

func TestSameAgentConfigIgnoresOnlyExpiry(t *testing.T) {
	current := agentConfig{UserID: 9, CompanyID: 3, Extension: "1301", Password: "secret", Codecs: "opus,ulaw", ExpiresAt: 10}

	if !sameAgentConfig(current, agentConfig{UserID: 9, CompanyID: 3, Extension: "1301", Password: "secret", Codecs: "opus,ulaw", ExpiresAt: 20}) {
		t.Fatal("expected a session renewal with the same endpoint settings to avoid a PJSIP reload")
	}
	if sameAgentConfig(current, agentConfig{UserID: 9, CompanyID: 3, Extension: "1301", Password: "changed", Codecs: "opus,ulaw"}) {
		t.Fatal("expected changed WebRTC credentials to require a PJSIP reload")
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
