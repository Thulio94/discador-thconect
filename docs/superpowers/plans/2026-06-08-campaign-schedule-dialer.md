# Plano - Agenda Operacional na Discagem Simulada

Data: 2026-06-08

## Escopo

Aplicar a agenda operacional salva na campanha ao fluxo de discagem simulada do MVP.

## Passos

1. Criar teste RED para impedir discagem fora da agenda.
2. Adicionar regra de agenda em `SimulatedDialerService`.
3. Bloquear a acao manual de rodar discagem no controller quando a campanha estiver fora da agenda.
4. Exibir estado da agenda na listagem de campanhas.
5. Criar teste positivo para discagem dentro da agenda.
6. Atualizar o roadmap de conclusao.
7. Rodar testes focados, suite completa, build e smoke Docker.

## Resultado Esperado

Administradores conseguem configurar uma campanha com agenda e o discador simulado respeita essa janela antes de entregar chamadas aos agentes.
