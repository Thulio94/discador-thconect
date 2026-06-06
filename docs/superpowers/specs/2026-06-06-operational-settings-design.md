# Configuracoes Operacionais - Design

## Objetivo

Ativar o modulo `Configuracoes` como uma area operacional real do tenant, sem integrar ainda telefonia, CRM externo ou AMD avancado.

## Escopo desta fase

- Motivos de pausa por empresa.
- Tabulacoes por empresa.
- Blacklist de telefones por empresa.
- Interface com CRUD em modais, mantendo o usuario na mesma pagina.
- Telefonia, webhooks, AMD avancado e integracoes visiveis apenas como recursos reservados/em breve.

## Regras

- Todos os registros pertencem a uma `company_id`.
- Usuarios de uma empresa nao podem visualizar, alterar ou excluir registros de outra empresa.
- Excluir deve apenas inativar quando fizer sentido operacional.
- A pagina nao deve conter `javascript.alert`, `confirm()` ou fluxo de CRUD por paginas `create/edit`.
- Feedback deve continuar usando toast premium no canto direito.

## Entidades

### PauseReason

- `company_id`
- `name`
- `description`
- `status`
- `is_billable`

### Disposition

- `company_id`
- `name`
- `code`
- `category`
- `status`
- `ends_call`
- `is_success`

### BlacklistNumber

- `company_id`
- `phone`
- `reason`
- `status`

## Fora do escopo

- Troncos SIP reais.
- Rotas de telefonia reais.
- Webhooks/CRM externo.
- Motor AMD.
- Importacao em massa da blacklist.
