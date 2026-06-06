# Supervisao Simulada Design

## Objetivo

Liberar a primeira versao real do modulo Supervisao no Discador THCONECT, usando os status e chamadas simuladas ja criados no Painel do Agente. A tela deve dar ao supervisor uma visao operacional rapida dos agentes da empresa, sem depender de telefonia real.

## Escopo

Esta fase inclui:

- tela real em `/tenant/supervision`;
- indicadores de agentes disponiveis, pausados, em chamada e offline;
- total de chamadas ativas simuladas;
- grid de usuarios operacionais da empresa;
- status atual, ramal, tipo de usuario, equipes e chamada ativa;
- filtros por status e equipe;
- modal de detalhes do agente;
- badges "Em breve" para acoes de telefonia futuras.

Esta fase nao inclui:

- ouvir, sussurrar ou intervir de verdade;
- Asterisk, AMI, Go Bridge ou WebRTC;
- permissao granular nova alem do middleware de tenant existente;
- atualizacao em tempo real via websocket.

## Regras

Somente dados da empresa logada podem aparecer. A grid deve listar usuarios `agent` e `supervisor`, porque supervisores tambem possuem ramal e podem aparecer na operacao. Apenas usuarios `agent` contam nas metricas de capacidade/disponibilidade de campanha.

Status considerados:

- `available`
- `paused`
- `in_call`
- `offline`

Usuarios sem status registrado aparecem como `offline`.

## Interface

A pagina deve usar o design system THCONECT:

- cards compactos para indicadores;
- grid denso para leitura rapida;
- filtros sem trocar de modulo;
- modal para detalhes;
- nenhuma chamada a `alert`, `confirm` ou popup nativo.

As acoes `Ouvir`, `Sussurrar` e `Intervir` ficam visiveis como itens "Em breve" para preparar a experiencia futura, mas sem rotas de telefonia real.

## Testes

Cobrir:

- supervisao abre como tela real e nao mostra "Modulo em breve";
- agentes de outra empresa nao aparecem;
- indicadores contam status corretamente;
- filtros por status e equipe funcionam;
- chamada ativa simulada aparece na grid;
- sidebar nao marca Supervisao como "Em breve".
