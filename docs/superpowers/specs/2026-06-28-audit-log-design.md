# Auditoria Central Multiempresa - Design

## Objetivo

Criar um modulo de auditoria central para o Discador THCONECT que registre autenticacao e alteracoes administrativas relevantes, preserve o isolamento entre empresas e permita consulta segura pelo Super Admin e pelo administrador de cada empresa.

Esta entrega cobre empresas, licencas, usuarios, login, logout e falhas de autenticacao. A mesma base podera receber eventos dos futuros parametros operacionais sem mudar o contrato do modulo.

## Decisoes Aprovadas

- O Super Admin visualiza eventos globais e de todas as empresas.
- O administrador da empresa visualiza somente eventos vinculados ao proprio tenant.
- Login bem-sucedido, logout e tentativa de login invalida sao auditados.
- Alteracoes registram somente os campos modificados, com valor anterior e novo.
- Senhas, tokens, segredos e credenciais nunca sao persistidos na auditoria.
- Os registros nao possuem expiracao automatica.
- Somente o Super Admin pode limpar historico.
- A limpeza ocorre por empresa ou escopo global e periodo, exige motivo e confirmacao em modal.
- A propria limpeza gera um novo evento de auditoria depois da exclusao.

## Escopo Funcional

### Eventos de autenticacao

O modulo registra:

- login bem-sucedido;
- logout;
- tentativa de login invalida.

O evento de login ou logout referencia o usuario e a empresa quando existirem. Uma falha de login usa o e-mail informado apenas como contexto sanitizado. Quando o e-mail corresponder a um usuario existente, o evento tambem pode ser associado a sua empresa, sem registrar a senha ou revelar essa associacao na resposta de autenticacao.

### Eventos administrativos

Observers registram criacao, alteracao e inativacao de:

- empresa;
- licenca da empresa;
- usuario.

Atualizacoes sem mudanca efetiva nao geram evento. Em alteracoes, `old_values` e `new_values` contem apenas os campos que realmente mudaram. Nome e e-mail do ator e identificacao legivel da entidade sao preservados como snapshot para que o historico continue compreensivel mesmo depois de futuras alteracoes cadastrais.

### Consulta

O modulo Auditoria aparece na sidebar para perfis autorizados.

Super Admin:

- consulta eventos de todas as empresas e eventos globais;
- filtra por periodo, empresa, usuario, tipo de evento e texto;
- abre detalhes em modal;
- executa limpeza controlada.

Administrador da empresa:

- consulta somente eventos com o proprio `company_id`;
- filtra por periodo, usuario, tipo de evento e texto;
- abre detalhes em modal;
- nao visualiza nem acessa a acao de limpeza.

As listagens sao paginadas e ordenadas do evento mais recente para o mais antigo. A busca textual considera descricao, nome/e-mail do ator e identificacao legivel da entidade.

## Arquitetura

### Persistencia

A tabela `audit_logs` possui:

- `id`;
- `company_id`, opcional e indexado;
- `actor_user_id`, opcional e indexado;
- `actor_name` e `actor_email` como snapshots opcionais;
- `event`, identificador estavel como `auth.login` ou `license.updated`;
- `action`, como `created`, `updated`, `deactivated`, `login`, `logout`, `failed` ou `purged`;
- entidade afetada por referencia polimorfica opcional;
- `subject_label`, snapshot legivel da entidade;
- `description`, resumo legivel;
- `old_values` e `new_values`, JSON opcionais;
- `metadata`, JSON sanitizado para contexto adicional;
- `ip_address`, compativel com IPv4 e IPv6;
- `user_agent`;
- `route_name` e `http_method` opcionais;
- `created_at`.

O modelo nao oferece fluxo comum de atualizacao. Registros existentes sao tratados como imutaveis; a unica remocao permitida passa pelo servico de limpeza autorizado.

### Servicos

`AuditLogService` e a porta unica de gravacao. Ele recebe evento, ator, empresa, entidade, descricao e mudancas, adiciona o contexto HTTP disponivel e aplica sanitizacao recursiva antes de persistir.

`AuditValueSanitizer` remove chaves sensiveis, incluindo variacoes de senha, token, segredo, chave de API e `remember_token`. Valores escalares muito longos sao limitados para impedir crescimento acidental do registro.

`AuditLogQuery` aplica filtros e escopo. O escopo da empresa e imposto no backend e nao depende de parametros enviados pela interface.

`AuditPurgeService` valida o escopo, o periodo e o motivo, exclui os registros correspondentes em transacao e cria um evento `audit.purged` com quantidade, periodo, empresa ou escopo global e motivo. O evento novo usa a data atual e nao faz parte do conjunto apagado.

### Captura dos eventos

Observers de `Company`, `CompanyLicense` e `User` convertem eventos Eloquent em chamadas ao `AuditLogService`. Listeners dos eventos de autenticacao do Laravel registram login, logout e falha.

O servico de auditoria possui protecao contra recursao para que a criacao de um `AuditLog` nunca gere outro evento automaticamente.

Se a gravacao da auditoria falhar, a operacao principal nao e revertida exclusivamente por essa falha. O erro e enviado ao log tecnico da aplicacao com contexto minimo e sem dados sensiveis.

## Autorizacao e Isolamento

- Rotas do Super Admin continuam protegidas por `auth` e `super.admin`.
- Rotas do tenant usam `auth`, `tenant.active` e uma permissao `tenant.audit.read`.
- O administrador da empresa recebe essa permissao pelo perfil administrativo.
- Toda consulta tenant adiciona `where company_id = usuario.company_id` no servidor.
- IDs de empresa, usuario ou evento de outro tenant retornam `404` ou `403`, conforme o padrao existente.
- Apenas o Super Admin possui a rota de limpeza.
- O motivo da limpeza e obrigatorio e limitado a texto simples.

## Interface

A tela segue o design system atual: densa, operacional e sem pagina promocional.

- Barra de filtros no topo.
- Tabela paginada com data, empresa, ator, evento, entidade e resumo.
- Botao de detalhes abre modal, sem navegar para outra pagina.
- Modal apresenta metadados e comparacao anterior/novo em linhas legiveis.
- Valores ausentes aparecem como `Nao informado`.
- Eventos sem empresa sao identificados como `Global` somente para o Super Admin.
- A limpeza usa modal proprio, com escopo, data inicial, data final, motivo e aviso da quantidade estimada.
- Sucesso e erro usam os toasts existentes no canto direito; nao ha `javascript.alert` ou `window.confirm`.

## Validacao e Erros

- Datas de filtro sao opcionais, mas a data inicial nao pode ser posterior a final.
- A limpeza exige data inicial, data final, escopo especifico e motivo.
- O escopo de limpeza aceita uma empresa existente ou eventos globais sem empresa; nao existe opcao de apagar todas as empresas de uma vez.
- Uma limpeza sem registros correspondentes nao apaga nada e retorna aviso legivel.
- Filtros desconhecidos sao ignorados ou rejeitados pela validacao do controller, sem alterar o escopo de seguranca.
- Dados JSON invalidos ou nao serializaveis sao convertidos para uma representacao segura pelo sanitizador.

## Testes

### Unidade

- sanitizacao recursiva de senhas, tokens e segredos;
- calculo de campos realmente alterados;
- limitacao de valores extensos;
- criacao do payload de auditoria sem contexto HTTP.

### Feature

- login, logout e login invalido geram eventos corretos;
- senha informada nunca aparece no banco;
- criacao e alteracao de empresa, licenca e usuario geram historico;
- atualizacao sem mudanca nao gera evento;
- Super Admin consulta todas as empresas e eventos globais;
- administrador consulta apenas a propria empresa;
- outros perfis recebem `403` e nao veem a navegacao;
- filtros, busca e paginacao preservam o escopo;
- detalhes aparecem em modal;
- apenas o Super Admin limpa por empresa ou escopo global e periodo;
- motivo e periodo sao obrigatorios;
- limpeza cria `audit.purged` com a quantidade removida;
- rotas nunca expõem eventos de outro tenant.

### Regressao e interface

- CRUDs atuais continuam funcionando com observers ativos;
- autenticacao mantem as mesmas respostas para credenciais validas e invalidas;
- sidebar permanece responsiva;
- nao sao introduzidos alertas nativos;
- a suite completa, Pint, testes frontend e build Vite continuam verdes no Docker do Windows.

## Fora do Escopo

- exportacao de auditoria;
- retencao ou limpeza automatica;
- envio de auditoria para servico externo;
- alertas em tempo real para eventos de seguranca;
- auditoria de chamadas, contatos, campanhas e configuracoes operacionais nesta primeira entrega;
- restauracao de registros apagados pela limpeza autorizada.

Esses itens podem reutilizar a mesma tabela e os mesmos servicos em ciclos posteriores.
