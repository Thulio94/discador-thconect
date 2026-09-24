# Homologacao VoIP na VPS

Este roteiro cobre o piloto em uma unica VPS, com ate 3 agentes e
ate 5 chamadas por agente disponivel. O teto teorico e de 15 chamadas
simultaneas, mas a liberacao deve ser gradual e depende da capacidade contratada
na operadora SIP.

## Estado atual

O navegador do agente ja registra um endpoint PJSIP/WebRTC tecnico no Asterisk
por WSS no mesmo dominio da aplicacao. O endpoint usa nome isolado por empresa,
credencial aleatoria criptografada, DTLS-SRTP, ICE, contato unico e expiracao
curta renovada enquanto a tela do agente permanece aberta. O navegador recebe
somente chamadas do Asterisk; ele nao pode escolher rota, canal `TECH` ou
originar SIP diretamente.

O bloqueio restante e operacional: audio real, NAT, DTMF, gravacao e eventos
precisam ser homologados com HTTPS, uma rota SIP real e um numero de teste
controlado. Ate essa homologacao, o ambiente nao deve receber trafego de
producao.

## Variaveis obrigatorias

Crie `.env.production` a partir de `.env.production.example` e substitua todos
os valores `CHANGE_ME`. Use valores diferentes para banco, Redis, Reverb, AMI,
bridge e webhook.

Configure ainda:

- `APP_URL`, `REVERB_HOST` e `REVERB_ALLOWED_ORIGINS` com o dominio HTTPS real;
- `WEBRTC_STUN_URL` com um servidor STUN aprovado, quando necessario, e
  `ASTERISK_STUN_ADDRESS` com o host correspondente sem o prefixo `stun:`;
- `ASTERISK_EXTERNAL_MEDIA_ADDRESS` e
  `ASTERISK_EXTERNAL_SIGNALING_ADDRESS` com o IP publico fixo da VPS;
- `ASTERISK_RTP_START=10000` e `ASTERISK_RTP_END=10199`;
- `ASTERISK_ENABLE_TLS=true` somente quando a operadora fornecer SIP TLS com
  certificado valido;
- `ASTERISK_BRIDGE_STATE_KEY` com uma chave Base64 de 32 bytes.

Gere a chave de estado sem grava-la no historico compartilhado:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

## Firewall e rede

No Easypanel e no firewall da VPS:

- publique `80/tcp` e `443/tcp` para o proxy HTTPS;
- publique `10000-10199/udp` para RTP;
- nao publique AMI `5038/tcp`, bridge `8090/tcp`, MySQL, Redis ou Reverb;
- nao publique `5060` ou `5061` quando o Asterisk apenas inicia conexoes para a
  operadora;
- permita a sinalizacao de saida para o host e porta SIP da operadora;
- se a operadora exigir trafego SIP de entrada por IP, libere a porta somente
  para os CIDRs oficiais dela.

Solicite a operadora:

- liberacao do IP publico da VPS;
- host, porta, transporte e modo de autenticacao;
- codecs autorizados, preferencialmente `alaw,ulaw`;
- DTMF, preferencialmente RFC 4733;
- formato do numero e do identificador de chamada;
- limite de canais e de CPS;
- IPs de sinalizacao e midia para regras de firewall.

## Implantacao

Valide o arquivo antes de subir:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml config --quiet
docker compose --env-file .env.production -f docker-compose.production.yml up -d --build
docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan optimize
```

Confirme a saude dos servicos:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml ps
docker compose --env-file .env.production -f docker-compose.production.yml exec asterisk asterisk -rx "core show version"
docker compose --env-file .env.production -f docker-compose.production.yml exec asterisk asterisk -rx "pjsip show transports"
docker compose --env-file .env.production -f docker-compose.production.yml exec asterisk asterisk -rx "pjsip show registrations"
docker compose --env-file .env.production -f docker-compose.production.yml exec asterisk asterisk -rx "pjsip show endpoints"
docker compose --env-file .env.production -f docker-compose.production.yml exec asterisk asterisk -rx "dialplan show thconect-outbound"
```

No Easypanel, publique o `nginx` pela rede externa `easypanel`; nao mapeie a
porta 80 diretamente no host. Ao executar este discador em paralelo com outro
PABX na mesma VPS, reserve um intervalo RTP exclusivo (por exemplo,
`12000-12199/udp`) e use exatamente o mesmo intervalo no firewall e nas
variaveis `ASTERISK_RTP_START` e `ASTERISK_RTP_END`.

Cadastre a rota no painel do super admin e use `Testar`. Esse teste valida AMI,
recarrega PJSIP e envia SIP OPTIONS, mas nao origina chamada nem prova que o
audio esta correto.

## Homologacao controlada

Chamadas reais exigem autorizacao explicita e um numero de teste controlado.
Execute nesta ordem:

1. Abra o painel do agente por HTTPS, autorize o microfone e confirme que
   `pjsip show endpoint tEMPRESA-eRAMAL` mostra um contato WebSocket.
2. Faca uma chamada e valide audio nos dois sentidos, DTMF e desligamento por
   cada lado.
3. Confirme status em tempo real no agente e na supervisao.
4. Confirme protocolo, duracao, origem do desligamento e arquivo de gravacao
   maior que 44 bytes.
5. Repita com ate 5 chamadas por agente disponivel.
6. Suba em etapas para 5, 10 e 15 chamadas, observando CPU, memoria, perda,
   jitter, fila e recusas SIP.
7. Execute queda e retorno do bridge e do Asterisk e confirme que rotas e
   estados sao recuperados sem duplicar chamadas.

Interrompa a expansao se houver audio unilateral, perda relevante, respostas
SIP 5xx, aumento continuo de memoria, gravações incompletas ou divergencia de
estado. Chamadas existentes nunca devem ser encerradas para aplicar um novo
limite.

## Criterio de aceite

O piloto pode ser liberado somente quando:

- HTTPS e WebSocket funcionarem sem conteudo inseguro;
- nenhum segredo ou porta interna estiver publico;
- os tres ramais de teste permanecerem registrados;
- ate 15 chamadas simultaneas passarem no teste gradual sem audio unilateral;
- supervisao, DTMF, desligamento, protocolo e gravacao estiverem corretos;
- reinicios recuperarem a configuracao da rota;
- backup do banco e dos volumes tiver restauracao testada.

## Verificacao do piloto em 24/09/2026

- Login de teste do agente: autenticou e abriu `/tenant/agent`; o link do
  dashboard abriu a visao individual, sem 403.
- `/up` respondeu HTTP 200. Os conteineres do Compose isolado do discador
  estavam em execucao; nginx, Asterisk, app, MySQL e Redis tinham health check
  saudavel. O coletor de logs do Easypanel retornou HTTP 400, portanto os
  logs de chamadas nao puderam ser auditados por esse canal.
- A tela de supervisao mostrava mais de 497 mil horas em `Offline`. A causa
  confirmada era o formatador JavaScript converter `null` para Unix epoch,
  embora o backend ja enviasse tempo Offline zerado. A regressao agora exige
  `00:00:00` para inicio nulo.
- O navegador de automacao passou de `Preparando microfone...` para
  `Conectado e pronto para chamadas` depois da permissao. O estado de registro
  SIP continua sem prova por CLI; conferir o contato PJSIP em homologacao.
- O login de administrador de teste abriu dashboard, usuarios, equipes, perfis,
  listas, campanhas, agente, supervisao, telefonia, configuracoes e as nove
  visoes de relatorios sem resposta 500/403. As leituras foram somente consulta;
  nenhuma campanha, equipe, usuario, rota ou configuracao foi alterada.
- O commit `67c4868` foi publicado na `main` e implantado no Compose
  `pabx-thconect/discador-piloto-teste`; a acao Easypanel concluiu com sucesso.
  O endpoint `/up` respondeu HTTP 200 e os 13 conteineres do piloto estavam
  ativos; app, nginx, Asterisk, bridge, MySQL e Redis estavam saudaveis.
- Depois do deploy, a pagina de telefonia da empresa mostra somente nome e
  operadora. O identificador TECH e o host tecnico nao aparecem no texto
  renderizado.
- Os cinco contadores Offline da supervisao agora exibem `00:00:00` apos
  recarga, sem o valor de centenas de milhares de horas.
- Foi detectada uma divergencia operacional ainda aberta: o resumo da
  supervisao indica cinco agentes em pausa, mas os cinco cartoes mostram
  `Offline`; o filtro `Em pausa` nao encontra agentes. A escuta Spy tambem esta
  desabilitada com a mensagem de que falta atribuir e testar uma rota Asterisk.
  Nao foi alterada a presenca dos agentes nem a configuracao da rota.
- A versao publicada oferece nova tentativa quando a permissao de microfone
  fica pendente ou falha. Testes comprovam que tentativas antigas nao iniciam
  registro SIP depois de uma nova tentativa ou do fechamento do painel; a
  verificacao visual desse botao no login de agente ainda esta pendente.
- O relatorio de aproveitamento exibiu 10 chamadas historicas, zero atendidas,
  zero entregues e uma tabulada; os estados incluem `wrap_up_timeout` e
  `asterisk_event_timeout`. Como os logs do Easypanel nao puderam ser lidos, a
  causa dessas linhas historicas ainda precisa de correlacao com AMI/bridge.
- Sem numero de destino controlado confirmado, nao foi originada chamada real.
  Portanto SIP INVITE/200 OK, RTP bidirecional, AMD da rota, entrega ao agente,
  MixMonitor, historico e distribuicao Power permanecem sem homologacao real.
- Testes locais apos as correcoes: 356 PHP aprovados, 1 ignorado; 40 frontend
  aprovados; build Vite, invariantes PBX e `npm audit --omit=dev` aprovados
  (zero vulnerabilidades). O PHP local precisou de
  512 MB para o teste de exportacao XLSX.

**Decisao:** o piloto nao esta homologado para producao. Repetir as etapas
controladas acima em um navegador com microfone liberado e com destino de
teste autorizado; nao usar contatos do mailing como destino de teste.

## Continuacao da validacao em 24/09/2026

- A conta administrativa de teste autenticou e abriu Dashboard, Campanhas,
  Usuarios, Equipes, Relatorios e Telefonia sem erro visivel. A rota na tela
  da empresa exibiu apenas nome e operadora; nenhuma alteracao foi salva.
- A campanha `Energia - Bahia` segue ativa com 8.780 contatos pendentes. O
  agente permaneceu Offline durante a verificacao; nenhuma chamada foi
  originada.
- A Supervisao ainda exibiu resumo de 5 agentes em pausa, enquanto os cinco
  cartoes apareceram como Offline. O dashboard, em uma leitura proxima, mostrou
  zero em disponiveis, chamadas e pausas. Essa divergencia operacional exige
  nova confirmacao apos o proximo deploy e leitura consistente do estado.
- Correcoes locais em `SupervisionSnapshot` calculam os contadores a partir da
  mesma colecao carregada para os cartoes. Um teste de regressao injeta uma
  mudanca de status durante a consulta e verifica que a mesma resposta nao
  mistura snapshots. O teste focado passou: 10 testes e 91 assercoes.
- Validacao local: PHPUnit completo com PHP 8.5 e 512 MB passou (358 testes,
  357 aprovados, 1 ignorado, 2.090 assercoes); frontend 40/40; build Vite,
  invariantes PBX e auditoria npm de dependencias de producao passaram.
- O primeiro comando Artisan para a suite completa iniciou o gerador XLSX com
  limite de 128 MB e terminou por falta de memoria. A execucao direta do
  PHPUnit com 512 MB passou. O teste Go do bridge nao foi executado: Go nao
  esta instalado/disponivel neste host.
- O commit `23cd585` foi enviado a `main` e o Easypanel concluiu o deploy com
  rebuild exclusivamente em `pabx-thconect/discador-piloto-teste` (acao
  `cmug0uybo013j06qd703h6m3t`, status `done`). O log marcou app, MySQL, Redis e
  Asterisk como saudaveis e os componentes restantes como iniciados. O servico
  `pabx-thconect1` nao foi alvo do deploy.
- Depois do deploy, `/up` e `/login` responderam HTTP 200. Nao foi possivel
  reabrir uma aba autenticada para conferir visualmente os cartoes e filtros;
  portanto, a divergencia da Supervisao ainda nao esta confirmada como
  resolvida em producao. O coletor de logs do Easypanel continua retornando
  HTTP 400.
- Sem sessao, `/dashboard`, `/tenant/agent`, `/tenant/campaigns` e
  `/tenant/supervision` retornaram HTTP 302 para `/login`, confirmando o
  middleware de autenticacao nas rotas administrativas e do agente.
- A URL WSS configurada no Easypanel (`wss://ws.discador-teste.169.58.168.174.nip.io/ws`)
  aceitou handshake TLS/WebSocket com o subprotocolo SIP e o Origin do app; a
  conexao abriu e encerrou normalmente. Um probe WebSocket generico sem
  subprotocolo `sip` recebeu HTTP 400, comportamento esperado para este
  endpoint, e nao foi contado como falha. Isso valida somente o transporte
  WSS; registro SIP, autenticacao do ramal e SDP/RTP ainda nao foram provados.
- O `getComposeIssues` do Easypanel sinaliza publicacao de portas no Asterisk
  tanto do piloto quanto do servico principal, e no TURN do principal. O aviso
  e generico e nao prova colisao. A configuracao efetiva do piloto anuncia
  RTP em `12200-12399/udp`; as variaveis expostas pelo principal nao incluem
  intervalo RTP, portanto nao foi possivel comprovar que os ranges nao se
  sobrepoem. Nao alterei portas, firewall ou o servico principal. Antes de
  homologar audio, conferir o `rtp.conf`/mapeamento ativo do Asterisk principal
  e comparar o intervalo completo no host.
- Seguem sem prova real: SIP INVITE/200 OK, audio bidirecional, entrega Power,
  DTMF, desligamento, gravacao MixMonitor e conciliacao AMI/bridge. O coletor
  de logs Easypanel havia retornado HTTP 400 na verificacao anterior. Exigir
  destino de teste controlado e autorizacao antes de originar chamadas.

**Decisao atualizada:** o ajuste foi implantado no piloto isolado e os
healthchecks HTTP passaram, mas a correcao visual da Supervisao ainda precisa
ser confirmada autenticada. O fluxo de telefonia tambem nao foi homologado.
Nao liberar para producao ate repetir a validacao administrativa na VPS e
concluir a homologacao controlada de chamadas.

## Validacao administrativa em 24/09/2026

- O login administrativo de teste autenticou e abriu Dashboard, Usuarios,
  Equipes, Perfis, Listas, Campanhas, Agente, Supervisao, Relatorios,
  Telefonia, Configuracoes e Integracoes sem 403/500 visivel. Os modulos foram
  apenas consultados; nenhum cadastro, estado ou configuracao foi alterado.
- As nove visoes de relatorio carregaram. A rota `report=overview` retornou
  404 porque nao e uma opcao valida do catalogo; nao e um relatorio suportado.
- A aba Historico de exportacoes apresentou erros Alpine: `@js($initialTab)`
  chegou literalmente no atributo `x-data` do componente Blade, impedindo a
  inicializacao das abas. A view passou o estado para um elemento HTML interno
  e foi coberta por teste para as abas `files` e `schedules`. A suite focada de
  relatorios passou (18 testes, 122 assercoes) e `npm run build` passou.
- A campanha `Energia - Bahia` aparece ativa, mas fora da agenda configurada;
  portanto nao e elegivel para discagem neste momento. A verificacao nao
  iniciou nem pausou campanha.
- O perfil Administrador aparecia com zero permissoes associadas. A auditoria
  confirmou que `company_admin` com papel `is_admin` recebe acesso total pela
  regra de autorizacao, enquanto outros tipos associados ao papel continuam
  sujeitos as permissoes individuais. A tela agora explica esse acesso total
  na listagem e nos detalhes, sem alterar a matriz de autorizacao. Teste RBAC:
  6 testes/26 assercoes; suite completa: 360 testes, 359 aprovados, 1
  ignorado, 2.100 assercoes. A nova apresentacao ainda depende de confirmacao
  visual pos-deploy.
- O teste de invariantes PBX via `scripts/verify-pbx-invariants.ps1 -RunTests`
  nao executou por falta do Docker CLI neste host. A correcao da tela de
  exportacoes foi publicada exclusivamente em
  `pabx-thconect/discador-piloto-teste` (acao
  `cmug1tb1c015d06qdhfz522iu`, status `done`). Pos-deploy, as duas abas
  alternaram corretamente, nao houve novos erros Alpine e `/up` respondeu
  `Application up`. O ajuste de apresentacao RBAC descrito acima ainda sera
  implantado no piloto. O servico principal `pabx-thconect1` permanece fora
  do escopo.

## Validacao do agente 1002 em 24/09/2026

- O login `energia1` foi redirecionado para `/tenant/agent`, com equipe
  Energia 01 e ramal 1002. A pagina apresentou os controles e o historico sem
  erro 403/500.
- O microfone ja estava autorizado no navegador. A tela passou de
  `Preparando microfone...` para `Conectado e pronto para chamadas`. No codigo,
  esse texto corresponde ao estado `registered`, definido pelo evento
  `registered` do JsSIP; portanto, nesta sessao o endpoint WebRTC registrou no
  Asterisk. O estado permaneceu pronto durante a verificacao.
- O status foi alternado de Offline para Disponivel e de volta a Offline sem
  recarregar a pagina; o timer zerou ao retornar offline. Nenhuma chamada foi
  originada. A campanha continua ativa, mas fora da agenda, sem contato
  entregue ao agente.
- O log via `queryComposeServiceLogs` continuou indisponivel (HTTP 400). O
  monitor do Easypanel listou os containers do piloto, inclusive Asterisk,
  asterisk-bridge, Reverb e `queue-telephony`, mas metricas de container nao
  comprovam sinalizacao de chamada ou audio.
- Ainda falta uma chamada de teste para numero controlado: SIP INVITE,
  resposta da operadora, 200 OK/entrega ao agente, audio bidirecional, DTMF,
  desligamento e arquivo MixMonitor nao foram homologados. Nao usar numeros do
  mailing como destino de teste.

**Estado:** login e registro do ramal foram confirmados; o ciclo de chamada e
midia permanece pendente. O agente foi devolvido ao estado Offline. Nao
aprovar producao ate concluir a chamada controlada, correlacionar logs e
validar a gravacao.
