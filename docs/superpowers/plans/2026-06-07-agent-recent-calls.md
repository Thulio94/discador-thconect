# Plano - Historico Geral de Chamadas do Agente

Data: 2026-06-07

## Escopo

Adicionar uma lista de ultimas chamadas no painel do agente, com foco no proprio usuario autenticado.

## Implementacao

1. Criar teste do painel do agente:
   - exibe ultimas chamadas do usuario atual;
   - nao exibe chamadas de outro agente;
   - mostra tabulacao e callback publico/privado.
2. Atualizar `AgentPanelController@index` para carregar `recentCalls`.
3. Atualizar `resources/views/tenant/agent/index.blade.php` com lista compacta.
4. Atualizar roadmap.
5. Rodar formatacao, testes, build e smoke Docker.

## Status

Concluido para o MVP simulado.
