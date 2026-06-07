# Mailing e Fila de Trabalho

Data: 2026-06-07

## Objetivo

Transformar contatos importados em uma fila operacional simulada para campanhas, preparando o terreno para o discador simulado por campanha.

## Escopo Entregue

- Contatos importados entram como `pending`.
- Contatos possuem contador de tentativas.
- Contatos possuem ultima tentativa, proxima tentativa e ultimo resultado.
- Campanhas podem selecionar o proximo contato disponivel.
- A fila respeita status, cooldown e listas vinculadas a campanha.
- Listas vinculadas a campanha possuem peso operacional.
- Campanhas exibem resumo de mailing por status.
- Administrador pode reciclar mailing via modal.
- Reciclagem preserva historico de tentativas.
- Isolamento multiempresa preservado.

## Status Operacionais de Contato

- `pending`: pronto para entrar na fila.
- `dialing`: em processo de discagem simulada.
- `answered`: atendido/concluido.
- `unproductive`: tentativa improdutiva.
- `callback`: retorno agendado.
- `exhausted`: tentativas esgotadas.

## Fora do Escopo

- Discagem automatica por campanha.
- Vinculo automatico da chamada ao contato atual.
- Callback publico/privado no painel do agente.
- Regras avancadas por tabulacao.
- Telefonia real e Go Bridge.
