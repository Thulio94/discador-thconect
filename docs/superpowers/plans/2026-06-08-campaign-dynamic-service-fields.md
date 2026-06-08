# Plano - Campos Dinamicos por Campanha

Data: 2026-06-08

## Escopo

Adicionar configuracao simples de campos de atendimento por campanha no MVP simulado.

## Passos

1. Criar testes RED para salvar esquema na campanha.
2. Criar testes RED para renderizar, validar e persistir campos no painel do agente.
3. Adicionar migration e cast no model `Campaign`.
4. Normalizar esquema no `CampaignController`.
5. Renderizar campos no formulario de campanha.
6. Renderizar e validar campos no painel do agente.
7. Atualizar roadmap e referencias.
8. Rodar testes, build, migracao Docker e smoke.

## Resultado Esperado

Cada campanha pode ter seu proprio mini-formulario de atendimento sem trocar de pagina e sem depender de integracoes futuras.
