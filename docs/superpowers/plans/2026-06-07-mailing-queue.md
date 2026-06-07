# Plano - Mailing e Fila de Trabalho

Data: 2026-06-07

## Resultado

Entregue parcialmente para o MVP simulado.

## Implementacao

1. Criar testes para importacao, fila e reciclagem.
2. Adicionar campos operacionais em contatos.
3. Adicionar peso no vinculo campanha/lista.
4. Criar `MailingQueueService`.
5. Adicionar rota e modal de reciclagem de mailing.
6. Exibir resumo de mailing na listagem de campanhas.
7. Atualizar roadmap.
8. Validar local e Docker.

## Proxima Etapa Recomendada

Discagem simulada por campanha.

Motivo: a campanha agora tem ciclo operacional e fila de contatos. O proximo passo e gerar chamadas simuladas respeitando campanha ativa, contatos pendentes/cooldown e capacidade dos agentes disponiveis.
