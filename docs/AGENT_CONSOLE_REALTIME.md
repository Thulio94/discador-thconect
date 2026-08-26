# Console do agente em tempo real

## Escopo implementado

- A tela existente do agente foi preservada. Nenhuma segunda interface ou base de frontend foi criada.
- Mudancas de presenca e de chamada sao transmitidas pelo Laravel Reverb no canal privado do usuario.
- Um fallback leve consulta somente uma assinatura de estado a cada cinco segundos. A pagina completa so e atualizada quando o estado realmente mudou.
- Formularios de tabulacao em preenchimento nao sao descartados por uma atualizacao em tempo real. O agente recebe uma acao explicita para atualizar depois de concluir ou revisar os dados.
- Chamadas nos estados `dialing`, `ringing` e `in_progress` sao consideradas ativas em todo o sistema.
- Eventos recebidos do bridge Asterisk sao registrados de forma imutavel e idempotente em `call_events`.
- O historico do agente usa o snapshot da chamada quando o contato, a lista ou a campanha original nao estiver mais disponivel.
- O ramal do navegador usa JsSIP sobre o caminho WSS `/sip-ws`, provisionado pelo bridge em um endpoint tecnico isolado por empresa.
- A credencial SIP nunca aparece em HTML, logs ou serializacao do usuario; ela e entregue apenas pela sessao autenticada, com `Cache-Control: no-store`.
- O endpoint expira automaticamente e e removido ao inativar o usuario ou regenerar o ramal.

## Contrato de estados

1. `available`: agente aguardando chamada.
2. `dialing`: origem solicitada ao provedor.
3. `ringing`: destino chamando.
4. `in_progress`: chamada atendida e entregue ao ramal.
5. `wrap_up`: chamada encerrada e aguardando tabulacao.
6. `paused` ou `offline`: agente fora da distribuicao.

Uma falha de origem ou chamada nao atendida libera o agente. Uma chamada atendida entra em tabulacao depois do desligamento e retorna ao fluxo configurado quando a tabulacao for concluida.

## Separacao de responsabilidades

Reverb transporta somente eventos operacionais. O audio usa WebRTC diretamente
entre navegador e Asterisk, com DTLS-SRTP; credenciais SIP e audio nunca passam
pelo Reverb. A origem da chamada continua exclusiva do backend e do bridge, que
validam empresa, rota, ramal e endpoint tecnico antes de enviar qualquer acao
ao AMI.

A implementacao local foi validada com criacao e remocao de endpoint sintetico
e handshake SIP WebSocket. A liberacao de producao ainda exige homologacao de
audio bidirecional e gravacao com uma chamada real autorizada.

## Operacao

Depois de alterar rotas, eventos ou listeners, recicle `app`, `reverb` e os workers persistentes. Limpar apenas o cache do Laravel nao substitui a reciclagem de processos PHP-FPM que ja carregaram o mapa antigo de rotas.
