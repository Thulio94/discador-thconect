# Autenticacao e identidade de usuarios

## Decisao

O Discador THCONECT utiliza o e-mail como identificador global e exclusivo de acesso. Todos os usuarios, independentemente da empresa, acessam a mesma pagina de login e informam somente e-mail e senha.

O ramal continua obrigatorio e globalmente exclusivo, mas e um identificador de telefonia. Ele nao pode ser usado para autenticar no painel.

## Regras implementadas

- O login aceita somente um e-mail valido.
- Espacos no inicio e no fim sao removidos.
- E-mails sao armazenados e consultados em letras minusculas.
- O indice unico `users_email_unique` impede dois registros com o mesmo e-mail no banco de dados.
- Cadastro, edicao administrativa e edicao do proprio perfil validam a unicidade em todas as empresas.
- A mensagem de conflito nao informa a qual empresa pertence o e-mail existente.
- Tentativas de login continuam limitadas por e-mail normalizado e endereco IP.
- Senhas nunca sao registradas na auditoria.

## Isolamento entre empresas

A empresa nao faz parte da credencial. Depois que o e-mail identifica o usuario, o sistema aplica `company_id`, status da empresa, licenca, perfil e permissoes. Essa ordem evita seletores de empresa no login, ambiguidade de contas e descoberta de clientes cadastrados.

## Migracao de dados existentes

A migracao `2026_08_02_000005_normalize_user_emails_for_global_login.php` verifica primeiro se existem e-mails repetidos sem diferenciar maiusculas, minusculas ou espacos. Se houver conflito, ela para sem alterar registros e informa os e-mails que precisam de revisao manual.

Quando nao ha conflito, os e-mails existentes sao normalizados para minusculas. Nenhum usuario, empresa ou outro dado e removido.

Os testes automatizados devem ser executados por `composer test`. A classe base de testes bloqueia a execucao quando encontra configuracao compilada ou uma conexao diferente do SQLite temporario, protegendo o banco de desenvolvimento.

## Regra operacional

Antes de transferir uma pessoa para outra empresa, o cadastro atual deve ser atualizado ou inativado conforme a politica comercial. Inativar um usuario nao libera automaticamente o e-mail, pois a identidade e o historico de auditoria continuam pertencendo ao mesmo registro. Uma eventual funcao de transferencia deve mover o usuario existente de forma auditada, e nao criar uma conta duplicada.
