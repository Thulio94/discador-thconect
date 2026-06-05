# Documentação de Rotas e API - Diamond Dialer

> Referência completa de endpoints HTTP

---

## Visão Geral

O sistema utiliza um Router customizado que suporta:
- Grupos de rotas com middleware
- Verbos HTTP: GET, POST, PUT, DELETE
- Parâmetros dinâmicos: `/users/{id}`
- Proteção por roles: `admin`, `supervisor`, `agent`

---

## Rotas Públicas

Endpoints acessíveis sem autenticação:

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/` | `AuthController@showLogin` | Página de login |
| GET | `/login` | `AuthController@showLogin` | Página de login |
| POST | `/login` | `AuthController@login` | Processa login |
| GET | `/logout` | `AuthController@logout` | Logout |
| GET | `/forgot-password` | `AuthController@showForgotPassword` | Recuperação de senha |
| POST | `/forgot-password` | `AuthController@forgotPassword` | Processa recuperação |

---

## Rotas Protegidas

### Dashboard

| Método | Endpoint | Controller | Roles |
|--------|----------|------------|-------|
| GET | `/dashboard` | `DashboardController@index` | Todos autenticados |

### Perfil

| Método | Endpoint | Controller | Roles |
|--------|----------|------------|-------|
| GET | `/profile` | `ProfileController@index` | Todos autenticados |
| POST | `/profile` | `ProfileController@update` | Todos autenticados |

---

## Gestão de Usuários

**Roles permitidas**: `admin`, `supervisor`

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/users` | `UserController@index` | Lista usuários |
| GET | `/users/create` | `UserController@create` | Formulário de criação |
| POST | `/users` | `UserController@store` | Cria usuário |
| GET | `/users/{id}` | `UserController@show` | Detalhes do usuário |
| GET | `/users/{id}/edit` | `UserController@edit` | Formulário de edição |
| PUT | `/users/{id}` | `UserController@update` | Atualiza usuário |
| DELETE | `/users/{id}` | `UserController@destroy` | Inativa usuário |

---

## Gestão de Equipes

**Roles permitidas**: `admin`, `supervisor`

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/teams` | `TeamController@index` | Lista equipes |
| GET | `/teams/create` | `TeamController@create` | Formulário de criação |
| POST | `/teams` | `TeamController@store` | Cria equipe |
| GET | `/teams/{id}` | `TeamController@show` | Detalhes da equipe |
| GET | `/teams/{id}/edit` | `TeamController@edit` | Formulário de edição |
| PUT | `/teams/{id}` | `TeamController@update` | Atualiza equipe |
| DELETE | `/teams/{id}` | `TeamController@destroy` | Exclui equipe |
| POST | `/teams/{id}/members` | `TeamController@addMember` | Adiciona membro |
| DELETE | `/teams/{id}/members/{userId}` | `TeamController@removeMember` | Remove membro |

---

## Gestão de Campanhas

**Roles permitidas**: `admin`, `supervisor`

### CRUD Básico

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/campaigns` | `CampaignController@index` | Lista campanhas |
| GET | `/campaigns/{id}` | `CampaignController@show` | Detalhes |
| GET | `/campaigns/{id}/edit` | `CampaignController@edit` | Formulário de edição |
| PUT | `/campaigns/{id}` | `CampaignController@update` | Atualiza |
| DELETE | `/campaigns/{id}` | `CampaignController@destroy` | Exclui |

### Wizard de Criação (4 Steps)

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/campaigns/create` | `CampaignController@create` | Step 1 - Info básica |
| POST | `/campaigns/create/step1` | `CampaignController@step1` | Processa Step 1 |
| GET | `/campaigns/create/step2` | `CampaignController@showStep2` | Step 2 - Modo de discagem |
| POST | `/campaigns/create/step2` | `CampaignController@step2` | Processa Step 2 (Tipo, AMD, Prazos) |
| GET | `/campaigns/create/step3` | `CampaignController@showStep3` | Step 3 - Agendamento |
| POST | `/campaigns/create/step3` | `CampaignController@step3` | Processa Step 3 |
| GET | `/campaigns/create/step4` | `CampaignController@showStep4` | Step 4 - Revisão |
| POST | `/campaigns` | `CampaignController@store` | Finaliza criação |

### Ações de Campanha

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| POST | `/campaigns/{id}/start` | `CampaignController@start` | Inicia campanha |
| POST | `/campaigns/{id}/pause` | `CampaignController@pause` | Pausa campanha |
| POST | `/campaigns/{id}/restart` | `CampaignController@restart` | Reinicia campanha |

---

### Parâmetros de Campanha (AMD)

Ao criar ou atualizar (`PUT /campaigns/{id}` ou `POST /campaigns/create/step2`), os seguintes campos de AMD são aceitos:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `amd_enabled` | `boolean` | `1` ou `0` para ativar a detecção |
| `amd_timeout` | `integer` | Tempo de análise em ms (1000 - 5000) |
| `amd_min_word_length`| `integer` | Duração mínima da voz em ms (50 - 500) |
| `amd_max_words` | `integer` | Máximo de palavras curtas (1 - 5) |

---

## Importação de Contatos

**Roles permitidas**: `admin`, `supervisor`

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/campaigns/{id}/import` | `ImportController@show` | Interface de importação |
| POST | `/campaigns/{id}/import` | `ImportController@upload` | Upload de arquivo |
| GET | `/campaigns/{id}/import/{listId}/mapping` | `ImportController@showMapping` | Mapeamento de colunas |
| POST | `/campaigns/{id}/import/{listId}/mapping` | `ImportController@processMapping` | Processa mapeamento |
| PUT | `/campaigns/{id}/import/{listId}/weight` | `ImportController@updateWeight` | Atualiza peso da lista |
| DELETE | `/campaigns/{id}/import/{listId}` | `ImportController@delete` | Exclui lista |

---

## Painel do Agente

**Roles permitidas**: Todos autenticados

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/agent` | `AgentPanelController@index` | Interface do agente |
| POST | `/agent/status` | `AgentPanelController@updateStatus` | Atualiza status |
| POST | `/agent/disposition` | `AgentPanelController@saveDisposition` | Salva tabulação |
| POST | `/agent/request-next` | `AgentPanelController@requestNext` | Solicita próximo contato |
| POST | `/agent/start-call` | `AgentPanelController@startCall` | Inicia chamada |
| POST | `/agent/answer-call` | `AgentPanelController@answerCall` | Atende chamada |
| POST | `/agent/end-call` | `AgentPanelController@endCall` | Encerra chamada |

---

## Supervisão

**Roles permitidas**: `admin`, `supervisor`

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/supervisor` | `SupervisorController@index` | Dashboard de supervisão |
| GET | `/supervisor/agents` | `SupervisorController@agents` | Lista agentes |
| POST | `/supervisor/spy/{call_id}` | `SupervisorController@spy` | Ouvir chamada (spy) |
| POST | `/supervisor/whisper/{call_id}` | `SupervisorController@whisper` | Sussurrar ao agente |
| POST | `/supervisor/barge/{call_id}` | `SupervisorController@barge` | Entrar na chamada |
| POST | `/supervisor/force-logout` | `SupervisorController@forceLogout` | Forçar logout de agente |

---

## Relatórios

**Roles permitidas**: `admin`, `supervisor`

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/reports` | `ReportController@index` | Dashboard de relatórios |
| GET | `/reports/calls` | `ReportController@calls` | Relatório de chamadas |
| GET | `/reports/agents` | `ReportController@agents` | Relatório de agentes |
| GET | `/reports/campaigns` | `ReportController@campaigns` | Relatório de campanhas |
| GET | `/reports/export` | `ReportController@export` | Exportar dados |

---

## Configurações (Admin Only)

**Role permitida**: `admin`

### Configurações Gerais

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/settings` | `AdminController@settings` | Configurações |
| POST | `/settings` | `AdminController@updateSettings` | Atualiza configurações |
| GET | `/settings/pauses` | `AdminController@pauseReasons` | Motivos de pausa |
| POST | `/settings/pauses` | `AdminController@savePauseReasons` | Salva motivos |
| GET | `/settings/audit` | `AdminController@auditLogs` | Logs de auditoria |

### Troncos SIP

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/settings/trunks` | `TrunkController@index` | Lista troncos |
| GET | `/settings/trunks/create` | `TrunkController@create` | Criar tronco |
| POST | `/settings/trunks` | `TrunkController@store` | Salva tronco |
| GET | `/settings/trunks/{id}/edit` | `TrunkController@edit` | Editar tronco |
| PUT | `/settings/trunks/{id}` | `TrunkController@update` | Atualiza tronco |
| DELETE | `/settings/trunks/{id}` | `TrunkController@destroy` | Remove tronco |

### Rotas de Discagem

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/settings/routes` | `RouteController@index` | Lista rotas |
| GET | `/settings/routes/create` | `RouteController@create` | Criar rota |
| POST | `/settings/routes` | `RouteController@store` | Salva rota |
| GET | `/settings/routes/{id}/edit` | `RouteController@edit` | Editar rota |
| PUT | `/settings/routes/{id}` | `RouteController@update` | Atualiza rota |
| DELETE | `/settings/routes/{id}` | `RouteController@destroy` | Remove rota |
| POST | `/settings/routes/{id}/toggle` | `RouteController@toggle` | Ativa/Desativa |

### Tabulações (Dispositions)

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/settings/dispositions` | `DispositionController@index` | Lista tabulações |
| GET | `/settings/dispositions/create` | `DispositionController@create` | Criar |
| POST | `/settings/dispositions` | `DispositionController@store` | Salva |
| POST | `/settings/dispositions/duplicate` | `DispositionController@duplicate` | Duplica |
| GET | `/settings/dispositions/{id}/edit` | `DispositionController@edit` | Editar |
| PUT | `/settings/dispositions/{id}` | `DispositionController@update` | Atualiza |
| DELETE | `/settings/dispositions/{id}` | `DispositionController@destroy` | Remove |

### Blacklist (DNC)

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/settings/blacklist` | `BlacklistController@index` | Lista bloqueados |
| POST | `/settings/blacklist` | `BlacklistController@store` | Adiciona número |
| DELETE | `/settings/blacklist/{id}` | `BlacklistController@destroy` | Remove número |
| POST | `/settings/blacklist/import` | `BlacklistController@import` | Importa lista |
| POST | `/settings/blacklist/check` | `BlacklistController@check` | Verifica número |

### Webhooks

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/settings/webhooks` | `WebhookController@index` | Lista webhooks |
| GET | `/settings/webhooks/create` | `WebhookController@create` | Criar |
| POST | `/settings/webhooks` | `WebhookController@store` | Salva |
| GET | `/settings/webhooks/{id}/edit` | `WebhookController@edit` | Editar |
| GET | `/settings/webhooks/{id}/logs` | `WebhookController@logs` | Ver logs |
| PUT | `/settings/webhooks/{id}` | `WebhookController@update` | Atualiza |
| DELETE | `/settings/webhooks/{id}` | `WebhookController@destroy` | Remove |
| POST | `/settings/webhooks/{id}/test` | `WebhookController@test` | Testar |

### Multi-Tenant

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/tenants` | `TenantController@index` | Lista tenants |
| GET | `/tenants/create` | `TenantController@create` | Criar tenant |
| POST | `/tenants` | `TenantController@store` | Salva tenant |
| GET | `/tenants/{id}/edit` | `TenantController@edit` | Editar |
| PUT | `/tenants/{id}` | `TenantController@update` | Atualiza |
| DELETE | `/tenants/{id}` | `TenantController@destroy` | Remove |

---

## Rotas de API (JSON)

**Prefixo**: `/api`  
**Middleware**: `api`

| Método | Endpoint | Controller | Descrição |
|--------|----------|------------|-----------|
| GET | `/api/dashboard/stats` | `Api\DashboardController@stats` | Estatísticas do dashboard |
| GET | `/api/dashboard/calls` | `Api\DashboardController@recentCalls` | Chamadas recentes |
| GET | `/api/agent/status` | `Api\AgentController@status` | Status do agente |
| GET | `/api/agent/call` | `Api\AgentController@currentCall` | Chamada atual |
| GET | `/api/supervisor/agents` | `Api\SupervisorController@agentsList` | Lista de agentes em tempo real |

---

## Códigos de Resposta HTTP

| Código | Significado |
|--------|-------------|
| `200` | Sucesso |
| `201` | Criado com sucesso |
| `302` | Redirect após ação |
| `400` | Bad Request - Dados inválidos |
| `401` | Não autenticado |
| `403` | Não autorizado (role) |
| `404` | Recurso não encontrado |
| `500` | Erro interno |
