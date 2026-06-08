# Spec - Regras de Tentativa por Tabulacao

Data: 2026-06-08

## Objetivo

Permitir que cada tabulacao controle o destino do contato apos o encerramento da chamada, incluindo retorno para fila, cooldown e limite maximo de tentativas.

## Decisoes

- As regras ficam na propria tabulacao para facilitar a operacao por empresa.
- `retry_enabled` indica se o contato volta para a fila.
- `retry_delay_minutes` define quando o contato pode ser discado novamente.
- `max_attempts` define quando o contato deve virar `exhausted`.
- Tabulacoes de sucesso continuam marcando contato como `answered`.
- Tabulacoes de retorno continuam usando a data/hora informada pelo agente.
- Tabulacoes negativas/improdutivas sem retry continuam marcando contato como `unproductive`.

## Fora de Escopo

- Regras diferentes por campanha.
- Regras por lista/mailing.
- Multipla arvore de tentativa por horario.
- Telefonia real, AMD real e Go Bridge.

## Criterios de Aceite

- Administrador cadastra cooldown e limite de tentativa na tabulacao.
- A lista de tabulacoes mostra a regra configurada.
- Ao tabular com retry habilitado, contato volta para `pending` com `next_attempt_at`.
- Ao atingir `max_attempts`, contato vira `exhausted`.
- Callback e sucesso preservam seus comportamentos atuais.
