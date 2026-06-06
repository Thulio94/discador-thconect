# Relatorios Simulados Design

## Objetivo

Liberar a primeira versao real do modulo Relatorios no Discador THCONECT, usando os dados operacionais simulados ja disponiveis: chamadas, status de agentes, pausas, campanhas, listas e usuarios. A tela deve ajudar administradores e supervisores a acompanhar a operacao sem depender de telefonia real.

## Escopo

Esta fase inclui:

- tela real em `/tenant/reports`;
- indicadores de chamadas totais, em andamento e encerradas;
- indicadores de agentes disponiveis, pausados e em chamada;
- resumo de performance por agente;
- resumo de performance por campanha;
- resumo de listas de contatos;
- ultimas chamadas simuladas;
- filtros por periodo, agente e campanha;
- exportacao marcada como "Em breve".

Esta fase nao inclui:

- geracao real de CSV, XLSX ou PDF;
- relatorios financeiros;
- dashboards em tempo real via websocket;
- dados reais de Asterisk/AMI/Go Bridge.

## Regras

Todos os dados devem ser filtrados por `company_id`. Usuarios de outra empresa, chamadas de outra empresa e campanhas de outra empresa nunca podem aparecer.

Os filtros de periodo usam `started_at` das chamadas. Quando nenhuma data for informada, o painel mostra os ultimos 30 dias.

Chamadas sem campanha entram no resumo geral e em ultimas chamadas, mas nao aparecem no ranking de campanhas.

## Interface

A pagina deve usar o design system THCONECT:

- cards compactos;
- tabelas densas e legiveis;
- filtros no topo;
- badge "Exportar - Em breve";
- nenhuma chamada a `alert`, `confirm` ou popup nativo.

## Testes

Cobrir:

- relatorios abrem como tela real e nao mostram "Modulo em breve";
- dados de outra empresa nao aparecem;
- cards contam chamadas e status corretamente;
- filtros por agente e campanha funcionam;
- ultimas chamadas simuladas aparecem;
- sidebar nao marca Relatorios como "Em breve".
