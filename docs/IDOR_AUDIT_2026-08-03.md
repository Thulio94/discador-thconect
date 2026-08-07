# Auditoria IDOR - 03/08/2026

## O que foi analisado

IDOR ocorre quando um usuario troca um identificador em uma URL ou requisicao e consegue consultar, baixar, editar ou excluir um recurso que pertence a outra conta. Em um SaaS, o limite obrigatorio e a empresa (`company_id`).

Foram revisadas todas as rotas empresariais que recebem modelos por ID, incluindo usuarios, equipes, perfis, listas, campanhas, monitoria, notificacoes, integracoes, exportacoes e gravacoes.

## Resultado

Nao foi identificada uma vulnerabilidade IDOR que permita leitura, download, alteracao ou exclusao de dados de outra empresa nos fluxos revisados.

Os principais controles encontrados foram:

- Cada controller empresarial valida que o recurso pertence ao `company_id` do usuario autenticado.
- Downloads de exportacoes e gravacoes verificam empresa, estado do arquivo e existencia no armazenamento privado antes da entrega.
- Filtros e relatorios recebem a empresa a partir da sessao, nunca de um parametro enviado pelo navegador.
- Relacoes entre equipes, usuarios e campanhas possuem validacoes por empresa e protecoes no banco.
- O Modo Deus e uma excecao intencional e limitada ao super admin; ele nao cria acesso para usuarios empresariais.

## Endurecimento aplicado

Algumas rotas retornavam `403` quando o ID pertencia a outra empresa. Embora isso ja bloqueasse a acao, a diferenca entre `403` e `404` poderia permitir enumerar IDs existentes.

Essas rotas agora respondem `404`, da mesma forma que um ID inexistente, para recursos de:

- usuarios e ramais;
- equipes;
- perfis;
- listas;
- campanhas e suas operacoes;
- monitoria de agentes;
- notificacoes.

Os controladores de relatorios, gravacoes, programacoes e integracoes ja utilizavam o mesmo comportamento seguro.

## Teste automatizado

Foi criado `IdorProtectionTest`, que autentica um administrador da Empresa A e tenta usar IDs da Empresa B para:

- editar usuario e gerar ramal;
- excluir equipe, perfil e lista;
- iniciar campanha;
- iniciar monitoria;
- excluir webhook;
- marcar notificacao como lida;
- baixar exportacao e ouvir gravacao.

Cada tentativa deve retornar `404` e o teste tambem confere que nenhum registro da Empresa B foi alterado.

**Validacao executada:** `IdorProtectionTest` foi executado no Docker e aprovado. A bateria complementar de integracoes, relatorios, telefonia e supervisao tambem foi aprovada, totalizando 19 testes e 132 verificacoes.

## Recomendacoes continuas

1. Todo novo controller empresarial com `{modelo}` deve verificar `company_id` antes de qualquer operacao.
2. Novas tabelas empresariais devem ter `company_id`, indice adequado e testes de acesso cruzado.
3. Mantenha os identificadores internos como IDs, mas nunca trate o ID como autorizacao. A autorizacao deve continuar sendo feita pela sessao, permissao e empresa.
4. Execute `IdorProtectionTest` no CI para impedir regressao.
