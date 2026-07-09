# Filtro e Busca nos Cadastros Grandes

## Objetivo

Melhorar a navegacao dos cadastros que podem crescer muito no tenant, mantendo CRUD em modal, RBAC e isolamento por empresa.

## Escopo Entregue

- Usuarios:
  - busca por nome, e-mail ou ramal;
  - filtro por status, tipo, perfil e equipe.
- Equipes:
  - busca por nome;
  - filtro por status e membro.
- Campanhas:
  - busca por nome ou descricao;
  - filtro por status, modo, equipe e lista.
- Listas:
  - busca por nome ou descricao;
  - filtro por status e campanha.

## Regras

- Todos os filtros usam `GET` e preservam query string na paginacao.
- Filtros por IDs validam que o registro pertence a empresa atual.
- Acoes de criar, editar, inativar, operar e duplicar continuam em modal.
- Relacionamentos com pivot de empresa usam subqueries diretas quando necessario para evitar vazamento e manter compatibilidade com filtros.

## Fora de Escopo

- Busca global cross-tenant.
- Exportacao de cadastros.
- Filtros dos modulos futuros de telefonia, Asterisk e integracoes.
