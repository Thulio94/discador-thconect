# Spec - Supervisao Operacional com Tempos

Data: 2026-06-07

## Objetivo

Dar ao supervisor mais contexto operacional no painel simulado, exibindo tempo no status, tempo em chamada, campanha atual e alertas visuais simples.

## Decisoes

- A tela continua simulada e sem telefonia real.
- O tempo no status usa o registro atual de `agent_statuses.started_at`.
- O tempo em chamada usa `calls.started_at` da chamada `in_progress`.
- A campanha atual vem da chamada ativa vinculada a campanha.
- Pausa longa aparece quando o status `paused` tem 20 minutos ou mais.
- Chamada longa aparece quando a chamada ativa tem 15 minutos ou mais.
- Acoes Ouvir, Sussurrar e Intervir continuam marcadas como "Em breve".

## Fora de Escopo

- Atualizacao em tempo real via Reverb.
- Polling automatico.
- Monitoria real de telefonia.
- Configuracao customizada dos limites de alerta.

## Criterios de Aceite

- Supervisor ve tempo no status por operador.
- Supervisor ve tempo em chamada quando houver chamada ativa.
- Supervisor ve campanha atual quando a chamada ativa tem campanha.
- Pausa longa e chamada longa aparecem como alertas visuais.
- Testes garantem que o painel renderiza os novos dados.
