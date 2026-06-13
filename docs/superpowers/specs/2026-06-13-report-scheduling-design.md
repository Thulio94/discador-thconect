# Agendamento de Relatorios - Design

Data: 2026-06-13

## Objetivo

Permitir que administradores da empresa cadastrem rotinas recorrentes de relatorios com formato, filtros e frequencia, mantendo a experiencia no painel atual de Relatorios e sem depender de integracoes externas nesta fase.

## Decisao de Escopo

Foram consideradas tres abordagens:

1. Laravel Scheduler com geracao e envio automaticos.
2. Cadastro recorrente com execucao simulada manual.
3. Apenas salvar filtros favoritos, sem frequencia ou execucao.

A abordagem escolhida e a segunda. Ela entrega o fluxo completo de configuracao e acompanhamento do agendamento, preserva o MVP simulado e deixa o modelo preparado para receber Laravel Scheduler, e-mail ou armazenamento de arquivos futuramente.

## Experiencia de Uso

Na tela de Relatorios sera adicionado o bloco `Agendamentos de relatorios`.

O usuario podera:

- criar um agendamento em modal;
- editar um agendamento em modal;
- escolher formato CSV, XLSX ou PDF;
- escolher frequencia diaria ou semanal;
- para frequencia semanal, escolher o dia da semana;
- definir o horario da rotina;
- aplicar os mesmos filtros disponiveis no relatorio atual;
- ativar ou inativar o agendamento;
- executar a rotina imediatamente;
- consultar a ultima execucao e sua quantidade de registros.

As operacoes retornarao ao painel de Relatorios com toast. Nao sera usado `javascript.alert` ou `window.confirm`.

## Modelo de Dados

Criar a tabela `report_schedules` com:

- `company_id`: empresa proprietaria;
- `user_id`: usuario que criou o agendamento;
- `name`: identificacao da rotina;
- `format`: `csv`, `xlsx` ou `pdf`;
- `frequency`: `daily` ou `weekly`;
- `weekday`: numero de 0 a 6 para frequencia semanal, nulo para diaria;
- `run_time`: horario planejado;
- `filters`: JSON com periodo relativo e filtros de agente, campanha, equipe, lista e tabulacao;
- `status`: `active` ou `inactive`;
- `last_run_at`: data e hora da ultima execucao simulada;
- `last_records_count`: quantidade de chamadas encontrada na ultima execucao;
- timestamps.

O periodo sera armazenado como `period_days`, com valores permitidos de 7, 15, 30, 60 ou 90 dias. A execucao calcula o intervalo terminando no momento atual, evitando datas absolutas vencidas.

## Regras

- Todos os dados devem ser isolados por `company_id`.
- Um usuario nao pode visualizar ou alterar agendamentos de outra empresa.
- `weekday` e obrigatorio somente para frequencia semanal.
- Agendamento inativo pode ser editado e reativado, mas nao pode ser executado.
- Executar agora consulta as chamadas usando os filtros salvos.
- A execucao atualiza `last_run_at` e `last_records_count`.
- A execucao cria uma entrada em `report_exports` com o formato do agendamento, filtros calculados e quantidade encontrada.
- Nesta fase a execucao nao gera, armazena ou envia um arquivo. Ela valida e registra a rotina simulada.

## Arquitetura

Criar `ReportSchedule` como modelo SaaS e `ReportScheduleController` para o CRUD e a execucao simulada. O controller de agendamentos reutilizara uma classe de servico pequena para calcular o periodo, montar a consulta e registrar a execucao.

O `ReportController@index` carregara os agendamentos da empresa para exibicao no painel. As rotas ficarao dentro do grupo tenant existente.

## Interface

O bloco exibira nome, formato, frequencia, horario, estado, filtros resumidos, criador e ultima execucao. Os comandos serao botoes compactos:

- editar;
- executar agora;
- ativar ou inativar.

Criacao e edicao usarao `x-ui.modal`. O formulario de frequencia mostrara o dia da semana apenas quando `weekly` estiver selecionado. A tela nao mudara de pagina durante a configuracao; apos o envio, o redirect volta para Relatorios.

## Tratamento de Erros

- Validacao Laravel para valores, relacionamentos e campos condicionais.
- IDs de filtros devem pertencer a empresa autenticada.
- Tentativas de acessar registros de outra empresa retornam 404.
- Execucao de agendamento inativo retorna erro de validacao via toast.

## Testes

Os testes de feature devem cobrir:

- exibicao do bloco e isolamento por empresa;
- criacao diaria e semanal;
- validacao de `weekday`;
- atualizacao de cadastro;
- ativacao e inativacao;
- bloqueio da execucao inativa;
- execucao simulada com filtros;
- atualizacao da ultima execucao;
- criacao correspondente no historico de exportacoes;
- protecao contra IDs de filtros e agendamentos de outra empresa.

## Fora do Escopo

- envio por e-mail;
- webhooks;
- armazenamento do arquivo gerado;
- destinatarios configuraveis;
- execucao automatica pelo Laravel Scheduler;
- integracoes com CRM;
- telefonia, Asterisk ou Go Bridge.

