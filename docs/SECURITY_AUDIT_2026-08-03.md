# Auditoria de Seguranca - 03/08/2026

## Escopo

Revisao do Laravel, rotas SaaS, isolamento entre empresas, autenticacao, importacao de listas, webhooks, telefonia Asterisk, armazenamento privado e configuracao Docker. A skill `firebase-security-rules-auditor` foi instalada e utilizada como referencia de analise por fronteiras de acesso. O projeto nao possui Firebase, Firestore, Storage Rules ou arquivos `.rules`; por isso a avaliacao foi adaptada aos controles equivalentes do Laravel.

## Resultado

Pontuacao atual: **7/10** para o ambiente de desenvolvimento, apos as correcoes desta revisao. O sistema possui boas bases de isolamento por empresa, autorizacao por permissao, CSRF, limites de requisicao e armazenamento privado. Ainda requer endurecimento de infraestrutura e monitoramento antes de producao.

## Correcoes aplicadas

| Area | Severidade original | Correcao |
| --- | --- | --- |
| Webhooks de empresa (SSRF) | Alta | URLs aceitam somente HTTPS publico, sem `localhost`, IPs privados/reservados, credenciais embutidas ou portas fora de 443. O teste de entrega nao segue redirecionamentos. |
| Reproducao de eventos Asterisk | Alta | Eventos agora incluem timestamp no HMAC e sao rejeitados depois de cinco minutos. |
| Importacao CSV/TXT | Media | Limite de 100.000 leads por arquivo, alem do limite existente de tamanho do upload. |
| Enumeracao de usuarios | Media | Recuperacao de senha responde de modo identico para emails existentes ou inexistentes. |
| Vazamento de detalhes tecnicos | Media | Falhas de conexao de telefonia e webhooks registram uma mensagem segura para a interface; a causa detalhada segue para os logs internos. |
| Dependencias JavaScript | Alta | Atualizada a cadeia `axios` e `form-data`; a auditoria atual do NPM nao aponta vulnerabilidades. |

## Controles validados

- Rotas empresariais usam autenticacao, verificacao de usuario ativo, empresa ativa e permissao especifica.
- Controllers verificam o `company_id` do recurso antes de exibir, editar, excluir ou baixar arquivos.
- Emails sao identificadores globais unicos e o login e feito somente por email.
- Senhas, segredos de webhooks e credenciais de telefonia sao manipulados fora das telas e as credenciais persistidas usam criptografia de modelo.
- Gravacoes e exportacoes ficam no disco privado e exigem autenticacao e permissao para entrega.
- Nginx bloqueia arquivos ocultos e os cabecalhos de seguranca sao aplicados pela aplicacao.
- As consultas analisadas usam Eloquent, Query Builder com vinculacao de parametros e validacao de entrada. Nao foram encontrados pontos de SQL montado por concatenacao.
- Ha limites de requisicao para login, recuperacao de senha, imports, exportacoes, testes de webhook e eventos do bridge.

## Riscos residuais e recomendacoes para producao

1. **DNS rebinding em webhooks externos, moderado.** A validacao resolve o DNS antes da chamada, mas um dominio pode trocar o IP entre a validacao e a conexao. Em producao, use um proxy de saida que repita a validacao no IP efetivamente conectado, com lista de destinos permitidos quando possivel.
2. **Segredos operacionais, alto.** Nunca mantenha senhas SIP, `ASTERISK_WEBHOOK_SECRET`, `APP_KEY` ou credenciais de banco em repositorio, imagens Docker ou capturas de tela. Configure-os por gerenciador de segredos no servidor e faça rotacao periodica.
3. **Exposicao de telefonia, alto.** Libere SIP e RTP apenas para IPs da operadora e servidores Asterisk. O AMI nao deve ser exposto publicamente. Mantenha firewall restritivo, VPN administrativa e logs de conexao.
4. **TLS e proxy, alto.** O ambiente atual e local. Antes da publicacao, usar HTTPS real, `APP_ENV=production`, `APP_DEBUG=false`, cookies `Secure`, HSTS apos validacao e backups criptografados.
5. **Dependencias, continuo.** Execute `composer audit` e `npm audit --omit=dev` no processo de CI. Atualizacoes com vulnerabilidade conhecida devem bloquear a entrega.
6. **Observabilidade, medio.** Centralize logs, alerte sobre repetidas falhas de HMAC, tentativas de SSRF, excesso de login, imports recusados e erros de fila. Restrinja o acesso aos logs pois podem conter dados operacionais.

## Testes executados

- `IntegrationSecurityTest`: bloqueio de IP interno, credenciais na URL e porta alternativa para webhooks.
- `TenantTelephonyConfigurationTest`: isolamento por empresa e rejeicao de evento Asterisk expirado.
- `PasswordResetTest`: recuperacao de senha e limite de requisicoes.
- `TenantCampaignListCrudTest`: importacao e fluxos de listas relacionados.

Resultado: **20 testes aprovados, 239 verificacoes**.

As auditorias `composer audit` e `npm audit --omit=dev --audit-level=high` tambem foram executadas ao final e nao retornaram vulnerabilidades conhecidas.

## Observacao sobre a suite completa

A suite completa ainda possui falhas legadas nos testes do painel generico de relatorios, substituido pelos relatorios especificos em alteracoes anteriores. Elas nao sao causadas por esta auditoria de seguranca e devem ser atualizadas antes da liberacao de producao.
