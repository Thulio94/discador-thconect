# Relatorios por Equipe - Design

## Objetivo

Adicionar analise operacional por equipe na tela de relatorios, completando os filtros avancados do MVP sem depender de telefonia real.

## Escopo

- Adicionar filtro opcional por equipe em Relatorios.
- Filtrar chamadas por agentes vinculados a equipe selecionada.
- Exibir bloco "Performance por equipe" com total de chamadas por equipe.
- Aplicar o filtro por equipe tambem no CSV.
- Manter isolamento por empresa e ignorar equipes de outros tenants.

## Decisoes

- A equipe sera resolvida pela tabela `team_user`, pois chamadas pertencem ao agente.
- Supervisores podem aparecer em equipes, mas a contagem de chamadas depende apenas dos usuarios que possuem chamadas.
- O filtro por equipe combina com periodo, agente, campanha e resultado.

## Fora do Escopo

- Metas por equipe.
- SLA/TMA detalhado por equipe.
- Exportacao Excel/PDF.
- Integracao com telefonia real.
