# Indicadores de Uso Contratado vs Uso Atual

## Objetivo

Fechar a pendencia de indicadores de uso contratado contra uso atual para Super Admin e administrador da empresa.

## Escopo

- Consolidar o uso por empresa em um servico unico.
- Exibir no tenant:
  - usuarios ativos contra limite contratado;
  - agentes disponiveis contra limite contratado;
  - chamadas simultaneas contra teto contratado;
  - chamadas por agente disponivel;
  - capacidade atual pelos agentes disponiveis;
  - capacidade efetiva considerando teto da empresa.
- Exibir no Super Admin:
  - resumo geral das empresas ativas;
  - uso por empresa na listagem de contratos;
  - estados `Normal`, `Atencao` e `Limite excedido`.

## Regras

- `max_users` limita usuarios ativos.
- `max_concurrent_agents` limita agentes disponiveis quando configurado.
- `max_concurrent_calls` limita chamadas simultaneas quando configurado.
- Quando um limite nullable nao estiver configurado, a metrica aparece como sem teto.
- A capacidade efetiva agora e o menor valor entre:
  - agentes disponiveis multiplicados por chamadas por agente;
  - teto de chamadas simultaneas, quando configurado.
