# Preferencias por Usuario nas Notificacoes

## Objetivo

Permitir que cada usuario controle quais categorias de notificacoes operacionais deseja receber, mantendo o painel lateral, os toasts e o fluxo sem `javascript.alert`.

## Categorias

- Campanhas: eventos de inicio, pausa, reinicio e finalizacao.
- Mailing e listas: importacoes e eventos de listas.
- Operacao: alertas de supervisao, pausa longa e chamada sem tabulacao.
- Sistema: avisos gerais.

## Regras

- Sem preferencia cadastrada, todas as categorias ficam habilitadas.
- A preferencia e individual por usuario e isolada por empresa.
- Preferencias desativadas impedem a criacao de notificacoes persistidas novas.
- O painel lateral continua exibindo as notificacoes ja criadas.
- A configuracao ocorre em modal dentro da central de notificacoes.

## Implementacao

- Tabela `user_notification_preferences`.
- Model `UserNotificationPreference`.
- Relacao `User::notificationPreferences`.
- `AppNotificationService` centraliza a decisao de criar ou ignorar a notificacao.
- Rota `tenant.notifications.preferences.update` protegida por `notifications.update`.

## Validacao

- Testes cobrem atualizacao das preferencias.
- Testes cobrem categoria desativada sem persistir notificacao.
- Testes cobrem audiencia da empresa respeitando preferencia individual.
- Teste de RBAC cobre a nova rota.
