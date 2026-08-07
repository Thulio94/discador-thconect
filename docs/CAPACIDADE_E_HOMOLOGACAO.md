# Capacidade, protecao e homologacao

## Escopo inicial

- 200 usuarios em operacao.
- Ate 10 tentativas configuradas por usuario disponivel.
- 500 canais simultaneos no cluster.
- 500 mil contatos ativos por empresa.
- 24 meses de chamadas no banco operacional.
- Gravacoes e arquivos antigos em armazenamento S3 compativel.

O teto individual nao garante dez chamadas para cada usuario. A capacidade autorizada e sempre o menor valor entre agente, empresa, rota, no Asterisk e cluster. Com 200 usuarios e 500 canais, a media sob saturacao e 2,5 canais por usuario.

## Protecoes implementadas

- Configuracao global exclusiva do SuperAdmin em `/super-admin/capacity`.
- Reserva serializada por lock distribuido e registro duravel em `channel_reservations`.
- Tetos globais, por empresa, rota e no Asterisk.
- Distribuicao por peso quando o cluster entra na faixa de alerta.
- Bloqueio de novas origens sem desligar chamadas existentes.
- Backpressure que recusa novos imports e relatorios na faixa critica.
- Reducao de lotes do discador para 50% no alerta e 25% no critico.
- Reconciliacao de reservas expiradas a cada minuto.
- Auditoria central para limites atingidos.

## Filas

| Fila | Finalidade | Prioridade operacional |
| --- | --- | --- |
| `telephony` | Reservas e orquestracao de chamadas | Maxima |
| `notifications` | Alertas operacionais | Alta |
| `imports` | CSV/TXT em segundo plano | Media |
| `reports` | Exportacoes | Baixa |
| `media` | Envio de gravacoes | Baixa |
| `default` | Demais tarefas | Normal |

Cada worker possui timeout, memoria e reciclagem proprios no Docker Compose. Em producao, Redis e workers devem ficar fora dos nos web.

## Telefonia

O ambiente local possui um no Asterisk de 170 canais para desenvolvimento. A homologacao de 500 canais requer quatro nos independentes, inicialmente com 170 canais por no. Rotas apontam para um no e possuem teto contratado proprio. O painel mostra uso, compromisso por empresa e estado.

Falha de um no deve retirar o no de `online`. Novas chamadas deixam de usar suas rotas; chamadas existentes nao sao encerradas pela aplicacao.

## Banco e relatorios

- PHP-FPM: 24 filhos por no e reciclagem apos 500 requisicoes.
- MariaDB local: buffer pool de 512 MB e slow query log acima de 1 segundo.
- Producao: banco dedicado, buffer pool conforme RAM e `DB_READ_HOST` apontando para replica.
- Tabelas interativas: 25 linhas.
- Consultas interativas: periodo maximo configuravel, padrao de 90 dias.
- CSV usa cursor e escrita progressiva.
- XLSX e PDF respeitam limites de linhas por empresa.
- Chamadas antigas sao arquivadas mensalmente em JSONL com SHA-256.

## Gravacoes

No desenvolvimento, `RECORDINGS_DISK=recordings` mantem o volume local. Em producao, use `RECORDINGS_DISK=s3` e configure as variaveis AWS/S3. O worker `media` transfere arquivos depois da chamada; o Asterisk nao faz compressao pesada durante o audio. A limpeza respeita `recording_retention_days` da empresa.

## Metricas

`GET /internal/metrics` expoe formato Prometheus e exige `Authorization: Bearer <METRICS_TOKEN>`. O endpoint informa canais globais, canais por no, empresas e tamanho das filas. CPU, memoria, disco e rede devem vir do exporter do host ou Kubernetes, nao do processo PHP.

## Homologacao obrigatoria

O teto nao deve ser aumentado para 500 em producao antes dos testes abaixo:

1. SIPp com rampa progressiva de 50, 100, 250, 425 e 500 canais.
2. AMD, gravacao, eventos e cancelamento das tentativas perdedoras ativos.
3. 200 sessoes autenticadas com supervisao aberta e Reverb conectado.
4. Importacao de 100 mil linhas e exportacoes concorrentes durante a carga.
5. Oito horas de carga continua.
6. Falha controlada de um no Asterisk.
7. Erros HTTP abaixo de 1%, paginas comuns p95 abaixo de 500 ms e acoes do agente abaixo de 250 ms.
8. Supervisao atualizada em ate 5 segundos, CPU abaixo de 70% e memoria abaixo de 75%.

O Docker Desktop valida integracao funcional, mas nao homologa 500 canais nem substitui quatro servidores Asterisk, rota SIP liberada e gerador SIP externo.

## Comandos operacionais

```bash
php artisan calls:archive-old
php artisan reports:purge-expired-assets
php artisan queue:monitor redis:telephony,redis:imports,redis:reports --max=100
php artisan schedule:list
```

Antes de producao, configure backups, restauracao testada, lifecycle S3, TLS, secrets externos e alertas Grafana.
