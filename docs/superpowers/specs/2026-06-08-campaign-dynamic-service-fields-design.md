# Spec - Campos Dinamicos por Campanha

Data: 2026-06-08

## Objetivo

Permitir que cada campanha configure campos proprios de atendimento para o agente preencher ao tabular a chamada.

## Decisoes

- A campanha guarda `service_fields_schema` como JSON.
- Cada campo possui chave, rotulo, tipo e obrigatoriedade.
- Tipos iniciais: texto, numero e data.
- O painel do agente renderiza o esquema da campanha quando existir.
- Campanhas sem esquema continuam usando os campos basicos atuais: documento, protocolo e valor.
- O backend valida campos obrigatorios e ignora chaves que nao pertencem ao esquema da campanha.

## Fora de Escopo

- Editor visual com arrastar/soltar.
- Campos condicionais.
- Mascara por tipo de documento.
- Campos por lista ou por equipe.
- Integracao CRM.

## Criterios de Aceite

- Administrador configura campos dinamicos dentro do modal de campanha.
- Campanha persiste somente campos ativos e validos.
- Listagem mostra resumo dos campos configurados.
- Painel do agente renderiza os campos da campanha.
- Campo obrigatorio bloqueia encerramento sem valor.
- Valores preenchidos sao persistidos em `calls.service_fields`.
