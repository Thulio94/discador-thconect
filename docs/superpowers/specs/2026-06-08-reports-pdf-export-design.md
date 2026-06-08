# Exportacao PDF dos Relatorios

Data: 2026-06-08

## Objetivo

Adicionar exportacao PDF simples ao modulo de relatorios para compartilhamento de resumo executivo da operacao.

## Escopo

- Criar rota de download PDF.
- Exportar resumo com totais, TMA, TME, conversoes e taxa de conversao.
- Listar filtros aplicados no cabecalho.
- Incluir amostra analitica limitada das chamadas filtradas.
- Manter CSV e XLSX existentes.
- Adicionar botao "Exportar PDF" na tela de relatorios.

## Fora de Escopo

- Historico de exportacoes.
- Agendamento de relatorios.
- Layout PDF avancado com graficos.
- Multiplas paginas complexas.

## Regras

- O PDF deve respeitar os mesmos filtros da tela.
- O arquivo deve se chamar `relatorios-chamadas.pdf`.
- A amostra analitica deve ser limitada para manter o PDF leve.
- O conteudo deve manter isolamento por empresa.

## Verificacao

- Teste feature verificando download PDF, conteudo e filtros.
- Suite de relatorios.
- Suite completa local.
- Suite completa no Docker.
