# Polling de Supervisao e Notificacoes - Design

Data: 2026-06-13

## Objetivo

Atualizar automaticamente a supervisao operacional e a central de notificacoes sem recarregar a pagina, mantendo o MVP simples e preparando um fallback confiavel para a futura ativacao do Laravel Reverb.

## Decisao de Arquitetura

Foram consideradas tres abordagens:

1. Recarregar a pagina completa em intervalos fixos.
2. Ativar eventos em tempo real com Reverb e Echo.
3. Buscar fragmentos HTML por polling leve.

A terceira abordagem foi escolhida. Ela preserva Blade, Alpine.js e o design system existentes, evita uma camada duplicada de templates em JSON e reduz a complexidade operacional antes da preparacao para producao.

O Laravel permite retornar fragmentos nomeados de views Blade. O navegador substituira somente as regioes marcadas da pagina.

## Escopo

### Supervisao

A pagina de Supervisao atualizara:

- indicadores de disponiveis, pausa, chamada, offline e chamadas ativas;
- lista filtrada de agentes;
- status e tempo no status;
- chamada e campanha atuais;
- tempo em chamada;
- alertas operacionais;
- conteudo dos modais de detalhes.

Os filtros atuais de status e equipe serao enviados em todas as requisicoes de polling.

### Notificacoes

A central lateral atualizara:

- contador de nao lidas;
- oito notificacoes mais recentes;
- estado visual de lida ou nao lida;
- links de acao.

O polling de notificacoes funcionara em todas as paginas autenticadas.

## Endpoints

Criar endpoints GET autenticados e tenant-aware:

```text
GET /tenant/supervision/poll
GET /tenant/notifications/poll
```

`supervision/poll` retornara os fragmentos Blade de metricas e agentes. `notifications/poll` retornara o painel completo de notificacoes.

As respostas devem conter:

- `Cache-Control: no-store, private`;
- cabecalho `X-Poll-Interval` com o intervalo normal em segundos;
- apenas dados autorizados para o usuario e empresa autenticados.

## Estrutura de Views

Extrair da pagina atual:

- `tenant/supervision/_metrics.blade.php`;
- `tenant/supervision/_agents.blade.php`.

O painel de notificacoes existente continuara em:

- `components/ui/notification-panel.blade.php`.

Cada regiao atualizavel tera um elemento raiz estavel:

```text
#supervision-metrics
#supervision-agents
#notification-panel
```

Os modais de detalhes dos agentes permanecerao dentro do fragmento de agentes para que seus dados tambem sejam atualizados.

## Cliente de Polling

Criar `resources/js/polling.js` com um controlador reutilizavel.

Comportamento:

- intervalo normal de 10 segundos;
- primeira atualizacao somente apos o intervalo, evitando requisicao duplicada no carregamento;
- nenhuma nova requisicao enquanto a anterior estiver ativa;
- `AbortController` encerra a requisicao anterior ao destruir ou reiniciar o controlador;
- polling suspenso quando `document.visibilityState !== 'visible'`;
- atualizacao imediata ao retornar para a aba visivel;
- backoff de 10, 20, 40 e no maximo 60 segundos apos falhas;
- retorno ao intervalo normal depois de uma resposta valida;
- credenciais same-origin e cabecalho `X-Requested-With: XMLHttpRequest`;
- falhas silenciosas na interface, registradas apenas com `console.warn` em ambiente de desenvolvimento;
- nenhuma notificacao `alert`, modal ou toast repetitivo para falhas temporarias.

O HTML recebido sera analisado com `DOMParser`. Cada elemento raiz esperado substituira somente o `innerHTML` da regiao correspondente, preservando o elemento raiz e o controlador de polling.

## Integracao Alpine

A Supervisao usara:

```html
<div
    x-data="pollingRegion({...})"
    x-init="start()"
    x-on:visibilitychange.document="handleVisibilityChange()"
>
```

O layout autenticado aplicara o mesmo controlador ao contenedor da central de notificacoes.

A substituicao nao deve recriar o elemento que possui `x-data`, evitando reinicializacoes concorrentes do Alpine.

## Regras de Concorrencia

- Uma regiao pode possuir no maximo uma requisicao em andamento.
- Um novo ciclo nao inicia se `inFlight` estiver ativo.
- Ao ocultar a aba, o timer e cancelado e a requisicao ativa pode concluir sem agendar novo ciclo.
- Ao mostrar a aba, uma atualizacao imediata e iniciada somente se nao houver outra ativa.
- Ao navegar para outra pagina, o controlador aborta a requisicao pendente.

## Alertas Operacionais

O polling da supervisao continuara avaliando pausa longa e chamada longa. O servico atual de notificacoes usa deduplicacao, portanto ciclos sucessivos nao devem criar notificacoes repetidas.

O endpoint de notificacoes nao cria alertas; ele apenas consulta notificacoes existentes do usuario.

## Seguranca

- Todos os endpoints permanecem no grupo `auth`, `verified`, `tenant.active` e `tenant.user`.
- A supervisao consulta somente usuarios, equipes e chamadas da empresa autenticada.
- Notificacoes sao consultadas somente pelo relacionamento do usuario autenticado.
- Filtros de equipe devem validar que a equipe pertence a empresa autenticada.
- Respostas nao podem ser armazenadas por navegador, proxy ou CDN.
- Nenhum endpoint aceita HTML, scripts ou IDs arbitrarios para renderizacao.

## Tratamento de Erros

- Falha HTTP nao altera o conteudo atual.
- Resposta sem os elementos esperados e considerada invalida.
- Sessao expirada com status 401 ou 419 interrompe polling ate uma navegacao ou recarga.
- Status 403 ou 404 interrompe a regiao afetada.
- Erros temporarios 429 e 5xx usam backoff.

## Testes

### Feature

- endpoint de supervisao retorna apenas fragmentos esperados;
- filtros de status e equipe continuam funcionando;
- equipe de outra empresa nao pode ser usada;
- dados de outra empresa nao aparecem;
- resposta possui `Cache-Control` e `X-Poll-Interval`;
- endpoint de notificacoes retorna somente notificacoes do usuario;
- contador de nao lidas e estado visual sao atualizados;
- alertas operacionais continuam deduplicados durante polling.

### Frontend

Os testes de build e verificacoes de markup devem confirmar:

- controladores de polling presentes nas regioes corretas;
- intervalos configurados;
- ausencia de `setInterval`, evitando sobreposicao;
- uso de `visibilitychange` e `AbortController`;
- ausencia de `alert` e `confirm`.

## Fora do Escopo

- ativacao de canais Reverb/Echo;
- eventos WebSocket;
- monitoria de audio;
- telefonia real;
- notificacoes push do navegador;
- preferencias individuais de intervalo;
- polling do painel do agente;
- alteracao da logica de criacao de alertas.

## Evolucao Futura

Quando Reverb for ativado, os mesmos fragmentos e eventos de atualizacao poderao ser reutilizados. O polling permanecera disponivel como fallback quando a conexao WebSocket estiver indisponivel.

