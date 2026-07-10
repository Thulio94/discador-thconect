# Contrato DialerGateway

Data: 2026-07-10

## Objetivo

Separar o fluxo operacional de chamadas da tecnologia de telefonia, preservando o discador simulado como implementacao ativa no ambiente local.

## Contrato

`App\Contracts\Dialer\DialerGateway` define tres operacoes:

- `originate`: inicia uma chamada de saida;
- `cancel`: registra uma tentativa cancelada antes de chegar ao agente;
- `hangup`: encerra uma chamada em andamento.

`DialerCallRequest` transporta somente o contexto operacional necessario: empresa, usuario, campanha, lead, telefone, canal e telefone de destino.

## Adaptador ativo

`SimulatedDialerGateway` e a implementacao registrada pelo container Laravel. Ele persiste os mesmos estados de chamadas usados antes da introducao do contrato:

- originate: `in_progress`, com atendimento simulado;
- cancel: `cancelled`, sem atendimento;
- hangup: `ended`.

O painel do agente e o `SimulatedDialerService` utilizam o contrato. Tabulacao, callbacks, fila, relatorios e supervisao continuam usando a entidade `Call` existente.

## Preparacao de telefonia

Cada empresa pode registrar apenas referencias operacionais de tronco, rota de saida e caller ID. Esses dados nao contem host, credencial, senha ou segredo e nao iniciam conexoes externas.

SIP, Asterisk, AMI, originate real e hangup real continuam em breve.

## Verificacao

- container resolve o gateway simulado;
- originate, cancel e hangup mantem estados esperados;
- chamadas manuais e lotes simulados continuam funcionando pelo contrato;
- configuracao de telefonia respeita RBAC e isolamento entre empresas.
