# Plano - Tabulacao do Agente

1. Criar testes de feature para encerramento com tabulacao obrigatoria.
2. Criar migration incremental em `calls` com campos de tabulacao.
3. Atualizar `Call` com fillable, casts e relacao `disposition`.
4. Atualizar `AgentPanelController` para carregar tabulacoes ativas e validar tenant.
5. Trocar o botao direto de encerrar chamada por modal de tabulacao.
6. Rodar testes locais, build, migration/testes no Docker e smoke HTTP.
