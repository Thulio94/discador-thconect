# Exportacao XLSX dos Relatorios

Data: 2026-06-08

## Objetivo

Adicionar exportacao XLSX ao modulo de relatorios, mantendo os mesmos filtros e colunas relevantes do CSV.

## Escopo

- Criar rota de download XLSX.
- Exportar chamadas filtradas por periodo, agente, campanha, equipe, tabulacao e lista.
- Incluir colunas de duracao, espera e conversao.
- Manter o CSV existente.
- Adicionar botao "Exportar XLSX" na tela de relatorios.

## Fora de Escopo

- Historico de exportacoes.
- Agendamento de relatorios.
- Exportacao PDF.
- Planilhas com abas multiplas.

## Regras

- O XLSX deve respeitar exatamente os filtros da tela.
- O arquivo deve usar cabecalho legivel para analise gerencial.
- O download deve ter nome `relatorios-chamadas.xlsx`.
- A exportacao deve manter isolamento por empresa.

## Verificacao

- Teste feature lendo o XLSX gerado com PhpSpreadsheet.
- Suite de relatorios.
- Suite completa local.
- Suite completa no Docker.
