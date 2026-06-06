# Plano - Exportacao CSV de Relatorios

1. Criar teste para exportar CSV filtrado e isolado por empresa.
2. Adicionar rota `tenant.reports.export`.
3. Refatorar query compartilhada no `ReportController`.
4. Implementar resposta streamed CSV com cabecalho e linhas escapadas via `fputcsv`.
5. Atualizar botao da view para link real de exportacao mantendo filtros.
6. Rodar testes locais, build, Docker e smoke HTTP.
