# Simultaneidade do Discador Simulado - Design

Data: 2026-06-14

## Objetivo

Aplicar de forma efetiva os limites de chamadas configurados pelo Super Admin para cada empresa:

- chamadas simultaneas por agente disponivel;
- teto absoluto de chamadas simultaneas da empresa.

No simulador, varias tentativas podem ser abertas para um mesmo agente disponivel, mas somente uma delas pode ser atendida e entregue ao operador.

## Escopo

Esta etapa cobre:

- calculo da capacidade restante da empresa;
- criacao de lotes de tentativas por agente disponivel;
- escolha de uma tentativa vencedora por lote;
- cancelamento automatico das tentativas excedentes;
- respeito ao teto global entre todas as campanhas da empresa;
- registro do motivo tecnico de encerramento;
- indicadores de capacidade contratada e uso atual.

Nao fazem parte desta etapa:

- Asterisk, AMI, SIP, WebRTC ou Go Bridge;
- deteccao real de atendimento;
- pacing preditivo;
- AMD real;
- integracoes e webhooks;
- cobranca ou planos comerciais fixos.

## Regras de Negocio

### Capacidade por agente

Para cada agente elegivel, o sistema pode gerar ate
`max_calls_per_available_agent` tentativas no mesmo lote.

Um agente e elegivel quando:

- pertence a empresa e a uma equipe vinculada a campanha;
- possui tipo `agent`;
- esta ativo;
- esta com status `available`;
- nao possui chamada em andamento;
- nao possui chamada encerrada aguardando tabulacao.

Supervisores e administradores podem realizar chamadas manuais, mas nunca entram na capacidade de campanhas automaticas.

### Vencedor do lote

Cada lote pertence a um agente.

- A primeira tentativa valida do lote e considerada atendida no simulador.
- A tentativa vencedora fica `in_progress`, vinculada ao agente.
- O agente muda para `in_call`.
- As demais tentativas do lote sao encerradas como `cancelled`.
- As tentativas canceladas recebem `end_reason = answered_elsewhere`.
- Um agente nunca recebe mais de uma conversa ativa.

O simulador determina o vencedor imediatamente. Na futura telefonia real, essa decisao sera feita pelos eventos do gateway sem alterar as regras de capacidade.

### Teto global da empresa

O limite `max_concurrent_calls` e compartilhado por todas as campanhas da empresa.

Antes de criar um lote:

1. o sistema conta as chamadas `in_progress` da empresa;
2. calcula as vagas restantes no teto global;
3. limita o lote pelas vagas restantes;
4. impede novos lotes quando nao houver capacidade.

Quando `max_concurrent_calls` for nulo, o limite sera apenas a soma da capacidade dos agentes elegiveis.

Chamadas de outra empresa nunca interferem no calculo.

### Contatos e fila

Cada tentativa usa um contato distinto retornado pela fila da campanha. Se a fila tiver menos contatos que a capacidade calculada, o lote sera reduzido.

Tentativas vencedoras e canceladas contam como tentativa realizada no mailing, pois o numero foi selecionado para discagem. O cooldown continua sendo aplicado pela regra existente.

## Persistencia

A tabela `calls` recebera o campo anulavel:

```text
end_reason
```

Valores iniciais previstos:

- `answered_elsewhere`: outra tentativa do lote venceu;
- nulo: chamadas que nao possuem motivo tecnico de encerramento.

As tentativas canceladas terao:

- `status = cancelled`;
- `started_at` preenchido;
- `ended_at` preenchido;
- `answered_at = null`;
- `end_reason = answered_elsewhere`;
- campanha, contato, empresa e agente do lote registrados.

O campo sera textual para permitir novos motivos tecnicos na futura integracao de telefonia sem uma migracao para cada motivo.

## Componentes

### AgentCapacityService

Responsavel por:

- contar agentes elegiveis da campanha;
- obter a capacidade contratada por agente;
- contar chamadas ativas da empresa;
- calcular a capacidade total e as vagas restantes;
- aplicar o teto absoluto da empresa.

O servico nao cria chamadas nem altera status.

### SimulatedDialerService

Responsavel por:

- validar campanha e agenda operacional;
- selecionar agentes elegiveis;
- formar um lote por agente;
- buscar contatos distintos;
- criar a tentativa vencedora;
- criar e encerrar as tentativas excedentes;
- atualizar mailing e status do agente.

Cada execucao sera protegida por transacao e bloqueio no registro da empresa ou licenca. O calculo da capacidade sera refeito dentro da transacao para evitar que duas execucoes concorrentes ultrapassem o teto.

## Interface

O formulario do Super Admin continuara permitindo escolha livre de:

- chamadas por operador disponivel;
- chamadas simultaneas maximas.

Os indicadores da empresa devem mostrar:

- limite contratado por operador;
- chamadas ativas no momento;
- teto simultaneo da empresa, quando configurado.

Nao serao adicionadas paginas novas. As informacoes entram nas telas existentes.

## Relatorios

Chamadas canceladas permanecem no historico analitico para auditoria do lote, mas:

- nao contam como chamadas atendidas;
- nao entram em TMA;
- nao entram em conversao;
- exibem o motivo de encerramento de forma legivel.

Os indicadores atuais que contam todas as tentativas continuarao incluindo esses registros quando o objetivo for volume de discagem.

## Erros e Concorrencia

- Campanha inativa ou fora da agenda nao cria lote.
- Licenca sem capacidade disponivel retorna zero chamadas criadas.
- Falta de agente ou contato retorna zero sem erro.
- Uma falha durante o lote reverte chamadas, tentativas e mudanca de status.
- O bloqueio transacional serializa execucoes concorrentes da mesma empresa.
- Empresas diferentes podem processar campanhas em paralelo.

## Testes

Os testes automatizados devem comprovar:

1. dois agentes com limite tres geram dois vencedores e quatro canceladas;
2. somente uma chamada por agente permanece `in_progress`;
3. excedentes usam `answered_elsewhere`;
4. teto global reduz o lote;
5. chamadas ativas de outra campanha consomem o teto da empresa;
6. chamadas de outra empresa nao consomem o teto;
7. poucos contatos reduzem o lote;
8. agente ocupado ou aguardando tabulacao nao recebe lote;
9. supervisor e administrador nao recebem lote;
10. execucoes concorrentes nao excedem o teto;
11. relatorios distinguem tentativa cancelada de chamada atendida;
12. indicadores exibem contratado versus uso atual.

## Criterios de Aceitacao

- O multiplicador contratado deixa de ser apenas informativo e controla tentativas reais no simulador.
- Nenhum agente possui mais de uma chamada ativa.
- A soma de chamadas ativas da empresa nunca ultrapassa `max_concurrent_calls`.
- Campanhas concorrentes compartilham corretamente o teto da empresa.
- Toda tentativa excedente possui estado e motivo auditaveis.
- O comportamento permanece isolado por empresa.
- A suite de testes, o build frontend e a formatacao passam no ambiente Docker do projeto.
