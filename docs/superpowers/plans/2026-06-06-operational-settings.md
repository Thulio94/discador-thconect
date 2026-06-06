# Plano - Configuracoes Operacionais

1. Criar testes de feature para rota real de configuracoes, CRUD modal-first e isolamento por empresa.
2. Criar migrations e models para motivos de pausa, tabulacoes e blacklist.
3. Criar controller tenant com validacao por `company_id`.
4. Trocar rota `tenant.settings.index` de `ComingSoonController` para controller real.
5. Remover selo `Em breve` da sidebar apenas em Configuracoes.
6. Criar view `tenant/settings/index.blade.php` com secoes, metricas e modais.
7. Integrar motivos de pausa no painel do agente como opcoes sugeridas.
8. Rodar testes locais, build e testes dentro do Docker.
