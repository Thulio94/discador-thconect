# Operacao Local e Preparacao para Producao

## Ambiente local

Suba os servicos com:

```powershell
docker compose up -d
docker compose ps
```

O endpoint de saude da aplicacao e `http://localhost:8087/up`. Os servicos `app`, `nginx`, `mysql` e `redis` possuem health checks; fila, scheduler e Reverb possuem reinicio automatico.

## Backup e restauracao

Crie um backup do MariaDB local:

```powershell
.\scripts\backup-mariadb.ps1
```

Os arquivos sao gravados em `storage/backups`, que nao deve ser versionado. Para restaurar um arquivo no banco local, use a confirmacao explicita:

```powershell
.\scripts\restore-mariadb.ps1 -BackupFile .\storage\backups\discador-thconect-AAAAMMDD-HHMMSS.sql -Force
```

Use `-Database nome_da_base` apenas para testar um backup em uma base temporaria; sem esse parametro, os scripts usam a base configurada no container MariaDB.

## Validacao antes de publicar

```powershell
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
npm run test:frontend
npm run build
```

Use `.env.production.example` como lista de variaveis. Gere `APP_KEY` exclusivamente no ambiente de destino, mantenha `APP_DEBUG=false`, use HTTPS e nunca envie arquivos `.env`, dumps ou segredos para o Git.

Telefonia real, Asterisk/AMI e integracoes externas continuam fora desta preparacao.
