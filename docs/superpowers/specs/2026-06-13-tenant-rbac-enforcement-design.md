# Aplicacao de RBAC no Tenant

## Objetivo

Aplicar efetivamente as permissoes existentes nas rotas, acoes e navegacao do tenant, impedindo que usuarios acessem modulos ou executem operacoes fora de suas atribuicoes.

## Escopo

Esta etapa cobre:

- protecao das rotas do tenant por permissao;
- ampliacao da matriz para todos os modulos existentes;
- acesso integral do administrador da empresa ao proprio tenant;
- permissoes granulares para supervisores e agentes;
- restricoes estruturais por tipo de usuario;
- filtragem da sidebar e dos comandos visiveis;
- respostas HTTP `403` para acessos diretos nao autorizados;
- testes automatizados por perfil e operacao.

Nao fazem parte desta etapa alteracoes de telefonia, licenciamento, simultaneidade do discador ou integracoes externas.

## Modelo de autorizacao

O sistema utilizara um modelo hibrido:

1. O tipo do usuario estabelece limites estruturais.
2. O perfil associado fornece permissoes granulares.
3. O administrador da empresa possui acesso integral ao tenant.
4. O Super Admin permanece restrito ao painel global.

### Super Admin

- acessa o dashboard global e o cadastro de empresas e licencas;
- nao utiliza os modulos operacionais de uma empresa;
- continua identificado por `is_super_admin`.

### Administrador da empresa

- possui acesso integral aos modulos do proprio tenant;
- o acesso integral e reconhecido pelo perfil com `is_admin = true`;
- permanece sujeito ao isolamento por `company_id` e a validacao da licenca;
- nao recebe acesso a dados de outras empresas nem ao painel global.

### Supervisor

- acessa apenas modulos e operacoes concedidos pelo perfil;
- pode participar de varias equipes e campanhas para acompanhamento;
- pode usar o painel operacional para chamadas manuais quando possuir `agent.access`;
- nao entra na selecao de agentes disponiveis para discagem de campanhas.

### Agente

- utiliza o painel do agente e suas proprias chamadas quando autorizado;
- nao acessa administracao, configuracao ou supervisao apenas por conhecer a URL;
- nao visualiza nem altera chamadas de outros usuarios;
- pode pertencer a varias equipes e campanhas, conforme a regra atual.

## Matriz de permissoes

A matriz passara a contemplar:

| Modulo | Permissoes |
| --- | --- |
| Dashboard | `dashboard.read` |
| Usuarios | `users.read`, `users.create`, `users.update`, `users.delete` |
| Perfis | `roles.read`, `roles.create`, `roles.update`, `roles.delete` |
| Equipes | `teams.read`, `teams.create`, `teams.update`, `teams.delete` |
| Listas | `lists.read`, `lists.create`, `lists.update`, `lists.delete` |
| Campanhas | `campaigns.read`, `campaigns.create`, `campaigns.update`, `campaigns.delete`, `campaigns.operate` |
| Agente | `agent.access` |
| Supervisao | `supervision.read` |
| Relatorios | `reports.read`, `reports.export`, `reports.schedules` |
| Telefonia | `telephony.read` |
| Configuracoes | `settings.read`, `settings.create`, `settings.update`, `settings.delete` |
| Notificacoes | `notifications.read`, `notifications.update` |
| Integracoes | `integrations.read` |

As permissoes antigas de licencas serao preservadas no banco para compatibilidade, mas licencas continuarao administradas exclusivamente no painel de Super Admin.

## Aplicacao nas rotas

Cada rota recebera o middleware `can:tenant.<permissao>` correspondente. Rotas auxiliares, como polling, exportacao, operacao de campanha e leitura de notificacoes, tambem serao protegidas.

O middleware atual de tenant continuara responsavel por:

- usuario autenticado e verificado;
- empresa ativa;
- licenca valida;
- usuario pertencente ao tenant.

O Gate sera responsavel apenas pela autorizacao da operacao. Acesso sem permissao retornara `403`, sem redirecionamento que esconda a falha.

O dashboard escolhera o contexto pelo tipo de usuario. O Super Admin nao sera encaminhado para rotas do tenant.

## Aplicacao na interface

A sidebar sera criada a partir de itens com uma permissao associada. Um item somente sera renderizado quando `Gate::allows()` autorizar sua leitura ou acesso.

Dentro das telas:

- botoes de criacao exigirao permissao `create`;
- botoes de edicao exigirao permissao `update`;
- botoes de exclusao ou inativacao exigirao permissao `delete`;
- operacoes de campanha exigirao `campaigns.operate`;
- exportacoes exigirao `reports.export`;
- agendamentos exigirao `reports.schedules`.

Ocultar comandos melhora a experiencia, mas nao substitui a protecao das rotas.

## Compatibilidade e dados existentes

Nao sera criada migracao.

O seeder de permissoes utilizara `updateOrCreate` para registrar as novas chaves. Perfis administrativos existentes receberao todas as permissoes por serem identificados por `is_admin`, sem depender da tabela pivot para autorizar o administrador.

Perfis nao administrativos manterao as permissoes atuais. As novas permissoes precisarao ser escolhidas pelo administrador na tela de perfis. Para evitar perda imediata de acesso no ambiente de desenvolvimento, os perfis padrao de supervisor e agente criados pelo seeder serao atualizados com conjuntos coerentes:

- supervisor: dashboard, equipes em leitura, listas em leitura, campanhas em leitura e operacao, chamadas manuais, supervisao, relatorios e notificacoes;
- agente: dashboard, painel do agente e notificacoes.

## Seguranca adicional

- O valor `user_type` nao concedera acesso administrativo por si so.
- `role.is_admin` somente concedera acesso integral quando o perfil pertencer a mesma empresa do usuario.
- Usuarios sem perfil valido nao acessarao modulos protegidos.
- Operacoes continuarao validando que os registros pertencem ao mesmo `company_id`.
- A sidebar nao exibira links sem autorizacao.

## Testes

Os testes devem cobrir:

1. Administrador da empresa acessando todas as rotas do tenant.
2. Supervisor autorizado acessando supervisao e relatorios.
3. Supervisor sem permissao recebendo `403`.
4. Agente acessando seu painel e sendo bloqueado nos CRUDs administrativos.
5. Permissoes separadas de leitura, criacao, edicao e exclusao.
6. Operacao de campanha separada da edicao da campanha.
7. Exportacao e agendamento separados da leitura de relatorios.
8. Polling protegido pela mesma permissao da tela correspondente.
9. Sidebar sem itens nao autorizados.
10. Perfil administrativo de outra empresa sem conceder acesso.
11. Super Admin sem acesso acidental aos modulos operacionais do tenant.
12. Suite completa existente sem regressao.

## Criterios de aceite

- Nenhuma rota funcional do tenant permanece protegida apenas por autenticacao.
- Acesso direto sem permissao retorna `403`.
- Administrador da empresa mantem acesso integral ao proprio tenant.
- Supervisor e agente enxergam e executam somente o que seus perfis autorizam.
- A interface nao oferece comandos que o servidor recusaria.
- Todos os testes PHP e JavaScript permanecem aprovados no Docker/Windows.
