# Execucao Automatizada de Relatorios Agendados

Data: 2026-07-10

## Objetivo

Executar no ambiente local os agendamentos de relatorio ativos, sem depender de acao manual e sem enviar arquivos para sistemas externos.

## Funcionamento

- comando `reports:run-scheduled` procura agendamentos ativos;
- execucao diaria ocorre apos o horario configurado;
- execucao semanal ocorre apos o horario configurado e somente no dia definido;
- um agendamento ja executado no dia nao e repetido;
- o runner existente atualiza ultima execucao, total de registros e historico de exportacao;
- frequencias futuras e agendamentos inativos sao ignorados.

## Concorrencia

Cada agendamento e recarregado com bloqueio de banco dentro de transacao antes da execucao. A agenda Laravel tambem usa `withoutOverlapping`, evitando duplicacao pelo processo scheduler local.

## Docker local

O servico `scheduler` executa `php artisan schedule:work` e reutiliza a mesma imagem PHP do servico `app`. O Laravel avalia o comando a cada minuto.

## Limites da etapa

Os arquivos nao sao enviados por e-mail, webhook ou integracao CRM. A rotina registra somente a execucao simulada e o historico ja existente.

## Verificacao

- comando executa rotinas diaria e semanal vencidas;
- horarios futuros e rotinas inativas permanecem sem execucao;
- segunda passagem na mesma janela nao cria nova exportacao;
- `docker compose ps scheduler` confirma o servico ativo;
- `php artisan schedule:list` mostra o comando a cada minuto.
