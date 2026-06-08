# Plano - Relatorio por Mailing e Lista

Data: 2026-06-08

## Passos

1. Criar teste feature para filtro por `list_id`, resumo do mailing e CSV.
2. Adicionar `list_id` ao pipeline de filtros do `ReportController`.
3. Aplicar filtro por lista em chamadas, performance, tabulacoes e exportacao.
4. Criar resumo por lista com contagem de contatos por status.
5. Atualizar a view de relatorios com seletor de lista e painel "Mailing por lista".
6. Atualizar roadmap de conclusao.
7. Rodar Pint, testes, build e verificacao no Docker.

## Criterios de Aceite

- O relatorio permite filtrar por lista.
- O analitico nao mostra chamadas de outras listas quando `list_id` esta aplicado.
- O CSV respeita o filtro por lista.
- O painel mostra pendentes, discando, atendidos, improdutivos, callbacks e esgotados.
- O aproveitamento da lista e calculado em percentual.
- Nao ha alteracao de status de contatos durante consultas.
