package main

import (
	"bufio"
	"bytes"
	"crypto/aes"
	"crypto/cipher"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"crypto/tls"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"
)

type routeConfig struct {
	CompanyID                 int    `json:"company_id"`
	RouteID                   int    `json:"route_id"`
	Host                      string `json:"host"`
	Port                      int    `json:"port"`
	Username                  string `json:"username"`
	Secret                    string `json:"secret"`
	CallerID                  string `json:"caller_id"`
	ChannelTemplate           string `json:"channel_template"`
	SupervisorChannelTemplate string `json:"supervisor_channel_template"`
	Context                   string `json:"context"`
	Extension                 string `json:"extension"`
	SIPHost                   string `json:"sip_host"`
	SIPPort                   int    `json:"sip_port"`
	FromDomain                string `json:"from_domain"`
	OutboundProxy             string `json:"outbound_proxy"`
	SIPUsername               string `json:"sip_username"`
	SIPPassword               string `json:"sip_password"`
	FromUser                  string `json:"from_user"`
	ContactUser               string `json:"contact_user"`
	AuthenticationMode        string `json:"authentication_mode"`
	Transport                 string `json:"transport"`
	Codecs                    string `json:"codecs"`
	DTMFMode                  string `json:"dtmf_mode"`
	CallerIDHeader            string `json:"caller_id_header"`
	QualifyFrequency          int    `json:"qualify_frequency"`
	RegistrationExpiration    int    `json:"registration_expiration"`
}

type originateRequest struct {
	CompanyID      int    `json:"company_id"`
	RouteID        int    `json:"route_id"`
	CallID         int    `json:"call_id"`
	ActionID       string `json:"action_id"`
	Phone          string `json:"phone"`
	Channel        string `json:"channel"`
	Context        string `json:"context"`
	Extension      string `json:"extension"`
	CallerID       string `json:"caller_id"`
	AgentExtension string `json:"agent_extension"`
	AgentEndpoint  string `json:"agent_endpoint"`
	RecordingFile  string `json:"recording_file"`
	BatchID        string `json:"batch_id"`
}

type agentConfig struct {
	UserID    int    `json:"user_id"`
	CompanyID int    `json:"company_id"`
	Extension string `json:"extension"`
	Password  string `json:"password"`
	Codecs    string `json:"codecs"`
	ExpiresAt int64  `json:"expires_at"`
}

type persistedState struct {
	Version int           `json:"version"`
	Routes  []routeConfig `json:"routes"`
	Agents  []agentConfig `json:"agents"`
}

type hangupRequest struct {
	CompanyID int    `json:"company_id"`
	RouteID   int    `json:"route_id"`
	CallID    int    `json:"call_id"`
	Channel   string `json:"channel"`
	UniqueID  string `json:"uniqueid"`
}

type superviseRequest struct {
	CompanyID           int    `json:"company_id"`
	RouteID             int    `json:"route_id"`
	CallID              int    `json:"call_id"`
	AgentChannel        string `json:"agent_channel"`
	AgentExtension      string `json:"agent_extension"`
	SupervisorExtension string `json:"supervisor_extension"`
	Mode                string `json:"mode"`
	Persistent          bool   `json:"persistent"`
}

type actionRef struct {
	CallID    int
	CompanyID int
}

type bridge struct {
	token           string
	webhookURL      string
	webhookSecret   string
	client          *http.Client
	routes          sync.Map
	agents          sync.Map
	configPath      string
	agentConfigPath string
	statePath       string
	stateKey        []byte
	managerConfig   routeConfig
	routeMu         sync.Mutex
	agentMu         sync.Mutex
	persistMu       sync.Mutex
}

type routeManager struct {
	bridge        *bridge
	config        routeConfig
	stop          chan struct{}
	done          chan struct{}
	mu            sync.Mutex
	conn          net.Conn
	actions       map[string]actionRef
	ids           map[string]int
	companies     map[int]int
	recordings    map[int]string
	recordingSent map[int]bool
	cancelled     map[int]bool
	cancelSent    map[int]bool
	channels      map[int]string
	webhookQueues []chan map[string]any
}

func main() {
	b := &bridge{
		token:           os.Getenv("BRIDGE_SHARED_TOKEN"),
		webhookURL:      os.Getenv("BRIDGE_WEBHOOK_URL"),
		webhookSecret:   os.Getenv("BRIDGE_WEBHOOK_SECRET"),
		client:          &http.Client{Timeout: 8 * time.Second},
		configPath:      os.Getenv("ASTERISK_CONFIG_PATH"),
		agentConfigPath: os.Getenv("ASTERISK_AGENT_CONFIG_PATH"),
		statePath:       os.Getenv("BRIDGE_STATE_PATH"),
		managerConfig: routeConfig{
			Host:     os.Getenv("ASTERISK_AMI_HOST"),
			Port:     envInt("ASTERISK_AMI_PORT", 5038),
			Username: os.Getenv("ASTERISK_AMI_USERNAME"),
			Secret:   os.Getenv("ASTERISK_AMI_SECRET"),
		},
	}
	stateKey, err := decodeStateKey(os.Getenv("BRIDGE_STATE_KEY"))
	if err != nil {
		log.Fatalf("BRIDGE_STATE_KEY is invalid: %v", err)
	}
	b.stateKey = stateKey
	if b.statePath == "" && b.configPath != "" {
		b.statePath = filepath.Join(filepath.Dir(b.configPath), "thconect-bridge-state.enc")
	}
	if b.agentConfigPath == "" && b.configPath != "" {
		b.agentConfigPath = filepath.Join(filepath.Dir(b.configPath), "thconect-agents.conf")
	}

	if b.token == "" {
		log.Print("BRIDGE_SHARED_TOKEN is empty; privileged endpoints will reject requests")
	}
	if err := b.restoreRoutes(); err != nil {
		log.Printf("route state restore failed: %v", err)
	}
	go b.expiredAgentLoop()

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", b.health)
	mux.HandleFunc("/v1/routes/test", b.authorized(b.testRoute))
	mux.HandleFunc("/v1/routes/", b.authorized(b.syncRoute))
	mux.HandleFunc("/v1/agents/", b.authorized(b.syncAgent))
	mux.HandleFunc("/v1/calls/originate", b.authorized(b.originate))
	mux.HandleFunc("/v1/calls/hangup", b.authorized(b.hangup))
	mux.HandleFunc("/v1/calls/supervise", b.authorized(b.supervise))

	port := os.Getenv("BRIDGE_PORT")
	if port == "" {
		port = "8090"
	}

	server := &http.Server{
		Addr:              ":" + port,
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       60 * time.Second,
		MaxHeaderBytes:    16 << 10,
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
	b.routeMu.Lock()
	defer b.routeMu.Unlock()

	var config routeConfig
	if err := decodeJSON(r, &config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	config = normalizeRoute(config)
	if err := validateRoute(config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	if err := b.provisionRoutes(b.currentRouteConfigs(config)); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	defer func() {
		if err := b.provisionCurrentRoutes(); err != nil {
			log.Printf("failed to restore synchronized PJSIP routes after test: %v", err)
			return
		}
		if err := reloadPJSIP(config); err != nil {
			log.Printf("failed to reload synchronized PJSIP routes after test: %v", err)
		}
	}()
	if err := testAMI(config); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	status, err := testSIPReachability(config)
	if err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"connected": true, "sip_status": status})
}

func (b *bridge) syncRoute(w http.ResponseWriter, r *http.Request) {
	b.routeMu.Lock()
	defer b.routeMu.Unlock()

	companyID, err := companyIDFromPath(r.URL.Path)
	if err != nil {
		writeJSON(w, http.StatusNotFound, map[string]string{"message": "invalid company id"})
		return
	}
	if r.Method == http.MethodDelete {
		b.routes.Range(func(key, value any) bool {
			manager := value.(*routeManager)
			if manager.config.CompanyID == companyID {
				b.routes.Delete(key)
				manager.close()
			}
			return true
		})
		if err := b.provisionCurrentRoutes(); err != nil {
			writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
			return
		}
		if err := b.persistRoutes(); err != nil {
			writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
			return
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
	config = normalizeRoute(config)
	if err := validateRoute(config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	manager := newRouteManager(b, config)
	if current, loaded := b.routes.Swap(config.RouteID, manager); loaded {
		current.(*routeManager).close()
	}
	if err := b.provisionCurrentRoutes(); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	manager.start()
	if err := b.persistRoutes(); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"synchronized": true, "company_id": companyID})
}

func (b *bridge) syncAgent(w http.ResponseWriter, r *http.Request) {
	b.agentMu.Lock()
	defer b.agentMu.Unlock()

	userID, err := idFromPath(r.URL.Path, "/v1/agents/")
	if err != nil {
		writeJSON(w, http.StatusNotFound, map[string]string{"message": "invalid user id"})
		return
	}

	if r.Method == http.MethodDelete {
		var request struct {
			CompanyID int `json:"company_id"`
		}
		if err := decodeJSON(r, &request); err != nil || request.CompanyID < 1 {
			writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "company_id is required"})
			return
		}
		if current, ok := b.agents.Load(userID); ok && current.(agentConfig).CompanyID != request.CompanyID {
			writeJSON(w, http.StatusNotFound, map[string]string{"message": "agent endpoint not found"})
			return
		}
		b.agents.Delete(userID)
		if err := b.provisionCurrentAgents(); err != nil {
			writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
			return
		}
		if err := b.reloadFromAnyRoute(); err != nil {
			writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
			return
		}
		if err := b.persistRoutes(); err != nil {
			writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"removed": true})
		return
	}

	if r.Method != http.MethodPut {
		w.Header().Set("Allow", "PUT, DELETE")
		writeJSON(w, http.StatusMethodNotAllowed, map[string]string{"message": "method not allowed"})
		return
	}

	var config agentConfig
	if err := decodeJSON(r, &config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	config.UserID = userID
	config.ExpiresAt = time.Now().Add(3 * time.Minute).Unix()
	if err := validateAgent(config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	previous, hadPrevious := b.agents.Load(userID)
	if hadPrevious && sameAgentConfig(previous.(agentConfig), config) {
		b.agents.Store(userID, config)
		if err := b.persistRoutes(); err != nil {
			writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"synchronized": true, "user_id": userID})
		return
	}
	if err := b.provisionAgents(b.currentAgentConfigs(config)); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	b.agents.Store(userID, config)
	if err := b.reloadFromAnyRoute(); err != nil {
		if hadPrevious {
			b.agents.Store(userID, previous)
		} else {
			b.agents.Delete(userID)
		}
		_ = b.provisionCurrentAgents()
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	if err := b.persistRoutes(); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"synchronized": true, "user_id": userID})
}

func sameAgentConfig(left agentConfig, right agentConfig) bool {
	return left.UserID == right.UserID &&
		left.CompanyID == right.CompanyID &&
		left.Extension == right.Extension &&
		left.Password == right.Password &&
		left.Codecs == right.Codecs
}

func (b *bridge) originate(w http.ResponseWriter, r *http.Request) {
	var request originateRequest
	if err := decodeJSON(r, &request); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	if request.CompanyID < 1 || request.RouteID < 1 || request.CallID < 1 || request.ActionID == "" || request.Channel == "" || request.AgentExtension == "" || request.AgentEndpoint == "" {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "company_id, route_id, call_id, action_id, channel, agent_extension and agent_endpoint are required"})
		return
	}
	if request.AgentEndpoint != technicalEndpoint(request.CompanyID, request.AgentExtension) {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "agent_endpoint does not match company and extension"})
		return
	}
	current, ok := b.routes.Load(request.RouteID)
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
	current, ok := b.routes.Load(request.RouteID)
	if !ok {
		writeJSON(w, http.StatusConflict, map[string]string{"message": "route is not synchronized"})
		return
	}
	manager := current.(*routeManager)
	var err error
	if request.Channel == "" {
		err = manager.cancelCall(request.CallID, request.CompanyID)
	} else {
		err = manager.sendAction(map[string]string{"Action": "Hangup", "Channel": request.Channel})
	}
	if err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusAccepted, map[string]any{"accepted": true})
}

func (b *bridge) supervise(w http.ResponseWriter, r *http.Request) {
	var request superviseRequest
	if err := decodeJSON(r, &request); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	if request.CompanyID < 1 || request.RouteID < 1 || request.SupervisorExtension == "" || (request.AgentChannel == "" && request.AgentExtension == "") {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "company_id, route_id, supervisor_extension and an agent channel or extension are required"})
		return
	}
	if request.Mode != "listen" && request.Mode != "whisper" && request.Mode != "barge" {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "mode must be listen, whisper or barge"})
		return
	}
	current, ok := b.routes.Load(request.RouteID)
	if !ok {
		writeJSON(w, http.StatusConflict, map[string]string{"message": "route is not synchronized"})
		return
	}
	if err := current.(*routeManager).supervise(request); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
	}
	writeJSON(w, http.StatusAccepted, map[string]any{"accepted": true, "mode": request.Mode})
}

func newRouteManager(b *bridge, config routeConfig) *routeManager {
	return &routeManager{bridge: b, config: config, stop: make(chan struct{}), done: make(chan struct{}), actions: map[string]actionRef{}, ids: map[string]int{}, companies: map[int]int{}, recordings: map[int]string{}, recordingSent: map[int]bool{}, cancelled: map[int]bool{}, cancelSent: map[int]bool{}, channels: map[int]string{}}
}

func (m *routeManager) start() {
	m.webhookQueues = make([]chan map[string]any, 8)
	for i := range m.webhookQueues {
		queue := make(chan map[string]any, 256)
		m.webhookQueues[i] = queue
		go func() {
			for {
				select {
				case payload := <-queue:
					m.bridge.sendWebhook(payload)
				case <-m.stop:
					return
				}
			}
		}()
	}
	go m.connectLoop()
}

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
	if err := conn.SetDeadline(time.Now().Add(8 * time.Second)); err != nil {
		return err
	}

	reader := bufio.NewReader(conn)
	if err := readAMIGreeting(reader); err != nil {
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
	if err := writeAMIAction(conn, map[string]string{"Action": "Command", "Command": "pjsip reload"}); err != nil {
		return err
	}
	if _, err := readAMIMessage(reader); err != nil {
		return err
	}
	if err := conn.SetDeadline(time.Time{}); err != nil {
		return err
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
	m.actions[request.ActionID] = actionRef{CallID: request.CallID, CompanyID: request.CompanyID}
	m.companies[request.CallID] = request.CompanyID
	m.recordings[request.CallID] = request.RecordingFile
	m.mu.Unlock()

	return m.sendAction(map[string]string{
		"Action": "Originate", "ActionID": request.ActionID, "Channel": request.Channel,
		"Context": request.Context, "Exten": request.Extension, "Priority": "1",
		"CallerID": request.CallerID, "Async": "true", "Timeout": "45000",
		"Variable": originateVariables(request),
	})
}

func (m *routeManager) cancelCall(callID, companyID int) error {
	if callID < 1 || companyID < 1 {
		return errors.New("invalid call or company")
	}
	m.mu.Lock()
	if m.companies[callID] != companyID {
		m.mu.Unlock()
		return errors.New("call is not active on this route")
	}
	m.cancelled[callID] = true
	channel := m.channels[callID]
	m.mu.Unlock()
	if channel != "" {
		return m.hangupCancelled(callID, channel)
	}
	return nil
}

func (m *routeManager) hangupCancelled(callID int, channel string) error {
	m.mu.Lock()
	if m.cancelSent[callID] {
		m.mu.Unlock()
		return nil
	}
	m.cancelSent[callID] = true
	m.mu.Unlock()
	if err := m.sendAction(map[string]string{"Action": "Hangup", "Channel": channel}); err != nil {
		m.mu.Lock()
		delete(m.cancelSent, callID)
		m.mu.Unlock()
		return err
	}
	return nil
}

func originateVariables(request originateRequest) string {
	variables := []string{
		"THCONECT_CALL_ID=" + strconv.Itoa(request.CallID),
		"THCONECT_AGENT_EXTENSION=" + request.AgentExtension,
		"THCONECT_AGENT_ENDPOINT=" + request.AgentEndpoint,
	}
	if request.BatchID != "" {
		variables = append(variables, "THCONECT_BATCH_ID="+request.BatchID)
	}

	return strings.Join(variables, ",")
}

func (m *routeManager) supervise(request superviseRequest) error {
	channelTemplate := m.config.SupervisorChannelTemplate
	if channelTemplate == "" {
		channelTemplate = "PJSIP/{extension}"
	}
	if !strings.Contains(channelTemplate, "{extension}") {
		return errors.New("supervisor channel template must contain {extension}")
	}
	supervisorChannel := strings.ReplaceAll(channelTemplate, "{extension}", request.SupervisorExtension)
	agentTarget, err := supervisionTarget(request)
	if err != nil {
		return err
	}

	options := "q"
	switch request.Mode {
	case "whisper":
		options = "qw"
	case "barge":
		options = "qB"
	}

	return m.sendAction(map[string]string{
		"Action":      "Originate",
		"Channel":     supervisorChannel,
		"Application": "ChanSpy",
		"Data":        agentTarget + "," + options,
		"Async":       "true",
	})
}

func supervisionTarget(request superviseRequest) (string, error) {
	if request.Persistent {
		extension, err := strconv.Atoi(request.AgentExtension)
		if request.CompanyID < 1 || err != nil || extension < 1000 || extension > 99999 {
			return "", errors.New("agent extension must be numeric and between 1000 and 99999")
		}

		return "PJSIP/" + technicalEndpoint(request.CompanyID, request.AgentExtension), nil
	}
	if request.AgentChannel == "" {
		return "", errors.New("agent channel is required")
	}

	return request.AgentChannel, nil
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
	companyID := m.companyIDFor(callID)
	if companyID == 0 {
		companyID = m.config.CompanyID
	}

	uniqueID := firstNonEmpty(message["Uniqueid"], message["UniqueID"], message["DestUniqueid"], message["DestUniqueID"])
	if uniqueID != "" {
		m.mu.Lock()
		for _, id := range correlationIDs(message) {
			m.ids[id] = callID
		}
		m.mu.Unlock()
	}
	channel := message["Channel"]
	if strings.HasPrefix(channel, "PJSIP/thconect-route-") {
		m.mu.Lock()
		m.channels[callID] = channel
		cancelled := m.cancelled[callID]
		m.mu.Unlock()
		if cancelled {
			if err := m.hangupCancelled(callID, channel); err != nil {
				log.Printf("cancel call %d: %v", callID, err)
			}
		}
	}

	payload := map[string]any{"company_id": companyID, "call_id": callID, "channel": message["Channel"], "uniqueid": uniqueID, "payload": message}
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
		if strings.EqualFold(message["DialStatus"], "ANSWER") {
			payload["event"] = "answered"
		} else if strings.HasPrefix(message["DestChannel"], fmt.Sprintf("PJSIP/t%d-e", companyID)) {
			payload["event"] = "agent_delivery_failed"
			payload["cause"] = firstNonEmpty(message["DialStatus"], "agent_unreachable")
			if techCause := message["TechCause"]; techCause != "" {
				payload["cause"] = fmt.Sprintf("%s (%s)", payload["cause"], techCause)
			}
		} else {
			return
		}
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

	if len(m.webhookQueues) == 0 {
		go m.bridge.sendWebhook(payload)
	} else {
		m.webhookQueues[callID%len(m.webhookQueues)] <- payload
	}
	if event == "Hangup" {
		go m.finishCall(callID, companyID)
	}
}

type recordingInfo struct {
	file string
	size int64
}

func (m *routeManager) recordingFor(callID int) (recordingInfo, bool) {
	m.mu.Lock()
	file := m.recordings[callID]
	m.mu.Unlock()
	if file == "" {
		return recordingInfo{}, false
	}
	info, err := os.Stat(filepath.Join("/recordings", filepath.Base(file)))
	if err != nil || info.Size() <= 44 {
		return recordingInfo{}, false
	}
	return recordingInfo{file: file, size: info.Size()}, true
}

func (m *routeManager) finishCall(callID int, companyID int) {
	for attempt := 0; attempt < 8; attempt++ {
		if recording, ok := m.recordingFor(callID); ok {
			m.mu.Lock()
			alreadySent := m.recordingSent[callID]
			m.recordingSent[callID] = true
			m.mu.Unlock()
			if !alreadySent {
				m.bridge.sendWebhook(map[string]any{
					"company_id":       companyID,
					"call_id":          callID,
					"event":            "recording_ready",
					"recording_path":   "recordings/" + filepath.Base(recording.file),
					"recording_format": strings.TrimPrefix(filepath.Ext(recording.file), "."),
					"recording_size":   recording.size,
				})
			}
			break
		}
		time.Sleep(500 * time.Millisecond)
	}
	time.Sleep(30 * time.Second)
	m.cleanupCall(callID)
}

func (m *routeManager) cleanupCall(callID int) {
	m.mu.Lock()
	defer m.mu.Unlock()
	delete(m.companies, callID)
	delete(m.recordings, callID)
	delete(m.recordingSent, callID)
	delete(m.cancelled, callID)
	delete(m.cancelSent, callID)
	delete(m.channels, callID)
	for actionID, ref := range m.actions {
		if ref.CallID == callID {
			delete(m.actions, actionID)
		}
	}
	for uniqueID, id := range m.ids {
		if id == callID {
			delete(m.ids, uniqueID)
		}
	}
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
	for _, id := range correlationIDs(message) {
		m.mu.Lock()
		callID := m.ids[id]
		m.mu.Unlock()
		if callID > 0 {
			return callID
		}
	}
	return 0
}

func correlationIDs(message map[string]string) []string {
	seen := map[string]bool{}
	ids := make([]string, 0, 6)
	for _, id := range []string{
		message["Uniqueid"], message["UniqueID"], message["DestUniqueid"], message["DestUniqueID"],
		message["Linkedid"], message["LinkedID"],
	} {
		if id != "" && !seen[id] {
			seen[id] = true
			ids = append(ids, id)
		}
	}

	return ids
}

func (m *routeManager) companyIDFor(callID int) int {
	m.mu.Lock()
	defer m.mu.Unlock()
	return m.companies[callID]
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
	timestamp := strconv.FormatInt(time.Now().Unix(), 10)
	h := hmac.New(sha256.New, []byte(b.webhookSecret))
	_, _ = h.Write([]byte(timestamp + "."))
	_, _ = h.Write(body)
	request, err := http.NewRequest(http.MethodPost, b.webhookURL, bytes.NewReader(body))
	if err != nil {
		return
	}
	request.Header.Set("Content-Type", "application/json")
	request.Header.Set("X-THCONECT-Signature", hex.EncodeToString(h.Sum(nil)))
	request.Header.Set("X-THCONECT-Timestamp", timestamp)
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
	if err := reloadPJSIP(config); err != nil {
		return err
	}
	return nil
}

func reloadPJSIP(config routeConfig) error {
	conn, err := net.DialTimeout("tcp", net.JoinHostPort(config.Host, strconv.Itoa(config.Port)), 5*time.Second)
	if err != nil {
		return err
	}
	defer conn.Close()
	if err := conn.SetDeadline(time.Now().Add(8 * time.Second)); err != nil {
		return err
	}
	reader := bufio.NewReader(conn)
	if err = readAMIGreeting(reader); err != nil {
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
	if err = writeAMIAction(conn, map[string]string{"Action": "Command", "Command": "pjsip reload"}); err != nil {
		return err
	}
	reloadResponse, err := readAMIMessage(reader)
	if err != nil {
		return err
	}
	if !oneOf(strings.ToLower(reloadResponse["Response"]), "success", "follows") {
		return errors.New("Asterisk rejected the PJSIP reload")
	}
	return writeAMIAction(conn, map[string]string{"Action": "Logoff"})
}

func testSIPReachability(config routeConfig) (string, error) {
	address := net.JoinHostPort(config.SIPHost, strconv.Itoa(config.SIPPort))
	var (
		conn net.Conn
		err  error
	)
	switch config.Transport {
	case "tls":
		dialer := &net.Dialer{Timeout: 5 * time.Second}
		conn, err = tls.DialWithDialer(dialer, "tcp", address, &tls.Config{
			MinVersion: tls.VersionTLS12,
			ServerName: config.SIPHost,
		})
	case "tcp":
		conn, err = net.DialTimeout("tcp", address, 5*time.Second)
	default:
		conn, err = net.DialTimeout("udp", address, 5*time.Second)
	}
	if err != nil {
		return "", fmt.Errorf("SIP %s connection failed: %w", strings.ToUpper(config.Transport), err)
	}
	defer conn.Close()
	if err := conn.SetDeadline(time.Now().Add(5 * time.Second)); err != nil {
		return "", err
	}

	branch := randomToken(12)
	callID := randomToken(16) + "@thconect"
	localHost, localPort, err := net.SplitHostPort(conn.LocalAddr().String())
	if err != nil {
		return "", err
	}
	transport := strings.ToUpper(config.Transport)
	requestURI := fmt.Sprintf("sip:%s:%d", config.SIPHost, config.SIPPort)
	probe := fmt.Sprintf(
		"OPTIONS %s SIP/2.0\r\nVia: SIP/2.0/%s %s:%s;branch=z9hG4bK%s;rport\r\nFrom: <sip:healthcheck@%s>;tag=%s\r\nTo: <%s>\r\nCall-ID: %s\r\nCSeq: 1 OPTIONS\r\nMax-Forwards: 70\r\nUser-Agent: THCONECT-Route-Check\r\nContent-Length: 0\r\n\r\n",
		requestURI, transport, localHost, localPort, branch, config.FromDomain, branch, requestURI, callID,
	)
	if _, err := io.WriteString(conn, probe); err != nil {
		return "", fmt.Errorf("SIP OPTIONS send failed: %w", err)
	}
	line, err := bufio.NewReader(io.LimitReader(conn, 8<<10)).ReadString('\n')
	if err != nil {
		return "", fmt.Errorf("SIP endpoint did not answer OPTIONS: %w", err)
	}
	line = strings.TrimSpace(line)
	if !successfulSIPResponse(line) {
		return "", fmt.Errorf("SIP endpoint rejected OPTIONS: %s", line)
	}
	return line, nil
}

func successfulSIPResponse(line string) bool {
	return regexp.MustCompile(`^SIP/2\.0 2[0-9]{2}(?: |$)`).MatchString(line)
}

func randomToken(bytesCount int) string {
	value := make([]byte, bytesCount)
	if _, err := io.ReadFull(rand.Reader, value); err != nil {
		return strconv.FormatInt(time.Now().UnixNano(), 16)
	}
	return hex.EncodeToString(value)
}

func validateRoute(config routeConfig) error {
	if config.CompanyID < 1 || config.RouteID < 1 || config.Host == "" || config.Port < 1 || config.Port > 65535 || config.Username == "" || config.Secret == "" || config.SIPHost == "" {
		return errors.New("company_id, route_id, AMI and SIP host settings are required")
	}
	if config.SIPPort < 1 || config.SIPPort > 65535 {
		return errors.New("sip_port must be between 1 and 65535")
	}
	if !oneOf(config.AuthenticationMode, "ip", "registration", "digest") {
		return errors.New("authentication_mode must be ip, registration or digest")
	}
	if config.AuthenticationMode != "ip" && (config.SIPUsername == "" || config.SIPPassword == "") {
		return errors.New("SIP username and password are required for authenticated routes")
	}
	if !oneOf(config.Transport, "udp", "tcp", "tls") {
		return errors.New("transport must be udp, tcp or tls")
	}
	if !oneOf(config.DTMFMode, "rfc4733", "info", "inband", "auto") {
		return errors.New("unsupported dtmf_mode")
	}
	if !oneOf(config.CallerIDHeader, "none", "pai", "rpid", "both") {
		return errors.New("unsupported caller_id_header")
	}
	if config.QualifyFrequency < 0 || config.QualifyFrequency > 300 || config.RegistrationExpiration < 60 || config.RegistrationExpiration > 86400 {
		return errors.New("invalid qualify or registration interval")
	}
	if !regexp.MustCompile(`^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$`).MatchString(config.SIPHost) ||
		!regexp.MustCompile(`^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$`).MatchString(config.FromDomain) {
		return errors.New("invalid SIP host or From domain")
	}
	if !regexp.MustCompile(`^[A-Za-z0-9_.-]+$`).MatchString(config.Context) || !regexp.MustCompile(`^[A-Za-z0-9_.+*#-]+$`).MatchString(config.Extension) {
		return errors.New("invalid dialplan context or extension")
	}
	if config.OutboundProxy != "" && !regexp.MustCompile(`^sips?:[A-Za-z0-9._~:@?&=+;,%\-\[\]]+$`).MatchString(config.OutboundProxy) {
		return errors.New("invalid outbound proxy")
	}
	for _, value := range []string{config.SIPUsername, config.FromUser, config.ContactUser} {
		if value != "" && !regexp.MustCompile(`^[A-Za-z0-9_.+@-]+$`).MatchString(value) {
			return errors.New("invalid SIP user value")
		}
	}
	if config.SIPPassword != "" && !regexp.MustCompile(`^[A-Za-z0-9!#$%&()*+,./:<=>?@^_{}~-]+$`).MatchString(config.SIPPassword) {
		return errors.New("SIP password contains unsafe configuration characters")
	}
	if config.CallerID != "" && !regexp.MustCompile(`^[0-9+]+$`).MatchString(config.CallerID) {
		return errors.New("invalid caller id")
	}
	allowedCodecs := map[string]bool{"alaw": true, "ulaw": true, "g722": true, "gsm": true, "opus": true, "g729": true}
	seenCodecs := map[string]bool{}
	for _, codec := range strings.Split(config.Codecs, ",") {
		codec = strings.TrimSpace(strings.ToLower(codec))
		if !allowedCodecs[codec] || seenCodecs[codec] {
			return errors.New("unsupported, duplicate or unsafe codec")
		}
		seenCodecs[codec] = true
	}
	if len(seenCodecs) == 0 {
		return errors.New("at least one codec is required")
	}
	return nil
}

func (b *bridge) provisionCurrentRoutes() error {
	return b.provisionRoutes(b.currentRouteConfigs())
}

func (b *bridge) provisionCurrentAgents() error {
	return b.provisionAgents(b.currentAgentConfigs())
}

func (b *bridge) provisionAgents(agents []agentConfig) error {
	if b.agentConfigPath == "" {
		return errors.New("ASTERISK_AGENT_CONFIG_PATH is not configured")
	}
	content, err := renderAgentPJSIPConfig(agents)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(b.agentConfigPath), 0755); err != nil {
		return err
	}
	temporaryPath := b.agentConfigPath + ".tmp"
	if err := os.WriteFile(temporaryPath, []byte(content), 0600); err != nil {
		return err
	}
	return os.Rename(temporaryPath, b.agentConfigPath)
}

func renderAgentPJSIPConfig(agents []agentConfig) (string, error) {
	sort.Slice(agents, func(i, j int) bool { return agents[i].UserID < agents[j].UserID })
	var content strings.Builder
	content.WriteString("; Generated by THCONECT Asterisk Bridge. Do not edit manually.\n")
	seenUsers := map[int]bool{}
	seenEndpoints := map[string]bool{}
	for _, agent := range agents {
		if agent.ExpiresAt > 0 && agent.ExpiresAt <= time.Now().Unix() {
			continue
		}
		if err := validateAgent(agent); err != nil {
			return "", fmt.Errorf("agent %d: %w", agent.UserID, err)
		}
		name := technicalEndpoint(agent.CompanyID, agent.Extension)
		if seenUsers[agent.UserID] || seenEndpoints[name] {
			return "", errors.New("duplicate WebRTC agent endpoint")
		}
		seenUsers[agent.UserID] = true
		seenEndpoints[name] = true

		content.WriteString(fmt.Sprintf("\n[%s]\ntype=endpoint\ntransport=transport-ws\ncontext=thconect-agent-denied\ndisallow=all\n", name))
		for _, codec := range strings.Split(agent.Codecs, ",") {
			content.WriteString("allow=" + strings.TrimSpace(strings.ToLower(codec)) + "\n")
		}
		content.WriteString(fmt.Sprintf("aors=%s\nauth=%s-auth\nwebrtc=yes\ndirect_media=no\nforce_rport=yes\nrewrite_contact=yes\nrtp_symmetric=yes\nmax_audio_streams=1\nmax_video_streams=0\ndevice_state_busy_at=1\n", name, name))
		content.WriteString(fmt.Sprintf("\n[%s]\ntype=aor\nmax_contacts=1\nremove_existing=yes\nqualify_frequency=30\n", name))
		content.WriteString(fmt.Sprintf("\n[%s-auth]\ntype=auth\nauth_type=userpass\nusername=%s\npassword=%s\n", name, name, agent.Password))
	}
	return content.String(), nil
}

func validateAgent(config agentConfig) error {
	if config.UserID < 1 || config.CompanyID < 1 || !regexp.MustCompile(`^[0-9]{4,5}$`).MatchString(config.Extension) {
		return errors.New("user_id, company_id and a 4-5 digit extension are required")
	}
	if len(config.Password) < 40 || len(config.Password) > 128 || !regexp.MustCompile(`^[A-Za-z0-9]+$`).MatchString(config.Password) {
		return errors.New("agent password must be 40-128 alphanumeric characters")
	}
	allowed := map[string]bool{"opus": true, "ulaw": true, "alaw": true, "g722": true}
	seen := map[string]bool{}
	for _, codec := range strings.Split(config.Codecs, ",") {
		codec = strings.TrimSpace(strings.ToLower(codec))
		if !allowed[codec] || seen[codec] {
			return errors.New("unsupported, duplicate or unsafe WebRTC codec")
		}
		seen[codec] = true
	}
	if len(seen) == 0 {
		return errors.New("at least one WebRTC codec is required")
	}
	return nil
}

func technicalEndpoint(companyID int, extension string) string {
	return "t" + strconv.Itoa(companyID) + "-e" + extension
}

func (b *bridge) provisionRoutes(routes []routeConfig) error {
	if b.configPath == "" {
		return errors.New("ASTERISK_CONFIG_PATH is not configured")
	}
	content, err := renderPJSIPConfig(routes)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(b.configPath), 0755); err != nil {
		return err
	}
	temporaryPath := b.configPath + ".tmp"
	if err := os.WriteFile(temporaryPath, []byte(content), 0600); err != nil {
		return err
	}
	return os.Rename(temporaryPath, b.configPath)
}

func renderPJSIPConfig(routes []routeConfig) (string, error) {
	sort.Slice(routes, func(i, j int) bool { return routes[i].RouteID < routes[j].RouteID })
	var content strings.Builder
	content.WriteString("; Generated by THCONECT Asterisk Bridge. Do not edit manually.\n")
	seenRoutes := map[int]bool{}
	for _, originalRoute := range routes {
		route := normalizeRoute(originalRoute)
		if seenRoutes[route.RouteID] {
			continue
		}
		if err := validateRoute(route); err != nil {
			return "", fmt.Errorf("route %d: %w", route.RouteID, err)
		}
		seenRoutes[route.RouteID] = true
		name := fmt.Sprintf("thconect-route-%d", route.RouteID)
		content.WriteString(fmt.Sprintf("\n[%s]\ntype=endpoint\ntransport=transport-%s\ncontext=%s\ndisallow=all\n", name, route.Transport, route.Context))
		for _, codec := range strings.Split(route.Codecs, ",") {
			codec = strings.TrimSpace(codec)
			if codec != "" {
				content.WriteString("allow=" + codec + "\n")
			}
		}
		content.WriteString(fmt.Sprintf("aors=%s\nfrom_domain=%s\ndirect_media=no\nrtp_symmetric=yes\nforce_rport=yes\nrewrite_contact=yes\ndtmf_mode=%s\n", name, route.FromDomain, route.DTMFMode))
		if route.FromUser != "" {
			content.WriteString("from_user=" + route.FromUser + "\n")
		}
		if route.AuthenticationMode != "ip" {
			content.WriteString(fmt.Sprintf("outbound_auth=%s-auth\n", name))
		}
		if route.CallerIDHeader == "pai" || route.CallerIDHeader == "both" {
			content.WriteString("send_pai=yes\ntrust_id_outbound=yes\n")
		}
		if route.CallerIDHeader == "rpid" || route.CallerIDHeader == "both" {
			content.WriteString("send_rpid=yes\ntrust_id_outbound=yes\n")
		}
		if route.OutboundProxy != "" {
			content.WriteString("outbound_proxy=" + route.OutboundProxy + "\n")
		}
		uri := sipURI(route)
		content.WriteString(fmt.Sprintf("\n[%s]\ntype=aor\ncontact=%s\nqualify_frequency=%d\n", name, uri, route.QualifyFrequency))
		if route.OutboundProxy != "" {
			content.WriteString("outbound_proxy=" + route.OutboundProxy + "\n")
		}
		if route.AuthenticationMode != "ip" {
			content.WriteString(fmt.Sprintf("\n[%s-auth]\ntype=auth\nauth_type=userpass\nusername=%s\npassword=%s\n", name, route.SIPUsername, route.SIPPassword))
		}
		if route.AuthenticationMode == "registration" {
			clientUser := firstNonEmpty(route.ContactUser, route.SIPUsername)
			content.WriteString(fmt.Sprintf("\n[%s-registration]\ntype=registration\ntransport=transport-%s\noutbound_auth=%s-auth\nserver_uri=%s\nclient_uri=sip:%s@%s\ncontact_user=%s\nendpoint=%s\nline=yes\nexpiration=%d\nretry_interval=30\nmax_retries=10000\n", name, route.Transport, name, uri, clientUser, route.FromDomain, clientUser, name, route.RegistrationExpiration))
			if route.OutboundProxy != "" {
				content.WriteString("outbound_proxy=" + route.OutboundProxy + "\n")
			}
		}
	}
	return content.String(), nil
}

func normalizeRoute(config routeConfig) routeConfig {
	if config.SIPPort == 0 {
		if config.Transport == "tls" {
			config.SIPPort = 5061
		} else {
			config.SIPPort = 5060
		}
	}
	if config.FromDomain == "" {
		config.FromDomain = config.SIPHost
	}
	if config.DTMFMode == "" {
		config.DTMFMode = "rfc4733"
	}
	if config.CallerIDHeader == "" {
		config.CallerIDHeader = "none"
	}
	if config.QualifyFrequency == 0 {
		config.QualifyFrequency = 15
	}
	if config.RegistrationExpiration == 0 {
		config.RegistrationExpiration = 3600
	}
	return config
}

func sipURI(route routeConfig) string {
	transport := ""
	if route.Transport != "udp" {
		transport = ";transport=" + route.Transport
	}
	return fmt.Sprintf("sip:%s:%d%s", route.SIPHost, route.SIPPort, transport)
}

func oneOf(value string, allowed ...string) bool {
	for _, candidate := range allowed {
		if value == candidate {
			return true
		}
	}
	return false
}

func (b *bridge) currentRouteConfigs(extra ...routeConfig) []routeConfig {
	routesByID := map[int]routeConfig{}
	b.routes.Range(func(_, value any) bool {
		config := value.(*routeManager).config
		routesByID[config.RouteID] = config
		return true
	})
	for _, config := range extra {
		routesByID[config.RouteID] = config
	}
	routes := make([]routeConfig, 0, len(routesByID))
	for _, config := range routesByID {
		routes = append(routes, config)
	}
	return routes
}

func (b *bridge) currentAgentConfigs(extra ...agentConfig) []agentConfig {
	agentsByID := map[int]agentConfig{}
	b.agents.Range(func(_, value any) bool {
		config := value.(agentConfig)
		agentsByID[config.UserID] = config
		return true
	})
	for _, config := range extra {
		agentsByID[config.UserID] = config
	}
	agents := make([]agentConfig, 0, len(agentsByID))
	for _, config := range agentsByID {
		agents = append(agents, config)
	}
	return agents
}

func (b *bridge) reloadFromAnyRoute() error {
	if b.managerConfig.Host != "" && b.managerConfig.Username != "" && b.managerConfig.Secret != "" {
		return reloadPJSIP(b.managerConfig)
	}

	var selected *routeConfig
	b.routes.Range(func(_, value any) bool {
		config := value.(*routeManager).config
		selected = &config
		return false
	})
	if selected == nil {
		return errors.New("no Asterisk AMI connection is available for PJSIP reload")
	}
	return reloadPJSIP(*selected)
}

func (b *bridge) expiredAgentLoop() {
	ticker := time.NewTicker(30 * time.Second)
	defer ticker.Stop()
	for range ticker.C {
		b.agentMu.Lock()
		changed := false
		now := time.Now().Unix()
		b.agents.Range(func(key, value any) bool {
			if expiresAt := value.(agentConfig).ExpiresAt; expiresAt > 0 && expiresAt <= now {
				b.agents.Delete(key)
				changed = true
			}
			return true
		})
		if !changed {
			b.agentMu.Unlock()
			continue
		}
		if err := b.provisionCurrentAgents(); err != nil {
			log.Printf("failed to remove expired WebRTC endpoints: %v", err)
			b.agentMu.Unlock()
			continue
		}
		if err := b.reloadFromAnyRoute(); err != nil {
			log.Printf("failed to reload PJSIP after WebRTC endpoint expiry: %v", err)
			b.agentMu.Unlock()
			continue
		}
		if err := b.persistRoutes(); err != nil {
			log.Printf("failed to persist WebRTC endpoint expiry: %v", err)
		}
		b.agentMu.Unlock()
	}
}

func (b *bridge) persistRoutes() error {
	b.persistMu.Lock()
	defer b.persistMu.Unlock()

	if b.statePath == "" || len(b.stateKey) == 0 {
		return nil
	}
	routes := b.currentRouteConfigs()
	sort.Slice(routes, func(i, j int) bool { return routes[i].RouteID < routes[j].RouteID })
	state := persistedState{
		Version: 1,
		Routes:  routes,
		Agents:  b.currentAgentConfigs(),
	}
	body, err := json.Marshal(state)
	if err != nil {
		return err
	}
	body, err = encryptState(b.stateKey, body)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(b.statePath), 0755); err != nil {
		return err
	}
	temporaryPath := b.statePath + ".tmp"
	if err := os.WriteFile(temporaryPath, body, 0600); err != nil {
		return err
	}
	return os.Rename(temporaryPath, b.statePath)
}

func (b *bridge) restoreRoutes() error {
	if b.statePath == "" || len(b.stateKey) == 0 {
		return nil
	}
	body, err := os.ReadFile(b.statePath)
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}
	if err != nil {
		return err
	}
	body, err = decryptState(b.stateKey, body)
	if err != nil {
		return err
	}
	var state persistedState
	if err := json.Unmarshal(body, &state); err != nil || state.Version == 0 {
		var legacyRoutes []routeConfig
		if legacyErr := json.Unmarshal(body, &legacyRoutes); legacyErr != nil {
			return errors.New("encrypted bridge state has an unsupported format")
		}
		state = persistedState{Version: 1, Routes: legacyRoutes}
	}
	for _, originalConfig := range state.Routes {
		config := normalizeRoute(originalConfig)
		if err := validateRoute(config); err != nil {
			return fmt.Errorf("stored route %d: %w", config.RouteID, err)
		}
		manager := newRouteManager(b, config)
		b.routes.Store(config.RouteID, manager)
		manager.start()
	}
	for _, config := range state.Agents {
		if config.ExpiresAt == 0 {
			config.ExpiresAt = time.Now().Add(3 * time.Minute).Unix()
		}
		if err := validateAgent(config); err != nil {
			return fmt.Errorf("stored agent %d: %w", config.UserID, err)
		}
		b.agents.Store(config.UserID, config)
	}
	if err := b.provisionCurrentRoutes(); err != nil {
		return err
	}
	return b.provisionCurrentAgents()
}

func decodeStateKey(value string) ([]byte, error) {
	if value == "" {
		return nil, nil
	}
	key, err := base64.StdEncoding.DecodeString(value)
	if err != nil || len(key) != 32 {
		return nil, errors.New("must be a base64-encoded 32-byte key")
	}
	return key, nil
}

func encryptState(key []byte, plaintext []byte) ([]byte, error) {
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}
	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return nil, err
	}
	return gcm.Seal(nonce, nonce, plaintext, nil), nil
}

func decryptState(key []byte, ciphertext []byte) ([]byte, error) {
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}
	if len(ciphertext) < gcm.NonceSize() {
		return nil, errors.New("encrypted route state is truncated")
	}
	nonce, ciphertext := ciphertext[:gcm.NonceSize()], ciphertext[gcm.NonceSize():]
	return gcm.Open(nil, nonce, ciphertext, nil)
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

func readAMIGreeting(reader *bufio.Reader) error {
	line, err := reader.ReadString('\n')
	if err != nil {
		return err
	}
	if !strings.HasPrefix(strings.TrimSpace(line), "Asterisk Call Manager/") {
		return errors.New("invalid Asterisk AMI greeting")
	}
	return nil
}

func writeAMIAction(conn net.Conn, fields map[string]string) error {
	var action strings.Builder
	for _, key := range []string{"Action", "ActionID", "Channel", "Context", "Exten", "Priority", "Application", "Data", "CallerID", "Async", "Timeout", "Variable", "Username", "Secret", "Events", "Command"} {
		if value := fields[key]; value != "" {
			action.WriteString(key + ": " + value + "\r\n")
		}
	}
	action.WriteString("\r\n")
	_, err := io.WriteString(conn, action.String())
	return err
}

func companyIDFromPath(path string) (int, error) {
	return idFromPath(path, "/v1/routes/")
}

func idFromPath(path string, prefix string) (int, error) {
	if !strings.HasPrefix(path, prefix) {
		return 0, errors.New("invalid path")
	}
	value := strings.Trim(strings.TrimPrefix(path, prefix), "/")
	id, err := strconv.Atoi(value)
	if err != nil || id < 1 {
		return 0, errors.New("invalid id")
	}
	return id, nil
}

func decodeJSON(r *http.Request, value any) error {
	decoder := json.NewDecoder(io.LimitReader(r.Body, 64<<10))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(value); err != nil {
		return err
	}
	if err := decoder.Decode(&struct{}{}); !errors.Is(err, io.EOF) {
		return errors.New("request body must contain exactly one JSON object")
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

func envInt(key string, fallback int) int {
	value, err := strconv.Atoi(os.Getenv(key))
	if err != nil || value < 1 {
		return fallback
	}
	return value
}
