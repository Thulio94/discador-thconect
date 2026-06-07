# Discagem Simulada por Campanha

Data: 2026-06-07

## Objetivo

Gerar chamadas simuladas a partir de campanhas ativas, usando a fila de mailing e agentes disponiveis, sem ativar telefonia real.

## Escopo Entregue

- Servico `SimulatedDialerService`.
- Selecao de agentes ativos, do tipo agente, vinculados as equipes da campanha.
- Bloqueio de agente com chamada em andamento sem tabulacao.
- Selecao do proximo contato pendente pela fila de mailing.
- Criacao de chamada simulada vinculada a campanha, contato e agente.
- Marcacao do contato como `dialing` com tentativa e cooldown.
- Alteracao do status do agente para `in_call`.
- Acionamento manual pela tela de campanhas via modal.

## Regras

- Somente campanhas `active` geram chamadas.
- Supervisores e administradores nao recebem chamadas automaticas.
- A primeira versao entrega no maximo uma chamada por agente disponivel.
- A capacidade da licenca continua sendo usada como teto operacional.
- Telefonia real, AMI e Go Bridge permanecem fora do escopo.

## Fora do Escopo

- Pacing automatico em loop.
- Multipla discagem simultanea por agente antes de atendimento.
- AMD real.
- WebRTC/SIP.
- Monitoria real de chamada.
