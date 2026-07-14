package main

import (
	"bufio"
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"
)

type routeConfig struct {
	CompanyID       int    `json:"company_id"`
	Host            string `json:"host"`
	Port            int    `json:"port"`
	Username        string `json:"username"`
	Secret          string `json:"secret"`
	CallerID        string `json:"caller_id"`
	ChannelTemplate string `json:"channel_template"`
	Context         string `json:"context"`
	Extension       string `json:"extension"`
}

type originateRequest struct {
	CompanyID int    `json:"company_id"`
	CallID    int    `json:"call_id"`
	ActionID  string `json:"action_id"`
	Phone     string `json:"phone"`
	Channel   string `json:"channel"`
	Context   string `json:"context"`
	Extension string `json:"extension"`
	CallerID  string `json:"caller_id"`
}

type hangupRequest struct {
	CompanyID int    `json:"company_id"`
	CallID    int    `json:"call_id"`
	Channel   string `json:"channel"`
	UniqueID  string `json:"uniqueid"`
}

type actionRef struct {
	CallID int
}

type bridge struct {
	token         string
	webhookURL    string
	webhookSecret string
	client        *http.Client
	routes        sync.Map
}

type routeManager struct {
	bridge  *bridge
	config  routeConfig
	stop    chan struct{}
	done    chan struct{}
	mu      sync.Mutex
	conn    net.Conn
	actions map[string]actionRef
	ids     map[string]int
}

func main() {
	b := &bridge{
		token:         os.Getenv("BRIDGE_SHARED_TOKEN"),
		webhookURL:    os.Getenv("BRIDGE_WEBHOOK_URL"),
		webhookSecret: os.Getenv("BRIDGE_WEBHOOK_SECRET"),
		client:        &http.Client{Timeout: 8 * time.Second},
	}

	if b.token == "" {
		log.Print("BRIDGE_SHARED_TOKEN is empty; privileged endpoints will reject requests")
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", b.health)
	mux.HandleFunc("/v1/routes/test", b.authorized(b.testRoute))
	mux.HandleFunc("/v1/routes/", b.authorized(b.syncRoute))
	mux.HandleFunc("/v1/calls/originate", b.authorized(b.originate))
	mux.HandleFunc("/v1/calls/hangup", b.authorized(b.hangup))

	port := os.Getenv("BRIDGE_PORT")
	if port == "" {
		port = "8090"
	}

	server := &http.Server{
		Addr:              ":" + port,
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
	}
	log.Printf("asterisk bridge listening on :%s", port)
	log.Fatal(server.ListenAndServe())
}

func (b *bridge) health(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{"status": "ok"})
}

func (b *bridge) authorized(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		expected := "Bearer " + b.token
		if b.token == "" || !hmac.Equal([]byte(r.Header.Get("Authorization")), []byte(expected)) {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"message": "unauthorized"})
			return
		}
		next(w, r)
	}
}

func (b *bridge) testRoute(w http.ResponseWriter, r *http.Request) {
	var config routeConfig
	if err := decodeJSON(r, &config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	if err := validateRoute(config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	if err := testAMI(config); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"connected": true})
}

func (b *bridge) syncRoute(w http.ResponseWriter, r *http.Request) {
	companyID, err := companyIDFromPath(r.URL.Path)
	if err != nil {
		writeJSON(w, http.StatusNotFound, map[string]string{"message": "invalid company id"})
		return
	}
	if r.Method == http.MethodDelete {
		if current, ok := b.routes.LoadAndDelete(companyID); ok {
			current.(*routeManager).close()
		}
		writeJSON(w, http.StatusOK, map[string]any{"removed": true})
		return
	}
	if r.Method != http.MethodPut {
		w.Header().Set("Allow", "PUT, DELETE")
		writeJSON(w, http.StatusMethodNotAllowed, map[string]string{"message": "method not allowed"})
		return
	}

	var config routeConfig
	if err := decodeJSON(r, &config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	config.CompanyID = companyID
	if err := validateRoute(config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}

	manager := newRouteManager(b, config)
	if current, loaded := b.routes.Swap(companyID, manager); loaded {
		current.(*routeManager).close()
	}
	manager.start()
	writeJSON(w, http.StatusOK, map[string]any{"synchronized": true, "company_id": companyID})
}

func (b *bridge) originate(w http.ResponseWriter, r *http.Request) {
	var request originateRequest
	if err := decodeJSON(r, &request); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	if request.CompanyID < 1 || request.CallID < 1 || request.ActionID == "" || request.Channel == "" {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "company_id, call_id, action_id and channel are required"})
		return
	}
	current, ok := b.routes.Load(request.CompanyID)
	if !ok {
		writeJSON(w, http.StatusConflict, map[string]string{"message": "route is not synchronized"})
		return
	}
	if err := current.(*routeManager).originate(request); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusAccepted, map[string]any{"accepted": true, "action_id": request.ActionID})
}

func (b *bridge) hangup(w http.ResponseWriter, r *http.Request) {
	var request hangupRequest
	if err := decodeJSON(r, &request); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	current, ok := b.routes.Load(request.CompanyID)
	if !ok {
		writeJSON(w, http.StatusConflict, map[string]string{"message": "route is not synchronized"})
		return
	}
	if request.Channel == "" {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "channel is required to hang up a call"})
		return
	}
	if err := current.(*routeManager).sendAction(map[string]string{"Action": "Hangup", "Channel": request.Channel}); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusAccepted, map[string]any{"accepted": true})
}

func newRouteManager(b *bridge, config routeConfig) *routeManager {
	return &routeManager{bridge: b, config: config, stop: make(chan struct{}), done: make(chan struct{}), actions: map[string]actionRef{}, ids: map[string]int{}}
}

func (m *routeManager) start() { go m.connectLoop() }

func (m *routeManager) close() {
	close(m.stop)
	m.mu.Lock()
	if m.conn != nil {
		_ = m.conn.Close()
	}
	m.mu.Unlock()
	select {
	case <-m.done:
	case <-time.After(2 * time.Second):
	}
}

func (m *routeManager) connectLoop() {
	defer close(m.done)
	for {
		select {
		case <-m.stop:
			return
		default:
		}

		if err := m.connectOnce(); err != nil {
			log.Printf("AMI route company=%d disconnected: %v", m.config.CompanyID, err)
		}
		select {
		case <-m.stop:
			return
		case <-time.After(3 * time.Second):
		}
	}
}

func (m *routeManager) connectOnce() error {
	conn, err := net.DialTimeout("tcp", net.JoinHostPort(m.config.Host, strconv.Itoa(m.config.Port)), 5*time.Second)
	if err != nil {
		return err
	}
	defer conn.Close()

	reader := bufio.NewReader(conn)
	if _, err := readAMIMessage(reader); err != nil {
		return err
	}
	if err := writeAMIAction(conn, map[string]string{"Action": "Login", "Username": m.config.Username, "Secret": m.config.Secret, "Events": "on"}); err != nil {
		return err
	}
	response, err := readAMIMessage(reader)
	if err != nil {
		return err
	}
	if !strings.EqualFold(response["Response"], "Success") {
		return fmt.Errorf("AMI login rejected: %s", response["Message"])
	}

	m.mu.Lock()
	m.conn = conn
	m.mu.Unlock()
	defer func() {
		m.mu.Lock()
		if m.conn == conn {
			m.conn = nil
		}
		m.mu.Unlock()
	}()

	for {
		message, err := readAMIMessage(reader)
		if err != nil {
			return err
		}
		m.processEvent(message)
	}
}

func (m *routeManager) originate(request originateRequest) error {
	m.mu.Lock()
	m.actions[request.ActionID] = actionRef{CallID: request.CallID}
	m.mu.Unlock()

	return m.sendAction(map[string]string{
		"Action": "Originate", "ActionID": request.ActionID, "Channel": request.Channel,
		"Context": request.Context, "Exten": request.Extension, "Priority": "1",
		"CallerID": request.CallerID, "Async": "true", "Timeout": "45000",
		"Variable": "THCONECT_CALL_ID=" + strconv.Itoa(request.CallID),
	})
}

func (m *routeManager) sendAction(fields map[string]string) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	if m.conn == nil {
		return errors.New("AMI route is reconnecting")
	}
	return writeAMIAction(m.conn, fields)
}

func (m *routeManager) processEvent(message map[string]string) {
	event := message["Event"]
	if event == "" {
		return
	}
	callID := m.callIDFor(message)
	if callID == 0 && event == "UserEvent" && message["UserEvent"] == "THCONECTAMD" {
		callID, _ = strconv.Atoi(message["CallId"])
	}
	if callID == 0 {
		return
	}

	uniqueID := firstNonEmpty(message["Uniqueid"], message["UniqueID"], message["DestUniqueid"])
	if uniqueID != "" {
		m.mu.Lock()
		m.ids[uniqueID] = callID
		m.mu.Unlock()
	}

	payload := map[string]any{"company_id": m.config.CompanyID, "call_id": callID, "channel": message["Channel"], "uniqueid": uniqueID, "payload": message}
	switch event {
	case "OriginateResponse":
		payload["action_id"] = message["ActionID"]
		if strings.EqualFold(message["Response"], "Success") {
			payload["event"] = "originate_succeeded"
		} else {
			payload["event"] = "originate_failed"
			payload["cause"] = firstNonEmpty(message["Reason"], message["Message"], "originate_failed")
		}
	case "DialEnd":
		if !strings.EqualFold(message["DialStatus"], "ANSWER") {
			return
		}
		payload["event"] = "answered"
	case "UserEvent":
		if message["UserEvent"] != "THCONECTAMD" {
			return
		}
		payload["event"] = "amd"
		payload["amd_result"] = firstNonEmpty(message["Result"], message["AMDResult"])
	case "Hangup":
		payload["event"] = "hangup"
		payload["cause"] = firstNonEmpty(message["Cause-txt"], message["Cause"], "hangup")
	default:
		return
	}

	go m.bridge.sendWebhook(payload)
}

func (m *routeManager) callIDFor(message map[string]string) int {
	if actionID := message["ActionID"]; actionID != "" {
		m.mu.Lock()
		ref := m.actions[actionID]
		m.mu.Unlock()
		if ref.CallID > 0 {
			return ref.CallID
		}
	}
	if message["Variable"] == "THCONECT_CALL_ID" {
		id, _ := strconv.Atoi(message["Value"])
		return id
	}
	for _, id := range []string{message["Uniqueid"], message["UniqueID"], message["DestUniqueid"]} {
		m.mu.Lock()
		callID := m.ids[id]
		m.mu.Unlock()
		if callID > 0 {
			return callID
		}
	}
	return 0
}

func (b *bridge) sendWebhook(payload map[string]any) {
	if b.webhookURL == "" || b.webhookSecret == "" {
		log.Print("event skipped: webhook URL or secret is empty")
		return
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return
	}
	h := hmac.New(sha256.New, []byte(b.webhookSecret))
	_, _ = h.Write(body)
	request, err := http.NewRequest(http.MethodPost, b.webhookURL, bytes.NewReader(body))
	if err != nil {
		return
	}
	request.Header.Set("Content-Type", "application/json")
	request.Header.Set("X-THCONECT-Signature", hex.EncodeToString(h.Sum(nil)))
	response, err := b.client.Do(request)
	if err != nil {
		log.Printf("webhook delivery failed: %v", err)
		return
	}
	defer response.Body.Close()
	if response.StatusCode >= 300 {
		log.Printf("webhook delivery failed with status %d", response.StatusCode)
	}
}

func testAMI(config routeConfig) error {
	conn, err := net.DialTimeout("tcp", net.JoinHostPort(config.Host, strconv.Itoa(config.Port)), 5*time.Second)
	if err != nil {
		return err
	}
	defer conn.Close()
	reader := bufio.NewReader(conn)
	if _, err = readAMIMessage(reader); err != nil {
		return err
	}
	if err = writeAMIAction(conn, map[string]string{"Action": "Login", "Username": config.Username, "Secret": config.Secret, "Events": "off"}); err != nil {
		return err
	}
	response, err := readAMIMessage(reader)
	if err != nil {
		return err
	}
	if !strings.EqualFold(response["Response"], "Success") {
		return fmt.Errorf("AMI login rejected: %s", response["Message"])
	}
	return writeAMIAction(conn, map[string]string{"Action": "Logoff"})
}

func validateRoute(config routeConfig) error {
	if config.CompanyID < 1 || config.Host == "" || config.Port < 1 || config.Port > 65535 || config.Username == "" || config.Secret == "" {
		return errors.New("company_id, host, port, username and secret are required")
	}
	return nil
}

func readAMIMessage(reader *bufio.Reader) (map[string]string, error) {
	message := map[string]string{}
	for {
		line, err := reader.ReadString('\n')
		if err != nil {
			return nil, err
		}
		line = strings.TrimSpace(line)
		if line == "" {
			return message, nil
		}
		if key, value, ok := strings.Cut(line, ":"); ok {
			message[strings.TrimSpace(key)] = strings.TrimSpace(value)
		}
	}
}

func writeAMIAction(conn net.Conn, fields map[string]string) error {
	var action strings.Builder
	for _, key := range []string{"Action", "ActionID", "Channel", "Context", "Exten", "Priority", "CallerID", "Async", "Timeout", "Variable", "Username", "Secret", "Events"} {
		if value := fields[key]; value != "" {
			action.WriteString(key + ": " + value + "\r\n")
		}
	}
	action.WriteString("\r\n")
	_, err := io.WriteString(conn, action.String())
	return err
}

func companyIDFromPath(path string) (int, error) {
	value := strings.Trim(strings.TrimPrefix(path, "/v1/routes/"), "/")
	return strconv.Atoi(value)
}

func decodeJSON(r *http.Request, value any) error {
	decoder := json.NewDecoder(io.LimitReader(r.Body, 64<<10))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(value); err != nil {
		return err
	}
	return nil
}

func writeJSON(w http.ResponseWriter, status int, value any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(value)
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if value != "" {
			return value
		}
	}
	return ""
}
