# Relatorio por Mailing e Lista

Data: 2026-06-08

## Objetivo

Completar a visao de relatorios do MVP simulado com filtro por lista/mailing, resumo operacional por status de contatos e exportacao CSV mantendo os filtros aplicados.

## Escopo

- Adicionar filtro `list_id` na tela de relatorios.
- Filtrar chamadas analiticas, metricas e exportacao CSV por lista.
- Exibir painel "Mailing por lista" com totais por status do mailing.
- Calcular aproveitamento da lista com base em contatos atendidos sobre total de contatos.
- Manter isolamento por empresa.
- Manter navegacao na mesma pagina, sem telas extras para CRUD.

## Fora de Escopo

- Exportacao XLSX/PDF.
- Historico/agendamento de exportacoes.
- Integracoes externas.
- Telefonia real, AMD real e Asterisk.

## Comportamento Esperado

Quando o gestor filtra por campanha e lista, o relatorio deve:

- mostrar apenas chamadas vinculadas a contatos daquela lista;
- exportar apenas chamadas da lista filtrada;
- exibir pendentes, discando, atendidos, improdutivos, callbacks e esgotados;
- preservar o filtro `list_id` nos links de paginacao/exportacao;
- nao alterar status de contatos durante consulta/exportacao.

## Verificacao

- Teste feature cobrindo filtro por lista, resumo por status e CSV.
- Suite de relatorios.
- Suite completa e build antes de concluir.
