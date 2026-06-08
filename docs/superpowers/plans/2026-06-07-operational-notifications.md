# Plano - Notificacoes Operacionais do MVP Simulado

Data: 2026-06-07

## Escopo

Criar notificacoes persistidas para eventos operacionais reais do MVP simulado.

## Implementacao

1. Criar testes de notificacao:
   - campanha iniciada notifica admin/supervisor;
   - importacao de lista notifica admin/supervisor;
   - pausa longa e chamada sem tabulacao criam notificacoes deduplicadas.
2. Ampliar `AppNotificationService`:
   - criar notificacao unica por usuario;
   - criar notificacoes para audiencia da empresa.
3. Atualizar `CampaignController`:
   - notificar inicio, pausa, reinicio e finalizacao.
4. Atualizar `ContactListController`:
   - notificar importacao com contatos criados.
5. Atualizar `SupervisionController`:
   - notificar pausa longa;
   - notificar chamada sem tabulacao;
   - deduplicar alertas.
6. Atualizar roadmap.
7. Rodar formatacao, testes, build e smoke Docker.

## Status

Concluido para o MVP simulado.
