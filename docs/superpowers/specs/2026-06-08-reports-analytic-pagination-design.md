# Spec - Relatorio Analitico Paginado

Data: 2026-06-08

## Objetivo

Substituir a lista curta de ultimas chamadas por uma tabela analitica paginada, mantendo os filtros existentes e o fluxo de exportacao CSV.

## Decisoes

- O relatorio usa o mesmo `callsQuery` dos indicadores, filtros e exportacao.
- A tabela pagina 10 chamadas por pagina.
- A paginacao preserva a query string dos filtros.
- A tabela mostra telefone, contato, agente, campanha, resultado, status/canal, inicio e duracao.
- Chamadas em andamento mostram duracao como `em andamento`.
- O CSV continua exportando todos os registros filtrados, nao apenas a pagina atual.

## Fora de Escopo

- XLSX.
- PDF.
- Historico de exportacoes.
- Agendamento de relatorios.
- Colunas configuraveis por usuario.

## Criterios de Aceite

- Relatorio mostra tabela analitica paginada.
- Filtros por agente, campanha, equipe e tabulacao continuam funcionando.
- Segunda pagina mostra registros seguintes e preserva filtros.
- Tabela mostra duracao basica de chamadas encerradas.
- Exportacao CSV permanece com os mesmos filtros.
