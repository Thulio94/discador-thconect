# Parametros Operacionais por Empresa

## Objetivo

Concluir a pendencia de limites de pausa e parametros gerais da operacao simulada, mantendo isolamento por empresa, CRUD via modal e auditoria central.

## Escopo

- Configuracao por empresa em `operational_parameters`.
- Defaults sem gravacao em banco ao abrir a tela:
  - pausa minima: 0 minutos;
  - pausa maxima: 120 minutos;
  - alerta de pausa longa: 20 minutos;
  - alerta de chamada longa: 15 minutos;
  - agente volta para disponivel apos tabular: ativo;
  - chamada manual durante pausa: inativo.
- Edicao em modal na tela de Configuracoes Operacionais.
- Validacao de faixa: pausa maxima deve ser maior ou igual a pausa minima.
- Auditoria das criacoes e alteracoes de parametros.

## Regras Aplicadas

- Pausa minima bloqueia retorno para `Disponivel` antes do tempo configurado.
- Chamada manual durante pausa fica bloqueada por padrao e pode ser liberada por empresa.
- Apos tabular uma chamada, o agente volta para `Disponivel` ou `Offline` conforme parametro da empresa.
- Supervisao usa os limites configurados para:
  - `Pausa longa`;
  - `Pausa excedida`;
  - `Chamada longa`;
  - notificacoes operacionais deduplicadas.

## Fora de Escopo

- Telefonia real, Asterisk, Go Bridge e integracoes continuam em breve.
- Preferencias individuais por usuario ficam para modulo futuro de notificacoes/preferencias.
