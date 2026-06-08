# Spec - Notificacoes Operacionais do MVP Simulado

Data: 2026-06-07

## Objetivo

Transformar a central de notificacoes em um recurso ativo do MVP simulado, criando notificacoes persistidas para eventos operacionais reais sem usar alertas nativos do navegador.

## Decisoes

- Notificacoes operacionais sao enviadas para usuarios ativos dos tipos `company_admin` e `supervisor`.
- Agentes nao recebem notificacoes administrativas por padrao.
- Eventos de campanha notificam inicio, pausa, reinicio e finalizacao.
- Importacao de lista notifica quando pelo menos um contato e importado.
- Alertas de supervisao notificam pausa longa e chamada sem tabulacao.
- Alertas de supervisao sao deduplicados para evitar repeticao ao reabrir a tela.
- Reverb/Echo continua como melhoria futura; o MVP persiste as notificacoes no banco e mostra pelo painel lateral.

## Fora de Escopo

- Preferencias de notificacao por usuario.
- Push em tempo real obrigatorio.
- Scheduler dedicado para alertas periodicos.
- Notificacoes para telefonia real, Go Bridge ou integracoes.

## Criterios de Aceite

- Campanha iniciada cria notificacao para admin/supervisor da empresa.
- Importacao de lista cria notificacao com quantidade importada.
- Pausa longa cria notificacao deduplicada.
- Chamada longa sem tabulacao cria notificacao deduplicada.
- Notificacoes nao vazam para outros tenants.
