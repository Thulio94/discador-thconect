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
- Na pagina de telefonia do administrador, a versao atualmente publicada ainda
  exibia o identificador TECH da rota. O HTML foi corrigido localmente para
  mostrar apenas operadora e nome; um teste de regressao comprova que TECH e
  host do tronco nao aparecem. Essa protecao so estara ativa na VPS depois do
  deploy desta versao.
- A tela do agente agora oferece nova tentativa quando a permissao de microfone
  fica pendente ou falha. Tentativas antigas nao podem iniciar registro SIP
  depois de uma nova tentativa ou do fechamento do painel.
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
