# Conversao por Campanha, Equipe e Agente

Data: 2026-06-08

## Objetivo

Adicionar indicadores de conversao aos relatorios do MVP simulado, usando as tabulacoes de sucesso ja configuradas pela empresa.

## Definicao

- Conversao e uma chamada encerrada com tabulacao marcada como `is_success = true`.
- Base de conversao sao chamadas encerradas com tabulacao dentro dos filtros aplicados.
- Taxa de conversao = conversoes / chamadas encerradas com tabulacao.
- Chamadas sem tabulacao nao entram no denominador da taxa.

## Escopo

- Exibir card geral de conversoes.
- Exibir card geral de taxa de conversao.
- Exibir conversoes e taxa por agente.
- Exibir conversoes e taxa por campanha.
- Exibir conversoes e taxa por equipe.
- Exportar CSV com coluna `conversao` por chamada.
- Respeitar filtros de periodo, agente, campanha, equipe, tabulacao e lista.

## Fora de Escopo

- Criar novas regras comerciais de conversao.
- Criar metas por campanha/equipe/agente.
- Exportacao XLSX/PDF.
- Integracoes externas.

## Verificacao

- Teste feature com tabulacoes de sucesso e nao sucesso.
- Suite de relatorios.
- Suite completa local.
- Suite completa no Docker.
