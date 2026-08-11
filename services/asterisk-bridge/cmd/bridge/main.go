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
	"path/filepath"
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
	FromDomain                string `json:"from_domain"`
	SIPUsername               string `json:"sip_username"`
	SIPPassword               string `json:"sip_password"`
	AuthenticationMode        string `json:"authentication_mode"`
	Transport                 string `json:"transport"`
	Codecs                    string `json:"codecs"`
}

type originateRequest struct {
	CompanyID     int    `json:"company_id"`
	CallID        int    `json:"call_id"`
	ActionID      string `json:"action_id"`
	Phone         string `json:"phone"`
	Channel       string `json:"channel"`
	Context       string `json:"context"`
	Extension     string `json:"extension"`
	CallerID      string `json:"caller_id"`
	RecordingFile string `json:"recording_file"`
}

type hangupRequest struct {
	CompanyID int    `json:"company_id"`
	CallID    int    `json:"call_id"`
	Channel   string `json:"channel"`
	UniqueID  string `json:"uniqueid"`
}

type superviseRequest struct {
	CompanyID           int    `json:"company_id"`
	CallID              int    `json:"call_id"`
	AgentChannel        string `json:"agent_channel"`
	AgentExtension      string `json:"agent_extension"`
	SupervisorExtension string `json:"supervisor_extension"`
	Mode                string `json:"mode"`
	Persistent          bool   `json:"persistent"`
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
	provisioned   sync.Map
	configPath    string
}

type routeManager struct {
	bridge     *bridge
	config     routeConfig
	stop       chan struct{}
	done       chan struct{}
	mu         sync.Mutex
	conn       net.Conn
	actions    map[string]actionRef
	ids        map[string]int
	recordings map[int]string
}

func main() {
	b := &bridge{
		token:         os.Getenv("BRIDGE_SHARED_TOKEN"),
		webhookURL:    os.Getenv("BRIDGE_WEBHOOK_URL"),
		webhookSecret: os.Getenv("BRIDGE_WEBHOOK_SECRET"),
		client:        &http.Client{Timeout: 8 * time.Second},
		configPath:    os.Getenv("ASTERISK_CONFIG_PATH"),
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
	mux.HandleFunc("/v1/calls/supervise", b.authorized(b.supervise))

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
	if err := b.provisionRoutes([]routeConfig{config}); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
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
		if err := b.provisionCurrentRoutes(); err != nil {
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
	if err := validateRoute(config); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	b.provisioned.Store(config.RouteID, config)

	manager := newRouteManager(b, config)
	if current, loaded := b.routes.Swap(companyID, manager); loaded {
		current.(*routeManager).close()
	}
	if err := b.provisionCurrentRoutes(); err != nil {
		writeJSON(w, http.StatusBadGateway, map[string]string{"message": err.Error()})
		return
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

func (b *bridge) supervise(w http.ResponseWriter, r *http.Request) {
	var request superviseRequest
	if err := decodeJSON(r, &request); err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": err.Error()})
		return
	}
	if request.CompanyID < 1 || request.SupervisorExtension == "" || (request.AgentChannel == "" && request.AgentExtension == "") {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "company_id, supervisor_extension and an agent channel or extension are required"})
		return
	}
	if request.Mode != "listen" && request.Mode != "whisper" && request.Mode != "barge" {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]string{"message": "mode must be listen, whisper or barge"})
		return
	}
	current, ok := b.routes.Load(request.CompanyID)
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
	return &routeManager{bridge: b, config: config, stop: make(chan struct{}), done: make(chan struct{}), actions: map[string]actionRef{}, ids: map[string]int{}, recordings: map[int]string{}}
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
	if err := writeAMIAction(conn, map[string]string{"Action": "Command", "Command": "pjsip reload"}); err != nil {
		return err
	}
	if _, err := readAMIMessage(reader); err != nil {
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
	m.actions[request.ActionID] = actionRef{CallID: request.CallID}
	m.recordings[request.CallID] = request.RecordingFile
	m.mu.Unlock()

	return m.sendAction(map[string]string{
		"Action": "Originate", "ActionID": request.ActionID, "Channel": request.Channel,
		"Context": request.Context, "Exten": request.Extension, "Priority": "1",
		"CallerID": request.CallerID, "Async": "true", "Timeout": "45000",
		"Variable": "THCONECT_CALL_ID=" + strconv.Itoa(request.CallID),
	})
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
		if err != nil || extension < 1000 || extension > 10000 {
			return "", errors.New("agent extension must be numeric and between 1000 and 10000")
		}

		return "PJSIP/" + request.AgentExtension, nil
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
	if event == "Hangup" {
		if recording, ok := m.recordingFor(callID); ok {
			go m.bridge.sendWebhook(map[string]any{
				"company_id":       m.config.CompanyID,
				"call_id":          callID,
				"event":            "recording_ready",
				"recording_path":   "recordings/" + filepath.Base(recording.file),
				"recording_format": strings.TrimPrefix(filepath.Ext(recording.file), "."),
				"recording_size":   recording.size,
			})
		}
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
	if err != nil || info.Size() == 0 {
		return recordingInfo{}, false
	}
	return recordingInfo{file: file, size: info.Size()}, true
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
	if config.CompanyID < 1 || config.RouteID < 1 || config.Host == "" || config.Port < 1 || config.Port > 65535 || config.Username == "" || config.Secret == "" || config.SIPHost == "" {
		return errors.New("company_id, route_id, AMI and SIP host settings are required")
	}
	if config.AuthenticationMode != "ip" && config.AuthenticationMode != "registration" {
		return errors.New("authentication_mode must be ip or registration")
	}
	if config.AuthenticationMode == "registration" && (config.SIPUsername == "" || config.SIPPassword == "") {
		return errors.New("SIP username and password are required for registration")
	}
	if config.Transport != "udp" && config.Transport != "tcp" {
		return errors.New("transport must be udp or tcp")
	}
	for _, value := range []string{config.SIPHost, config.FromDomain, config.SIPUsername, config.SIPPassword, config.CallerID} {
		if strings.ContainsAny(value, "\r\n") {
			return errors.New("route settings cannot contain line breaks")
		}
	}
	return nil
}

func (b *bridge) provisionCurrentRoutes() error {
	routesByID := map[int]routeConfig{}
	b.provisioned.Range(func(_, value any) bool {
		config := value.(routeConfig)
		routesByID[config.RouteID] = config
		return true
	})
	routes := make([]routeConfig, 0, len(routesByID))
	for _, config := range routesByID {
		routes = append(routes, config)
	}
	return b.provisionRoutes(routes)
}

func (b *bridge) provisionRoutes(routes []routeConfig) error {
	if b.configPath == "" {
		return errors.New("ASTERISK_CONFIG_PATH is not configured")
	}
	sort.Slice(routes, func(i, j int) bool { return routes[i].RouteID < routes[j].RouteID })
	var content strings.Builder
	content.WriteString("; Generated by THCONECT Asterisk Bridge. Do not edit manually.\n")
	for _, route := range routes {
		name := fmt.Sprintf("thconect-route-%d", route.RouteID)
		content.WriteString(fmt.Sprintf("\n[%s]\ntype=endpoint\ntransport=transport-%s\ncontext=%s\ndisallow=all\n", name, route.Transport, route.Context))
		for _, codec := range strings.Split(route.Codecs, ",") {
			codec = strings.TrimSpace(codec)
			if codec != "" {
				content.WriteString("allow=" + codec + "\n")
			}
		}
		content.WriteString(fmt.Sprintf("aors=%s\nfrom_domain=%s\ndirect_media=no\nrtp_symmetric=yes\nforce_rport=yes\nrewrite_contact=yes\n", name, route.FromDomain))
		if route.AuthenticationMode == "registration" {
			content.WriteString(fmt.Sprintf("outbound_auth=%s-auth\nfrom_user=%s\n", name, route.SIPUsername))
		}
		content.WriteString(fmt.Sprintf("\n[%s]\ntype=aor\ncontact=sip:%s\nqualify_frequency=30\n", name, route.SIPHost))
		if route.AuthenticationMode == "registration" {
			content.WriteString(fmt.Sprintf("\n[%s-auth]\ntype=auth\nauth_type=userpass\nusername=%s\npassword=%s\n", name, route.SIPUsername, route.SIPPassword))
			content.WriteString(fmt.Sprintf("\n[%s-registration]\ntype=registration\noutbound_auth=%s-auth\nserver_uri=sip:%s\nclient_uri=sip:%s@%s\nretry_interval=60\n", name, name, route.SIPHost, route.SIPUsername, route.FromDomain))
		}
	}
	if err := os.MkdirAll(filepath.Dir(b.configPath), 0755); err != nil {
		return err
	}
	temporaryPath := b.configPath + ".tmp"
	if err := os.WriteFile(temporaryPath, []byte(content.String()), 0600); err != nil {
		return err
	}
	return os.Rename(temporaryPath, b.configPath)
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
