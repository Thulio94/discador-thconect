# Rota Asterisk AMI

## Objetivo

O Discador THCONECT esta preparado para conectar cada empresa a uma rota VoIP/Asterisk independente. Nenhuma credencial de telefonia vem no repositorio. Ate a configuracao ser validada, o gateway permanece no simulador local.

## Componentes entregues

- Configuracao AMI por empresa em `Telefonia`, com host, porta, usuario, segredo, tronco, Caller ID, canal e dialplan.
- Usuario e segredo AMI criptografados com a `APP_KEY` do Laravel e nunca exibidos apos o salvamento.
- Servico Docker `asterisk-bridge`, escrito em Go, isolado da interface publica.
- Sessao AMI persistente por empresa para enviar `Originate` e `Hangup`.
- Webhook assinado por HMAC para registrar eventos de originate, atendimento, AMD e hangup no historico da chamada.
- Healthcheck da ponte no monitor de status do Super Admin.
- Gateway com fallback automatico para o simulador enquanto a rota nao estiver habilitada e testada.

## Credenciais necessarias por empresa

1. Host ou IP acessivel do Asterisk AMI.
2. Porta AMI, normalmente `5038`.
3. Usuario AMI com permissoes de `originate`, `call`, `logoff` e eventos de chamada.
4. Segredo AMI.
5. Template do canal, por exemplo `PJSIP/{phone}@tronco-principal`.
6. Contexto e extensao do dialplan que atenderao o Originate, normalmente `discador-outbound` e `s`.
7. Caller ID autorizado pela rota.

## Segredos de infraestrutura

Antes de habilitar qualquer rota real, gere dois valores aleatorios diferentes e defina-os no ambiente Docker:

```env
ASTERISK_BRIDGE_TOKEN=token-interno-longo-e-aleatorio
ASTERISK_BRIDGE_WEBHOOK_SECRET=segredo-hmac-longo-e-aleatorio
```

O token autentica Laravel e bridge. O segundo segredo assina os eventos enviados da bridge para o Laravel. Ambos devem existir em todos os containers antes de usar o botao `Testar AMI`.

## Fluxo de ativacao

1. Atualize as imagens e aplique as migrations.
2. Configure os dois segredos de infraestrutura acima.
3. Abra `Telefonia` dentro da empresa e escolha `Asterisk AMI / rota VoIP`.
4. Preencha as credenciais, canal e dialplan; marque `Habilitar chamadas reais apos o teste`.
5. Salve e use `Testar AMI`.
6. Somente quando o status ficar `Conectada`, inicie uma chamada controlada.

## Formato de discagem por rota

- Com `Tech`, o servidor envia `Tech + 55 + DDD + telefone`.
- Em uma rota liberada por IP que nao use `Tech`, deixe esse campo vazio e informe o prefixo nacional obrigatorio. Por exemplo, com prefixo `0`, o destino enviado sera `0 + DDD + telefone`.
- O navegador continua enviando apenas o telefone normalizado. A montagem do destino ocorre no servidor, sem expor configuracao tecnica ou credenciais SIP aos usuarios.

## AMD

Para a campanha com AMD `Asterisk / rota`, o dialplan ou o provedor deve emitir um UserEvent AMI com este formato:

```asterisk
same => n,UserEvent(THCONECTAMD,CallId:${THCONECT_CALL_ID},Result:${AMDSTATUS})
```

O resultado sera gravado em `calls.amd_result`. A regra de negocio deve considerar atendimento apenas quando a chamada for direcionada a um agente ou ramal, como ja definido no discador.

## Rede e seguranca

- A porta `5038` deve ser permitida somente entre `asterisk-bridge` e o Asterisk.
- A porta da bridge nao e publicada no host; ela pertence apenas a rede Docker.
- Mantenha `APP_KEY`, token e segredo HMAC fora do Git.
- Restrinja o usuario AMI ao minimo de permissoes necessario e a IPs conhecidos.
