# Spec - Wizard de Campanha e Agenda Operacional

Data: 2026-06-08

## Objetivo

Melhorar o cadastro de campanhas do MVP simulado com etapas visuais, agenda operacional por dia/horario, revisao antes de salvar e configuracao de peso por lista.

## Decisoes

- O formulario continua dentro de modal, sem navegar para outra pagina.
- O wizard e visual e dividido em quatro etapas: basico, operacao, publico e revisao.
- A agenda operacional fica em `campaigns.operational_schedule` como JSON.
- Apenas dias marcados como habilitados sao persistidos.
- Horarios invalidos, com inicio maior ou igual ao fim, sao ignorados.
- O peso por lista usa o campo ja existente no pivot `campaign_contact_list.weight`.
- O backend aceita o formato antigo de `list_ids[]` e o novo formato `list_ids[id][selected|weight]`.

## Fora de Escopo

- Wizard com navegacao por abas dinamicas.
- Agenda aplicada automaticamente no `SimulatedDialerService`.
- Configuracao avancada por feriados.
- Regras avancadas de tentativas por tabulacao.

## Criterios de Aceite

- Formulario mostra etapas do wizard, agenda, revisao e peso por lista.
- Campanha persiste agenda operacional.
- Campanha persiste peso por lista.
- Isolamento multiempresa das listas continua protegido.
- Testes cobrem criacao, UI e regressao de campanhas.
