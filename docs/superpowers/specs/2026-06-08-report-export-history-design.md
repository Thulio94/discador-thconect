# Historico de Exportacoes dos Relatorios

Data: 2026-06-08

## Objetivo

Registrar exportacoes de relatorios para auditoria operacional, mostrando quem exportou, qual formato foi usado, filtros aplicados e quantidade de registros.

## Escopo

- Criar tabela `report_exports`.
- Registrar exportacoes CSV, XLSX e PDF.
- Salvar usuario, empresa, formato, filtros e total de registros.
- Exibir historico recente na tela de relatorios.
- Manter isolamento por empresa.

## Fora de Escopo

- Armazenar o arquivo exportado.
- Reprocessar download antigo.
- Agendamento de relatorios.
- Auditoria global do super admin.

## Regras

- Cada exportacao bem-sucedida cria um registro.
- O historico da tela mostra apenas registros da empresa do usuario.
- Filtros devem ser salvos como JSON.
- O total de registros deve representar a query filtrada do relatorio.

## Verificacao

- Teste feature para registro CSV/XLSX/PDF.
- Teste de isolamento por empresa no historico exibido.
- Suite completa local e Docker.
