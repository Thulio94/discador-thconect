# Preparacao Local para Producao

Data: 2026-07-10

## Objetivo

Elevar a confiabilidade do ambiente Docker Desktop sem publicar o sistema ou ativar telefonia e integracoes reais.

## Entregas

- health checks para PHP-FPM, Nginx, MariaDB e Redis;
- inicializacao ordenada pelo Compose e politica `restart: unless-stopped`;
- endpoint Laravel `/up` como verificacao HTTP do proxy;
- cabecalhos de seguranca globais e CSP/HSTS condicionais para producao/HTTPS;
- `.env.production.example` sem segredos reais;
- scripts PowerShell de backup e restore MariaDB, com restore protegido por `-Force`;
- runbook local em `docs/OPERACAO_LOCAL.md`.

## Validacao executada

- containers `app`, `mysql`, `redis` e `nginx` ficaram `healthy`;
- `/up` retornou HTTP 200 e os cabecalhos globais esperados;
- backup foi criado e restaurado em uma base temporaria;
- a restauracao recriou 35 tabelas antes de a base temporaria ser removida;
- a base principal de desenvolvimento nao foi alterada pelo teste de restore.

## Limites

Esta etapa nao cria uma imagem imutavel de producao, nao provisiona HTTPS publico e nao publica o projeto. Esses itens pertencem ao proximo plano de infraestrutura de producao.
