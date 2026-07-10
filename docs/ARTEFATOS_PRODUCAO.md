# Artefatos de Producao

`docker-compose.production.yml` e um artefato de preparacao. Ele nao deve ser iniciado no computador de desenvolvimento com a porta 80 sem antes definir uma rede, dominio, HTTPS e segredos reais.

## Diferencas para desenvolvimento

- imagem PHP multiestagio com `composer install --no-dev` e build Vite;
- sem bind mounts de codigo, `vendor` ou `node_modules`;
- Nginx inclui sua configuracao na propria imagem;
- somente volumes nomeados para storage, MariaDB e Redis;
- MySQL e Redis nao publicam portas no host;
- fila e scheduler usam a mesma imagem imutavel da aplicacao.

## Validar o artefato

Copie `.env.production.example` para um arquivo local de validacao e preencha valores nao sensiveis de teste. Em seguida:

```powershell
$env:ENV_FILE = '.env.production.example'
docker compose --env-file .env.production.example -f docker-compose.production.yml config
docker compose --env-file .env.production.example -f docker-compose.production.yml build app nginx
```

Nao use o arquivo de exemplo como segredo de producao. Antes de qualquer publicacao, forneca `APP_KEY`, senhas de banco/Redis, dominio e certificado HTTPS por um cofre de segredos ou variaveis do ambiente de destino.

## HTTPS

O compose publica apenas HTTP interno. Em producao, coloque um proxy reverso com certificado valido na frente do Nginx e defina `APP_URL` com `https://`. Isso habilita cookies seguros e HSTS no aplicativo.
