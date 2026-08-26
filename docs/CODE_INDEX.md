# Indice vetorial do codigo

O projeto possui um indice vetorial local para localizar rapidamente controllers,
services, models, rotas, views, migrations, testes e documentacao relacionados a
uma funcionalidade.

## Criar ou atualizar

```bash
npm run code:index
```

O arquivo e criado em `.code-index/code-index.json`. Esse diretorio e ignorado
pelo Git e nao participa do Laravel, Docker ou ambiente de producao.

Reconstrua o indice depois de mudancas relevantes no codigo. A geracao indexa os
arquivos versionados e os novos arquivos nao ignorados pelo Git.

## Pesquisar

```bash
npm run code:search -- "status do agente na supervisao"
npm run code:search -- "limite de chamadas por empresa"
npm run code:search -- "download protegido de gravacao"
```

Cada resultado informa caminho, linha inicial, pontuacao, simbolo encontrado e
uma previa. O indice reconhece sinonimos do dominio em portugues e ingles, como
`ligacao/call`, `agente/agent`, `ramal/extension` e `empresa/tenant`.

## Privacidade e limites

- Todo o processamento e local e deterministico; nenhum trecho e enviado a API.
- `.env`, dependencias, logs, backups, gravacoes, storage e artefatos gerados sao
  excluidos.
- O mecanismo usa vetores TF-IDF com feature hashing. Ele e otimizado para a
  terminologia deste sistema, mas nao substitui a leitura do fluxo completo nem
  os testes antes de alterar telefonia, autenticacao ou isolamento SaaS.

## Diagnostico

```bash
node scripts/code-index.mjs stats
```
