# Artefatos Imutaveis de Producao

Data: 2026-07-10

## Objetivo

Criar os artefatos de container para uma futura publicacao sem alterar ou iniciar o ambiente de desenvolvimento.

## Artefatos

- `.docker/app/Dockerfile.production`: imagem multiestagio com Composer sem dependencias de desenvolvimento e build Vite interno;
- `.docker/nginx/Dockerfile.production`: configuracao Nginx incorporada na imagem;
- `docker-compose.production.yml`: servicos sem bind mounts de codigo, `vendor` ou `node_modules`;
- volumes nomeados somente para storage, MariaDB e Redis;
- banco e Redis sem portas expostas no host;
- app, fila e scheduler usando a mesma imagem imutavel.

## Runtime

O entrypoint de producao exige `APP_KEY` e gera caches de configuracao, rotas e views. Migrations nao sao executadas automaticamente para evitar alteracao de banco durante o start do container.

## Segredos e HTTPS

O Compose recebe variaveis por um arquivo externo definido em `ENV_FILE`, que por padrao e `.env.production` e permanece ignorado pelo Git. O exemplo nao contem segredos reais. HTTPS deve ser terminado por um proxy reverso externo antes da publicacao.

## Validacao

- `docker compose --env-file .env.production.example -f docker-compose.production.yml config` passou;
- a imagem Nginx foi construida com sucesso;
- a build completa da imagem PHP foi iniciada, mas o Docker Desktop local permaneceu sem progresso mensuravel durante a compilacao de extensoes/dependencias e foi encerrado sem iniciar containers de producao.

## Proximo passo tecnico

Reexecutar a build da imagem `app` quando o Docker Desktop estiver responsivo, e entao validar o Compose de producao em uma rede/porta isolada antes de qualquer deploy.
