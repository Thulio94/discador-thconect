# Plano - Supervisao Operacional com Tempos

Data: 2026-06-07

## Escopo

Adicionar tempo no status, tempo em chamada, campanha atual e alertas simples na tela de supervisao.

## Implementacao

1. Criar teste da supervisao:
   - status em pausa longa;
   - chamada longa;
   - campanha atual vinculada a chamada ativa.
2. Atualizar `SupervisionController@index`:
   - carregar campanha da chamada ativa;
   - calcular duracoes;
   - montar alertas operacionais.
3. Atualizar `resources/views/tenant/supervision/index.blade.php`:
   - exibir tempo no status;
   - exibir campanha atual;
   - exibir tempo em chamada;
   - exibir alertas no card e no modal.
4. Atualizar roadmap.
5. Rodar formatacao, testes, build e smoke Docker.

## Status

Concluido para o MVP simulado.
