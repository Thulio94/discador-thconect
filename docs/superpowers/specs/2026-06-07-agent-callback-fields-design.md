# Spec - Callback e Campos Basicos do Atendimento

Data: 2026-06-07

## Objetivo

Permitir que o agente encerre uma chamada simulada registrando retorno publico/privado, data e hora do retorno e informacoes basicas do atendimento, mantendo o fluxo dentro do modal de tabulacao.

## Decisoes

- Callback e acionado por tabulacoes com categoria `callback`.
- Para categoria `callback`, `callback_at` e `callback_type` sao obrigatorios.
- `callback_type` aceita `public` e `private`.
- Campos basicos ficam gravados em `calls.service_fields` como JSON.
- Ao encerrar uma chamada vinculada a contato, o contato recebe:
  - `status = callback` e `next_attempt_at = callback_at` para retornos;
  - `status = answered` para tabulacao de sucesso;
  - `status = unproductive` para tabulacoes negativas ou improdutivas;
  - `last_result = disposition.code` em todos os casos vinculados.
- O modal continua modal-first e sem alerta nativo do navegador.

## Fora de Escopo

- Formulario dinamico configuravel por campanha.
- Regras avancadas de tentativa por tabulacao.
- Telefonia real, Asterisk, Go Bridge e integracoes.

## Criterios de Aceite

- Agente nao consegue salvar tabulacao `callback` sem data/hora e tipo de retorno.
- Agente consegue salvar retorno privado ou publico.
- Chamada guarda callback, anotacoes e campos basicos.
- Contato fica com status `callback` e proxima tentativa agendada.
- Testes cobrem o fluxo principal e a validacao obrigatoria.
