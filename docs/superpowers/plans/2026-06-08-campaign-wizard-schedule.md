# Plano - Wizard de Campanha e Agenda Operacional

Data: 2026-06-08

## Escopo

Adicionar etapas visuais, agenda operacional, revisao e peso por lista no cadastro/edicao de campanhas.

## Implementacao

1. Criar testes:
   - formulario mostra etapas, agenda, revisao e peso;
   - cadastro persiste agenda e pesos;
   - regressivos de campanha/lista continuam verdes.
2. Criar migration `operational_schedule` em `campaigns`.
3. Atualizar `Campaign` com fillable e cast.
4. Atualizar `CampaignController`:
   - normalizar agenda;
   - validar listas antigas e novas;
   - persistir pesos por lista;
   - duplicar campanha mantendo pesos e agenda.
5. Atualizar `tenant/campaigns/_form.blade.php`:
   - etapas visuais;
   - agenda por dia;
   - peso por lista;
   - revisao.
6. Atualizar roadmap.
7. Rodar formatacao, testes, build e smoke Docker.

## Status

Concluido para o MVP simulado.
