# Plano - Relatorio Analitico Paginado

Data: 2026-06-08

## Escopo

Adicionar tabela analitica paginada na tela de relatorios do MVP simulado.

## Passos

1. Criar teste RED com mais de 10 chamadas filtradas.
2. Trocar consulta limitada por paginator com `withQueryString`.
3. Atualizar a view para tabela analitica.
4. Mostrar duracao e dados de agente/campanha/resultado.
5. Garantir que o CSV preserva os filtros.
6. Atualizar roadmap e referencias.
7. Rodar testes, build e smoke Docker.

## Resultado Esperado

Gestores conseguem navegar chamadas filtradas por pagina dentro do painel, sem depender apenas do CSV.
