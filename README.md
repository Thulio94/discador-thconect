# Discador THCONECT

Sistema SaaS independente para discagem, administracao de empresas, usuarios, equipes, licencas e notificacoes operacionais.

## Ambiente Local

O ambiente de desenvolvimento roda via Docker Desktop.

```bash
docker compose up -d
```

Aplicacao local:

```text
http://localhost:8087
```

Usuario inicial:

```text
admin@discador.local
password
```

## Identidade Visual

O painel usa a base visual THCONECT compartilhada com o CRM, com tokens proprios, logo `public/images/thconect-primary.png` e favicon `public/favicon.ico`.

## Navegacao pelo codigo

O indice vetorial local ajuda a localizar rapidamente as partes relacionadas de
uma funcionalidade sem enviar o codigo a servicos externos:

```bash
npm run code:index
npm run code:search -- "status do agente na supervisao"
```

Consulte `docs/CODE_INDEX.md` para detalhes e limites de seguranca.

## Homologacao VoIP

Antes de configurar uma rota real ou liberar chamadas na VPS, siga
`docs/VOIP_PRODUCTION_READINESS.md`. O roteiro inclui firewall, NAT/RTP,
segredos, verificacao PJSIP e a subida controlada de carga.
