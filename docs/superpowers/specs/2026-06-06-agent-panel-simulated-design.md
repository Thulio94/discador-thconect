# Painel do Agente Simulado Design

## Objetivo

Liberar a primeira versao real do modulo Agente no Discador THCONECT, substituindo a tela "Em breve" por um painel operacional simulado. Esta fase prepara o controle de status do operador, a base de chamadas simuladas e os contratos internos para evoluir depois para discagem real via gateway de telefonia.

## Escopo

Esta fase inclui:

- status atual do agente;
- troca de status para disponivel, pausa e offline;
- historico simples de status;
- pausas com motivo livre;
- chamada simulada minima;
- area visual compacta para operacao do agente;
- isolamento por empresa;
- regra de capacidade baseada apenas em usuarios do tipo `agent`.

Esta fase nao inclui:

- Asterisk, AMI, Go Bridge ou WebRTC real;
- integracoes externas ou CRMTELECOM;
- gravacao, spy, whisper ou barge;
- motor completo de discagem preditiva.

## Regras

Todos os usuarios podem ter ramal e abrir o painel para uso operacional basico, mas somente usuarios `agent` em status `available` contam como capacidade de campanha.

Status permitidos:

- `offline`: operador fora da operacao.
- `available`: operador disponivel para receber chamadas de campanha.
- `paused`: operador em pausa e fora da capacidade.
- `in_call`: operador em chamada simulada.

O status `available` so deve ser usado por agentes ativos. Supervisores e administradores podem visualizar ramal e area de chamada manual futuramente, mas nao entram na contagem de pacing.

## Dados

Criar:

- `agent_statuses`: estado atual e historico de status por usuario.
- `agent_pauses`: registros de pausas iniciadas e encerradas.
- `calls`: chamadas simuladas vinculadas a empresa, usuario, campanha e contato quando disponiveis.

Os registros sempre carregam `company_id` para isolamento do tenant.

## Interface

O menu `Agente` passa a abrir uma tela real em `/tenant/agent`.

A tela deve seguir o design system THCONECT:

- sidebar existente;
- cards compactos;
- botoes sem `alert` ou `confirm` nativo;
- mensagens por flash/toast existente;
- modal para iniciar pausa com motivo;
- nenhuma troca de pagina para mudar status.

## Fluxo

1. Usuario entra no painel Agente.
2. Sistema mostra status atual, ramal, tipo de usuario e resumo de capacidade.
3. Botao `Disponivel` marca agente como disponivel quando permitido.
4. Botao `Pausa` abre modal para informar motivo e marca status `paused`.
5. Botao `Offline` encerra pausa aberta e marca status `offline`.
6. Botao de chamada simulada cria uma chamada `simulated` em andamento.
7. Botao de encerrar chamada finaliza a chamada simulada e volta o agente para `available`.

## Testes

Cobrir:

- agente consegue abrir o painel real;
- agente muda para disponivel e passa a contar na capacidade;
- supervisor nao conta na capacidade mesmo se abrir painel;
- pausa cria registro e remove da capacidade;
- chamada simulada muda status para `in_call` e encerramento volta para `available`;
- tenant nao acessa dados de outra empresa.
