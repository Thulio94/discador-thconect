# Painel do Agente - Script de Atendimento

Data: 2026-06-07

## Objetivo

Permitir que cada campanha tenha um roteiro de atendimento exibido diretamente no painel do agente durante a chamada.

## Escopo Entregue

- Campo `service_script` em campanhas.
- Cadastro e edicao do script no modal de campanha.
- Duplicacao de campanha preserva o script.
- Painel do agente exibe o script da campanha da chamada atual.
- Modal de tabulacao usa "Anotacoes do atendimento" para reforcar o registro operacional.

## Regras

- Script e opcional.
- Script fica isolado por empresa junto com a campanha.
- Script aparece apenas quando a chamada atual tem campanha vinculada.
- Telefonia real e integracoes seguem como "Em breve".

## Fora do Escopo

- Campos dinamicos configuraveis por campanha.
- Callback publico/privado.
- Templates avancados por produto.
- Variaveis automaticas dentro do script.
