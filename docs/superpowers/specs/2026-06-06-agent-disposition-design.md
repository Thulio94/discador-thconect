# Tabulacao do Agente - Design

## Objetivo

Evoluir o painel do agente para que uma chamada simulada seja encerrada com uma tabulacao operacional configurada no tenant.

## Escopo desta fase

- Listar tabulacoes ativas no modal de encerramento de chamada.
- Exigir `disposition_id` ao encerrar uma chamada simulada.
- Gravar na chamada o resultado selecionado, codigo, nome e observacoes.
- Bloquear tabulacoes de outra empresa.
- Manter o fluxo modal-first, sem `alert()` ou `confirm()` nativos.

## Fora do escopo

- Pos-atendimento temporizado.
- Callback privado/publico.
- Script dinamico de atendimento.
- Telefonia real e WebRTC.
