# Plano - Callback e Campos Basicos do Atendimento

Data: 2026-06-07

## Escopo

Adicionar suporte a callback e campos basicos no encerramento de chamada simulada do painel do agente.

## Implementacao

1. Criar testes do painel do agente:
   - callback exige data/hora e tipo;
   - callback salva dados na chamada;
   - contato recebe status `callback`, `last_result` e `next_attempt_at`.
2. Criar migration para `calls`:
   - `callback_type`;
   - `callback_at`;
   - `service_fields`.
3. Atualizar `Call` com fillable e casts.
4. Atualizar `AgentPanelController@endCall`:
   - validar dados extras;
   - exigir agenda para categoria `callback`;
   - persistir callback e campos basicos;
   - atualizar status operacional do contato.
5. Atualizar modal de tabulacao:
   - secao de retorno;
   - campos basicos: documento, protocolo e valor.
6. Atualizar roadmap.
7. Rodar testes, build e migracao Docker.

## Status

Concluido para o MVP simulado.
