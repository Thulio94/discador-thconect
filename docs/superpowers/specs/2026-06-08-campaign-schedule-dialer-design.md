# Spec - Agenda Operacional na Discagem Simulada

Data: 2026-06-08

## Objetivo

Fazer o discador simulado respeitar a agenda operacional configurada na campanha antes de criar chamadas para agentes disponiveis.

## Decisoes

- Campanhas sem agenda configurada continuam livres para discagem simulada.
- Campanhas com agenda so discam quando o dia atual esta habilitado e o horario atual esta dentro da janela configurada.
- A regra fica centralizada em `SimulatedDialerService` para proteger qualquer acionamento futuro do discador.
- O painel de campanhas mostra se a campanha esta dentro ou fora da agenda.
- Quando o administrador tenta rodar discagem fora da agenda, o sistema retorna mensagem operacional via status/toast, sem `alert`.

## Fora de Escopo

- Feriados.
- Multiplas janelas por dia.
- Timezone configuravel por empresa.
- Telefonia real, Go Bridge e Asterisk.

## Criterios de Aceite

- Discagem simulada cria chamada dentro do horario configurado.
- Discagem simulada nao cria chamada fora do horario configurado.
- Contato e agente permanecem inalterados quando a campanha esta fora da agenda.
- Listagem mostra o estado da agenda para campanhas com agenda configurada.
- Testes cobrem o bloqueio, o caminho feliz e a exibicao do estado.
