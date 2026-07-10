# Etapas Avancadas de Campanha

Data: 2026-07-10

## Objetivo

Completar a configuracao avancada da campanha no MVP simulado sem ativar Asterisk, troncos SIP ou AMD real.

## Configuracoes por campanha

- `max_attempts_per_contact`: limite adicional de tentativas do lead. Quando a tabulacao tambem possuir limite, prevalece o menor valor.
- `retry_delay_minutes`: intervalo minimo adicional. Quando a tabulacao tambem possuir cooldown, prevalece o maior valor.
- `max_concurrent_calls`: teto de chamadas simultaneas da campanha. O valor nunca pode exceder o teto ativo contratado pela empresa e tambem respeita a capacidade dos agentes.
- `amd_mode`: `disabled` ou `simulated`. O modo simulado e apenas informativo no MVP.
- `dialing_route`: nome de referencia visual para a rota futura. Nenhum tronco, gateway ou Asterisk e chamado.

## Regras preservadas

- Callback continua usando sua data e hora definidas na tabulacao.
- Tabulacao de sucesso continua interrompendo novas tentativas do lead.
- Retry por tabulacao continua sendo o gatilho para retorno a fila.
- O limite global da empresa e compartilhado por todas as campanhas e continua autoritativo.

## Interface

Os controles ficam na etapa operacional do wizard de campanha e sao exibidos no resumo da campanha. Textos deixam claro que AMD real e rota de telefonia estao `Em breve`.

## Verificacao

- configuracoes avancadas persistem por empresa;
- limite da campanha acima do contrato e rejeitado;
- capacidade restante respeita teto proprio da campanha;
- cooldown e tentativas combinam corretamente as regras de campanha e tabulacao.
