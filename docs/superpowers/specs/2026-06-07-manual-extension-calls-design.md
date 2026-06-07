# Chamadas Manuais por Ramal - Design

## Objetivo

Permitir que usuarios administrativos, supervisores e agentes usem o painel de ramal para iniciar chamadas manuais simuladas, sem entrar em campanhas e sem depender de telefonia real.

## Escopo

- Manter acesso ao painel para usuarios tenant com ramal.
- Exibir a area de chamada manual simulada para todos os tipos tenant.
- Gravar chamadas manuais com `channel = manual`.
- Manter agentes como os unicos usuarios que contam para capacidade de campanhas.
- Ao encerrar chamada manual de usuario nao agente, voltar para `offline` em vez de `available`.

## Fora do Escopo

- Webphone real.
- Discagem Asterisk/Go Bridge.
- Transferencia entre ramais.
- Fila receptiva real.
