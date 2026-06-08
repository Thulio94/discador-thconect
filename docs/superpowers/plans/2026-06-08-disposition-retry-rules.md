# Plano - Regras de Tentativa por Tabulacao

Data: 2026-06-08

## Escopo

Implementar regras simples de retorno para fila por tabulacao no MVP simulado.

## Passos

1. Criar testes RED para retry com cooldown e esgotamento por limite.
2. Adicionar campos de regra na tabela `dispositions`.
3. Atualizar model, validacao e formulario de tabulacao.
4. Aplicar regra no encerramento da chamada do painel do agente.
5. Mostrar a regra na listagem de configuracoes.
6. Atualizar roadmap e referencias.
7. Rodar testes, build e smoke Docker.

## Resultado Esperado

Cada empresa consegue configurar tabulacoes que controlam se o contato volta para a fila, quando volta e quando deve ser considerado esgotado.
