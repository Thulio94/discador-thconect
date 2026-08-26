# Homologacao VoIP na VPS

Este roteiro cobre o primeiro ambiente em uma unica VPS, com ate 5 agentes e
ate 5 tentativas por agente disponivel. O teto teorico e de 25 chamadas
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
5. Repita com 5 chamadas simultaneas.
6. Suba em etapas para 10, 15 e 25 chamadas, observando CPU, memoria, perda,
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
- os cinco ramais permanecerem registrados;
- 25 chamadas simultaneas passarem no teste gradual sem audio unilateral;
- supervisao, DTMF, desligamento, protocolo e gravacao estiverem corretos;
- reinicios recuperarem a configuracao da rota;
- backup do banco e dos volumes tiver restauracao testada.
