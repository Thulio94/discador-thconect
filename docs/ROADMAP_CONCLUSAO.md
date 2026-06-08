# Roadmap de Conclusao - Discador THCONECT

Atualizado em: 2026-06-08

Este documento consolida o que falta para concluir o Discador THCONECT conforme o planejamento aprovado. Ele separa o MVP simulado, que permite operar e validar o fluxo sem Asterisk, das fases futuras de telefonia real, Go Bridge e integracoes.

## Estado Geral

O projeto ja possui a fundacao SaaS, design THCONECT, Docker, autenticacao, empresas, licencas, usuarios com ramal, equipes, perfis, campanhas/listas basicas, painel operacional simulado, configuracoes operacionais, supervisao simulada e relatorios com CSV.

Ainda falta completar o fluxo operacional ponta a ponta:

```text
Campanha configurada
  -> mailing com fila e tentativas
  -> discagem simulada/pacing
  -> agente recebe contato
  -> tabulacao e callback
  -> supervisao acompanha
  -> relatorios consolidados
```

## Modulos Concluidos ou Bem Encaminhados

### Fundacao SaaS

Status: praticamente concluido para MVP.

- [x] Projeto Laravel separado.
- [x] Docker Desktop com app em `localhost:8087`.
- [x] MariaDB, Redis, Queue e Reverb configurados.
- [x] Login e shell visual THCONECT.
- [x] Remocao de vestigios visuais do Laravel na autenticacao.
- [x] Tema claro/escuro e design system base.
- [x] Sidebar, cards, modais e toasts.
- [x] Sem `javascript.alert` e sem `window.confirm` no app.
- [x] CRUD modal-first nos modulos principais.

### Plataforma SaaS e Super Admin

Status: MVP operacional.

- [x] Empresas.
- [x] Licencas.
- [x] Limite de usuarios.
- [x] Limite de chamadas por agente disponivel.
- [x] Limite absoluto de chamadas simultaneas.
- [x] Flags de recursos como power, preview, predictive e AMD.
- [x] Inativacao de empresa em vez de exclusao fisica.

Pendencias recomendadas:

- [ ] Tela de historico de alteracoes da licenca.
- [ ] Auditoria de alteracoes feitas pelo super admin.
- [ ] Indicadores de uso contratado vs uso atual.

### Usuarios, Ramais, Equipes e Perfis

Status: MVP operacional.

- [x] Usuarios com ramal obrigatorio.
- [x] Tipos: agente, supervisor, administrador da empresa e super admin.
- [x] Ramal unico por empresa.
- [x] Agentes e supervisores em varias equipes.
- [x] Apenas agentes contam para campanhas/capacidade.
- [x] Perfis e permissoes.

Pendencias recomendadas:

- [ ] Melhorar matriz visual de permissoes por modulo.
- [ ] Auditoria de login/alteracoes de usuario.
- [ ] Filtro e busca nos cadastros grandes.

### Configuracoes Operacionais

Status: MVP operacional.

- [x] Motivos de pausa.
- [x] Tabulacoes.
- [x] Regras de tentativas por tabulacao.
- [x] Blacklist.
- [x] Isolamento por empresa.
- [x] Telefonia, AMD avancado e Integracoes marcados como "Em breve".

Pendencias recomendadas:

- [ ] Tempo minimo/maximo de pausa.
- [ ] Parametros gerais da operacao.

### Painel Operacional

Status: MVP simulado funcional.

- [x] Status do usuario.
- [x] Pausa.
- [x] Chamada ativa simulada.
- [x] Chamada manual simulada por ramal.
- [x] Tabulacao obrigatoria para encerrar chamada.
- [x] Supervisores e administradores podem fazer chamada manual sem contar como agente disponivel.
- [x] Agentes voltam para disponivel apos encerrar chamada.
- [x] Ficha do contato atual.
- [x] Campanha e lista atual no painel do agente.
- [x] Historico recente do contato.
- [x] Script de atendimento.
- [x] Anotacoes do atendimento persistidas na tabulacao.
- [x] Callback publico/privado com data e hora.
- [x] Campos basicos do atendimento persistidos na chamada.
- [x] Campos dinamicos configuraveis por campanha.
- [x] Status de contato atualizado pela tabulacao.
- [x] Historico geral de chamadas recentes no painel.

Pendencias importantes:

- [ ] Validacoes avancadas por mascara/tipo de campo.
- [x] Bloquear nova chamada automatica enquanto houver chamada sem tabulacao.

### Campanhas e Listas

Status: basico funcional, ainda nao completo.

- [x] CRUD de campanhas.
- [x] Campanha com varias equipes.
- [x] Campanha com varias listas.
- [x] CRUD de listas.
- [x] Importacao simples de contatos.
- [x] Sanitizacao de telefones.
- [x] Deduplicacao simples.
- [x] Blacklist aplicada na importacao.
- [x] Acoes de campanha: iniciar, pausar, reiniciar, finalizar e duplicar.
- [x] Status operacional da campanha na listagem.
- [x] Status operacional por contato.
- [x] Controle de tentativas por contato.
- [x] Cooldown entre tentativas.
- [x] Fila de trabalho simulada por campanha.
- [x] Peso por lista.
- [x] Wizard visual de campanha em etapas.
- [x] Agenda operacional por dia/horario.
- [x] Configuracao visual de peso por lista no formulario.
- [x] Revisao da campanha antes de salvar.
- [x] Reciclar mailing improdutivo/callback/esgotado.
- [x] Discagem simulada por campanha ativa.
- [x] Chamada simulada vinculada a campanha e contato.
- [x] Bloqueio de nova chamada para agente com chamada sem tabulacao.
- [x] Agenda operacional aplicada automaticamente na discagem simulada.
- [x] Exibicao do estado da agenda na listagem.
- [x] Regras avancadas de tentativa por tabulacao.

Pendencias criticas:

- [ ] Etapas avancadas: AMD/tentativas, rotas e limites.

### Supervisao

Status: MVP simulado funcional.

- [x] Grid de agentes/supervisores.
- [x] Status atual.
- [x] Chamadas ativas.
- [x] Filtro por status.
- [x] Filtro por equipe.
- [x] Detalhes em modal.
- [x] Tempo no status.
- [x] Tempo em chamada.
- [x] Campanha atual do agente.
- [x] Alertas visuais de pausa longa e chamada longa.
- [x] Acoes Ouvir, Sussurrar e Intervir marcadas como "Em breve".

Pendencias importantes:

- [ ] Atualizacao em tempo real ou polling leve.
- [ ] Eventos/notificacoes operacionais persistidos.
- [ ] Acoes reais de monitoria somente na fase de telefonia real.

### Relatorios

Status: MVP funcional, avancado pendente.

- [x] Tela real de relatorios.
- [x] Filtros por periodo, agente, campanha, equipe e tabulacao.
- [x] Performance por agente.
- [x] Performance por equipe.
- [x] Performance por campanha.
- [x] Resultados por tabulacao.
- [x] Resumo de listas.
- [x] Ultimas chamadas.
- [x] Exportacao CSV.

Pendencias importantes:

- [ ] Relatorio analitico paginado.
- [ ] Relatorio por mailing/lista.
- [ ] TMA e TME.
- [ ] Conversao por campanha/equipe/agente.
- [ ] Exportacao XLSX.
- [ ] Exportacao PDF.
- [ ] Historico de exportacoes.
- [ ] Agendamento de relatorios.

### Notificacoes

Status: MVP operacional.

- [x] Notificacoes persistidas.
- [x] Painel lateral direito.
- [x] Marcar como lida.
- [x] Marcar todas como lidas.
- [x] Toasts no canto direito.
- [x] Eventos de campanha: iniciada, pausada, reiniciada e finalizada.
- [x] Evento de lista importada.
- [x] Alerta persistido de pausa longa.
- [x] Alerta persistido de chamada sem tabulacao.
- [x] Deduplicacao de alertas operacionais.

Pendencias importantes:

- [ ] Reverb/Echo para tempo real.
- [ ] Fallback por polling.
- [ ] Preferencias por usuario.

## Modulos Mantidos Como "Em Breve"

Estes modulos nao devem ser ativados agora, conforme decisao aprovada.

### Telefonia Real

Status: propositalmente "Em breve".

Itens futuros:

- [ ] Troncos SIP.
- [ ] Rotas de discagem.
- [ ] Configuracao de ramais reais.
- [ ] AMI/Asterisk.
- [ ] Originate.
- [ ] Hangup.
- [ ] Transferencia.
- [ ] Monitoria: ouvir, sussurrar e intervir.
- [ ] WebRTC com SIP.js ou JsSIP.
- [ ] AMD real.

### Go Bridge e Escala

Status: futuro.

Itens futuros:

- [ ] Servico Go.
- [ ] Redis como estado quente.
- [ ] Pacing em alta escala.
- [ ] Originate massivo.
- [ ] Event loop AMI.
- [ ] Sincronizacao de eventos para Laravel.

### Integracoes CRM/Webhooks

Status: propositalmente "Em breve".

Itens futuros:

- [ ] Endpoints de integracao.
- [ ] Webhooks.
- [ ] Logs de webhook.
- [ ] Retry.
- [ ] Assinatura/seguranca.
- [ ] Integracao futura com CRMTELECOM.

## Ordem Recomendada Para Concluir o MVP Simulado

### Etapa 1 - Campanhas Operacionais

Objetivo: transformar campanha basica em campanha operacional.

Status: parcialmente concluida em 2026-06-07.

1. [x] Criar status operacional: rascunho, ativa, pausada, finalizada.
2. [x] Criar acoes iniciar, pausar, reiniciar e finalizar.
3. [x] Criar duplicacao de campanha.
4. [x] Criar agenda operacional simples.
5. [x] Exibir progresso da campanha na listagem.
6. [x] Exibir estado da agenda na listagem.

Resultado esperado: administrador consegue preparar e controlar uma campanha simulada.

### Etapa 2 - Mailing e Fila de Trabalho

Objetivo: transformar listas em fila discavel.

Status: concluida para base do MVP simulado em 2026-06-07.

1. [x] Adicionar status operacional nos contatos.
2. [x] Adicionar tentativas e ultimo resultado.
3. [x] Criar cooldown por contato.
4. [x] Criar fila de trabalho por campanha/lista.
5. [x] Permitir reciclar contatos improdutivos.
6. [x] Permitir peso por lista.

Resultado esperado: campanha consegue selecionar o proximo contato de forma controlada.

### Etapa 3 - Discagem Simulada por Campanha

Objetivo: simular o discador antes da telefonia real.

Status: concluida para a base do MVP simulado em 2026-06-07.

1. [x] Criar servico `SimulatedDialerService`.
2. [x] Calcular capacidade por campanha.
3. [x] Gerar chamadas simuladas respeitando agentes disponiveis.
4. [x] Amarrar chamada ao contato e campanha.
5. [x] Evitar nova chamada para agente com chamada sem tabulacao.
6. [x] Respeitar agenda operacional antes de gerar chamadas.

Resultado esperado: campanha ativa gera chamadas simuladas para agentes disponiveis.

### Etapa 4 - Painel do Agente Completo

Objetivo: entregar a experiencia operacional do agente.

Status: parcialmente concluida em 2026-06-07.

1. [x] Exibir ficha do contato.
2. [x] Exibir campanha/lista atual.
3. [x] Exibir script de atendimento.
4. [x] Permitir campos de anotacao/formulario basico.
5. [x] Criar callback publico/privado.
6. [x] Mostrar historico recente do contato.
7. [x] Criar historico geral de chamadas recentes no painel.
8. [ ] Criar formulario dinamico configuravel por campanha.

Resultado esperado: agente consegue atender, registrar resultado e agendar retorno.

### Etapa 5 - Supervisao Operacional

Objetivo: dar visao de operacao em andamento.

1. [x] Mostrar tempo no status.
2. [x] Mostrar tempo em chamada.
3. [x] Mostrar campanha atual.
4. [x] Criar alertas de pausa longa e chamada longa.
5. Atualizar via polling leve ou Reverb.

Resultado esperado: supervisor acompanha a operacao sem telefonia real.

### Etapa 6 - Relatorios MVP+

Objetivo: fechar indicadores essenciais.

1. Relatorio analitico paginado.
2. Relatorio por lista/mailing.
3. Conversao por campanha.
4. Conversao por agente.
5. TMA basico.
6. Exportacao XLSX.

Resultado esperado: gestor consegue medir campanha, equipe, agente, lista e resultado.

### Etapa 7 - Notificacoes Operacionais

Objetivo: transformar notificacoes em recurso ativo do sistema.

1. [x] Notificar importacao de lista.
2. [x] Notificar campanha iniciada/pausada/finalizada.
3. [x] Notificar agente em pausa longa.
4. [x] Notificar chamada sem tabulacao.
5. Ligar Reverb/Echo ou polling.

Resultado esperado: sistema avisa eventos importantes sem alertas nativos.

## Ordem Recomendada Depois do MVP

### Etapa 8 - Preparacao da Telefonia

1. Definir contrato `DialerGateway`.
2. Adaptar chamada manual/simulada para passar pelo gateway.
3. Criar configuracoes de tronco e rota ainda sem conectar no Asterisk.
4. Criar testes de contrato.

### Etapa 9 - Asterisk/AMI

1. Implementar originate.
2. Implementar hangup.
3. Implementar transferencia.
4. Implementar eventos AMI.
5. Ativar monitoria da supervisao.

### Etapa 10 - Go Bridge

1. Criar servico Go separado.
2. Conectar Redis.
3. Mover pacing massivo para Go.
4. Sincronizar eventos para Laravel.

### Etapa 11 - Integracoes

1. Criar modulo Integracoes real.
2. Criar webhooks.
3. Criar logs e retry.
4. Preparar conexao CRMTELECOM.

## Proximo Plano Sugerido

O proximo plano de implementacao recomendado e:

```text
Relatorio Analitico Paginado
```

Motivo: o fluxo operacional do agente e das campanhas esta bem fechado para o MVP simulado. O proximo passo e dar ao gestor uma consulta analitica paginada das chamadas.

Entregaveis desse proximo plano:

- tabela paginada de chamadas;
- filtros reutilizando periodo, agente, campanha, equipe e tabulacao;
- exibicao de contato, telefone, resultado, campanha, agente e duracao;
- link/acao de exportacao mantendo filtros;
- testes de isolamento e filtros;
- smoke no Docker.

## Referencias de Planejamento Existentes

- `docs/superpowers/specs/2026-06-04-discador-thconect-design.md`
- `docs/superpowers/plans/2026-06-04-discador-thconect-phase-1.md`
- `docs/superpowers/specs/2026-06-06-agent-panel-simulated-design.md`
- `docs/superpowers/specs/2026-06-06-supervision-simulated-design.md`
- `docs/superpowers/specs/2026-06-06-reports-simulated-design.md`
- `docs/superpowers/specs/2026-06-06-operational-settings-design.md`
- `docs/superpowers/specs/2026-06-07-manual-extension-calls-design.md`
- `docs/superpowers/specs/2026-06-07-campaign-operational-actions-design.md`
- `docs/superpowers/specs/2026-06-07-mailing-queue-design.md`
- `docs/superpowers/specs/2026-06-07-simulated-campaign-dialer-design.md`
- `docs/superpowers/specs/2026-06-07-agent-contact-card-design.md`
- `docs/superpowers/specs/2026-06-07-agent-service-script-design.md`
- `docs/superpowers/specs/2026-06-07-agent-callback-fields-design.md`
- `docs/superpowers/specs/2026-06-07-agent-recent-calls-design.md`
- `docs/superpowers/specs/2026-06-07-supervision-operational-times-design.md`
- `docs/superpowers/specs/2026-06-07-operational-notifications-design.md`
- `docs/superpowers/specs/2026-06-08-campaign-wizard-schedule-design.md`
- `docs/superpowers/specs/2026-06-08-campaign-schedule-dialer-design.md`
- `docs/superpowers/specs/2026-06-08-disposition-retry-rules-design.md`
- `docs/superpowers/specs/2026-06-08-campaign-dynamic-service-fields-design.md`
