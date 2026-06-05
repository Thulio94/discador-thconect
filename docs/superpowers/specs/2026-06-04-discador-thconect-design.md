# Discador THCONECT Design

## Objetivo

Criar o Discador THCONECT como um produto SaaS independente em Laravel, dentro da pasta `DISCADOR THCONECT`, usando a mesma base de design system do CRMTELECOM ja existente, mas com banco, autenticacao, configuracoes, servidores e contratacao separados.

O sistema deve gerenciar empresas contratantes, licencas, usuarios, equipes, campanhas de discagem, mailing, painel do agente, supervisao, relatorios e telefonia. A integracao com o CRMTELECOM deve ficar preparada para o futuro, mas marcada como modulo "Em breve" no MVP.

## Decisoes Aprovadas

- O projeto sera um novo Laravel separado, nao um modulo dentro do CRM existente.
- Todo arquivo novo do sistema deve ficar dentro de `C:\Users\Thulio\Documents\TRABALHO\PROJETOS\CRM_TELECOM\DISCADOR THCONECT`.
- A base visual deve reaproveitar os tokens e padroes do `crm-thconect`: Blade, Tailwind, Alpine, tema claro/escuro, sidebar, cards, acoes e componentes THCONECT.
- O ambiente de desenvolvimento deve rodar via Docker Desktop, usando portas configuraveis e verificadas antes de subir para evitar conflito com outros projetos.
- Integracoes externas/webhooks para CRM ficam como modulo "Em breve".
- Nada de `javascript.alert`, `confirm` nativo ou popups padrao do navegador. Notificacoes e confirmacoes devem usar componentes premium do sistema.
- CRUD deve ser modal-first sempre que possivel, evitando troca de pagina para criar, editar, visualizar e excluir.

## Arquitetura

O Laravel sera o painel SaaS e centro das regras de negocio. Ele controla tenants, licencas, RBAC, campanhas, listas, agentes, supervisao, relatorios, notificacoes e configuracoes de telefonia.

A telefonia ficara atras de contratos internos, com uma interface como `DialerGateway`. No MVP, a implementacao sera simulada para permitir desenvolver e validar campanhas, painel do agente, tabulacao, supervisao e relatorios sem depender do Asterisk.

Implementacoes previstas:

- `SimulatedDialerGateway`: chamadas simuladas para MVP e testes.
- `AsteriskAmiGateway`: comandos AMI como originate, hangup, redirect, spy, whisper e barge.
- `GoBridgeDialerGateway`: integracao futura com o servico Go, Redis e Asterisk para alta escala.

Fluxo futuro de escala:

```text
Laravel/MySQL <-> Redis <-> Go Dialer <-> Asterisk <-> Rota VOIP
```

Redis sera usado para cache, filas, estado quente da operacao e base futura para pacing. MySQL/MariaDB sera usado para dados transacionais e historico. Laravel Reverb/Echo sera usado para notificacoes e atualizacoes em tempo real, com fallback por polling leve quando necessario.

## Modulos

### Plataforma SaaS

Super admin gerencia empresas, status de licenca, limites contratados e acesso de suporte. Cada empresa opera isolada por `company_id`.

### Licencas e Planos

O super admin pode configurar livremente os limites de cada empresa:

- `max_users`: limite de usuarios cadastrados ou contratados.
- `max_concurrent_agents`: limite opcional de agentes simultaneos.
- `max_calls_per_available_agent`: quantidade maxima de chamadas simultaneas por operador em status disponivel.
- `max_concurrent_calls`: limite absoluto opcional de chamadas simultaneas da empresa.
- flags por recurso, como `predictive_enabled`, `power_enabled`, `preview_enabled` e `amd_enabled`.

Regra de pacing:

```text
limite_por_agentes = agentes_disponiveis * max_calls_per_available_agent
limite_empresa = max_concurrent_calls ou infinito
limite_campanha = agressividade/modo da campanha

chamadas_permitidas = menor(limite_por_agentes, limite_empresa, limite_campanha)
```

Somente agentes disponiveis vinculados a equipes da campanha contam na capacidade. Supervisores e administradores podem ter ramal e fazer chamadas manuais, mas nao contam como capacidade de campanha.

### Usuarios, Ramais e Equipes

Tipos de usuario:

- `agent`
- `supervisor`
- `company_admin`
- `super_admin`

Todos os usuarios devem ter ramal unico. Isso inclui agentes, supervisores, administradores da empresa e super admins, pois o ramal e usado para comunicacao entre ramais e chamadas manuais.

Agentes e supervisores podem participar de varias equipes. Apenas usuarios `agent` entram em campanhas como operadores. Supervisores podem acompanhar varias equipes e campanhas.

Relacionamentos:

```text
users
  user_type
  extension

teams
  company_id
  name

team_user
  team_id
  user_id
  membership_type: agent | supervisor

campaign_team
  campaign_id
  team_id
```

Se o mesmo agente estiver em mais de uma equipe associada a mesma campanha, ele conta uma unica vez no pacing.

### Campanhas

Campanhas terao CRUD e wizard com etapas:

1. Informacoes basicas.
2. Modo de discagem: power, predictive ou preview.
3. AMD e regras de tentativas/cooldown.
4. Rotas de telefonia.
5. Agenda operacional.
6. Revisao.

Acoes: iniciar, pausar, reiniciar/reciclar mailing, duplicar, editar e acompanhar progresso.

Uma campanha pode ter varias equipes e varias listas de mailing.

### Mailing e Importacao

Suporte a CSV/TXT com upload, pre-visualizacao, mapeamento de colunas, sanitizacao de telefones, deduplicacao, validacao, blacklist e peso por lista.

Contatos importados entram em uma fila de trabalho com status como pendente, discando, atendido, improdutivo, callback e esgotado.

### Painel do Agente

Tela operacional compacta com:

- status do agente;
- webphone ou simulador no MVP;
- chamada atual;
- ficha do contato;
- script/formulario;
- tabulacao obrigatoria;
- agendamento de retorno publico ou privado;
- controles de chamada simulados no MVP e reais depois.

O agente nao pode receber nova chamada antes de finalizar a tabulacao obrigatoria da chamada anterior.

### Supervisao

Grid em tempo real de agentes, status atual, tempo no status, chamadas ativas e campanhas. Acoes futuras: ouvir, sussurrar e intervir, sempre passando pelo gateway de telefonia.

### Relatorios

Relatorios essenciais:

- analitico de chamadas;
- performance de agente;
- performance de campanha;
- performance de mailing;
- exportacao CSV/XLSX/PDF.

### Telefonia

Configuracoes incluem troncos SIP, rotas de discagem, ramais, parametros AMD e futuramente WebRTC com SIP.js ou JsSIP.

No MVP, a telefonia real fica atras do simulador. Depois entram AMI/Asterisk e, em escala, Go Bridge com Redis.

### Configuracoes

Inclui pausas, tabulacoes, blacklist, parametros gerais, AMD e regras operacionais.

### Integracoes

Modulo marcado como "Em breve". Nao sera implementado no MVP ativo. A arquitetura deve deixar eventos internos e pontos de extensao preparados para webhook/API futura com CRMTELECOM e sistemas externos.

## Modelo de Dados

Tabelas principais:

- `companies`
- `company_licenses`
- `users`
- `roles`
- `permissions`
- `role_permissions`
- `teams`
- `team_user`
- `campaigns`
- `campaign_team`
- `campaign_schedules`
- `sip_trunks`
- `dialer_routes`
- `mailing_lists`
- `contacts`
- `contact_phones`
- `call_attempts`
- `calls`
- `agent_statuses`
- `agent_pauses`
- `dispositions`
- `call_dispositions`
- `callbacks`
- `blacklist_numbers`
- `audit_logs`
- `report_exports`
- `app_notifications`

Tabelas de integracao como `integration_endpoints` e `webhook_logs` ficam para o modulo futuro.

Todas as tabelas operacionais de tenant devem usar `company_id` para isolamento.

## UI e Design System

O projeto deve copiar/adaptar a base visual do CRM atual:

- `resources/css/thconect-tokens.css`
- `resources/css/thconect-components.css`
- Blade components de UI
- Tailwind
- Alpine
- Livewire/Reverb quando fizer sentido

Navegacao principal:

- Dashboard
- Campanhas
- Agente
- Supervisao
- Relatorios
- Cadastros
- Telefonia
- Configuracoes
- Integracoes, marcado como "Em breve"

O painel do agente deve ser compacto e operacional. Dashboard e supervisao devem priorizar leitura rapida e densidade informacional.

### Notificacoes Premium

O sistema deve usar:

- toasts/slide-ins no canto direito;
- painel lateral direito de notificacoes;
- contador de nao lidas;
- notificacoes persistidas em `app_notifications`;
- eventos em tempo real via Reverb/Echo;
- fallback por polling.

Nao usar `javascript.alert`, `window.confirm` ou popups nativos.

### CRUD Modal-First

Criar, editar, visualizar resumo, excluir/inativar, duplicar e confirmar acoes devem abrir modal sempre que possivel.

Paginas dedicadas sao aceitaveis apenas para fluxos grandes:

- wizard de campanha;
- importacao/mapeamento de mailing;
- painel do agente;
- supervisao em tempo real;
- relatorios grandes;
- detalhe completo de campanha;
- configuracao avancada de telefonia/AMD.

## Ambiente Docker

O ambiente de desenvolvimento deve usar Docker Desktop com `docker compose`.

Servicos previstos:

- `app`: PHP/Laravel.
- `nginx` ou servidor HTTP equivalente.
- `mysql` ou `mariadb`.
- `redis`.
- `reverb`.
- `queue`.

Portas devem ser parametrizadas no `.env` e verificadas antes de subir para evitar conflito com outros projetos.

Exemplo:

```env
APP_PORT=
VITE_PORT=
MYSQL_FORWARD_PORT=
REDIS_FORWARD_PORT=
REVERB_PORT=
```

## Fases de Entrega

### Fase 1: Fundacao SaaS Laravel

Novo projeto Laravel separado, login, tema THCONECT, layout com sidebar esquerda e painel de notificacoes a direita, empresas/licencas, usuarios com ramal, perfis, permissoes e equipes flexiveis. CRUD modal-first.

### Fase 2: Core Operacional Simulado

Campanhas, wizard, equipes por campanha, multiplas listas, importacao CSV/TXT, sanitizacao, deduplicacao, blacklist, tabulacoes, pausas, status de agente e painel do agente com chamada simulada.

### Fase 3: Supervisao e Relatorios

Dashboard em tempo real, grid de agentes, chamadas ativas, relatorios de chamadas/agentes/campanhas/mailing e exportacoes. Notificacoes operacionais via Reverb/Echo ou fallback polling.

### Fase 4: Telefonia Real

Troncos SIP, rotas, AMI/Asterisk, originate/hangup/transfer, WebRTC com SIP.js/JsSIP, AMD e monitoria.

### Fase 5: Go Bridge e Escala

Servico Go com Redis para pacing, filas quentes, originate massivo e event loop AMI.

### Fase 6: Integracoes Em Breve

Webhooks/API para CRMTELECOM e sistemas externos, com logs, retry e assinatura.

## Testes e Verificacao

Testes automatizados devem cobrir:

- isolamento multi-tenant;
- RBAC por tipo/permissao;
- licenca e limite `max_calls_per_available_agent`;
- campanhas e pacing simulado;
- importacao, sanitizacao e deduplicacao;
- status de agente;
- tabulacao obrigatoria;
- notificacoes persistidas;
- requests/controllers dos CRUDs modal-backed.

Verificacao manual deve subir o Docker, abrir a URL local e validar login, dashboard, usuarios, equipes, campanhas, importacao, agente, supervisao, relatorios e notificacoes premium.
