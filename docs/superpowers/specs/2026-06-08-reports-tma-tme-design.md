# TMA e TME nos Relatorios

Data: 2026-06-08

## Objetivo

Adicionar indicadores de tempo medio ao modulo de relatorios do MVP simulado, mantendo compatibilidade com a futura telefonia real.

## Escopo

- Calcular TMA geral a partir de chamadas encerradas com `started_at` e `ended_at`.
- Calcular TME basico a partir de `answered_at - started_at` quando `answered_at` existir.
- Considerar espera `0s` em chamadas simuladas encerradas sem `answered_at`.
- Exibir TMA/TME em cards de metricas do relatorio.
- Exibir TMA nas performances por agente e por campanha.
- Melhorar o analitico com duracao e espera.
- Exportar CSV com duracao e espera mantendo todos os filtros atuais.

## Fora de Escopo

- Criar novas colunas de fila.
- Medir tempo real de URA, fila Asterisk ou Go Bridge.
- Exportacao XLSX/PDF.
- Conversao por campanha/equipe/agente.

## Regras

- TMA deve ignorar chamadas sem fim.
- TME deve ignorar chamadas sem inicio ou sem fim.
- Chamadas encerradas sem `answered_at` entram no TME como `0s`, pois no simulador a chamada chega diretamente ao agente.
- Todos os calculos respeitam periodo, agente, campanha, equipe, tabulacao e lista.

## Verificacao

- Teste feature para cards, performance, analitico e CSV.
- Suite de relatorios.
- Suite completa local.
- Suite completa no Docker.
