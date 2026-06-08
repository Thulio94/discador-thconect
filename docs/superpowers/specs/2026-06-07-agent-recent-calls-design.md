# Spec - Historico Geral de Chamadas do Agente

Data: 2026-06-07

## Objetivo

Exibir no painel do agente uma visao das ultimas chamadas do proprio usuario, sem trocar de pagina e sem depender de haver uma chamada ativa.

## Decisoes

- A lista usa chamadas do usuario autenticado e da empresa atual.
- O limite inicial e de 8 chamadas recentes.
- Cada item mostra telefone, campanha, canal, status, data/hora, tabulacao e callback quando existir.
- Callback aparece como retorno publico ou privado, com data/hora agendada.
- A lista fica no painel principal do agente para leitura rapida.

## Fora de Escopo

- Filtros avancados.
- Modal de detalhes da chamada.
- Edicao retroativa de tabulacao.
- Historico geral de todos os agentes, que pertence a relatorios/supervisao.

## Criterios de Aceite

- O agente ve suas ultimas chamadas no painel.
- Chamadas de outro usuario nao aparecem.
- Callback agendado aparece destacado.
- O recurso passa nos testes automatizados e no smoke Docker.
