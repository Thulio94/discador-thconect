# Higiene de Listas e Blacklist - Design

## Objetivo

Aplicar as regras basicas de qualidade do mailing no cadastro simples de listas, usando a blacklist configurada no tenant.

## Escopo desta fase

- Sanitizar telefones removendo caracteres nao numericos.
- Ignorar telefones com menos de 10 digitos.
- Ignorar duplicados dentro do texto importado.
- Ignorar numeros ativos na blacklist da propria empresa.
- Nao cruzar blacklist entre empresas.
- Exibir feedback por toast com resumo da importacao.

## Fora do escopo

- Upload CSV/TXT real.
- Mapeamento visual de colunas.
- Peso de lista.
- Relatorio detalhado de rejeicoes.
