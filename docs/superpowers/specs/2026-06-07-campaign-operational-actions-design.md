# Campanhas Operacionais - Status e Acoes

Data: 2026-06-07

## Objetivo

Permitir que o administrador da empresa controle o ciclo operacional basico de uma campanha simulada sem sair da listagem e sem usar alertas nativos do navegador.

## Escopo Entregue

- Iniciar campanha em rascunho.
- Pausar campanha ativa.
- Reiniciar campanha pausada.
- Finalizar campanha mantendo historico operacional.
- Duplicar campanha em rascunho com as mesmas equipes e listas.
- Exibir inicio e fim operacional na listagem.
- Manter todas as acoes em modais.
- Garantir isolamento multiempresa nas acoes.

## Regras

- Campanhas finalizadas nao sao excluidas fisicamente.
- Finalizar grava `ends_at`.
- Iniciar e reiniciar gravam `starts_at` quando ainda nao existir.
- Duplicar nao reaproveita status operacional; a copia nasce em rascunho.
- Nomes duplicados recebem sufixo numerico.

## Fora do Escopo

- Agenda operacional por dia/horario.
- Reciclagem de mailing.
- Fila discavel por contato.
- Pacing real ou Go Bridge.
- Integracoes externas.
