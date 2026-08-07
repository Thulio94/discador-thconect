# Relatorios Especificos

## Acesso

`/tenant/reports` abre a central de relatorios. Cada card abre uma consulta independente por meio de `?report=`:

- `calls`: ligacoes, gravacoes, rota, historico e desligamento;
- `agents`: tempos, chamadas e tabulacoes por agente;
- `campaigns`: desempenho da campanha;
- `lists`: situacao do mailing;
- `qualifications`: tabulacoes e conversoes;
- `teams`: produtividade por equipe;
- `utilization`: funil operacional;
- `callbacks`: retornos de clientes;
- `exports`: fila, arquivos e programacoes.

As telas paginam no servidor com no maximo 25 linhas e preservam os filtros na navegacao. As consultas sempre recebem o `company_id` da sessao, inclusive em downloads e arquivos.

## Dados historicos de chamadas

As chamadas guardam um snapshot com agente, ramal, campanha, lista, contato, equipes e rota. Isso conserva o contexto do relatorio quando entidades operacionais forem alteradas posteriormente.

`hangup_party` usa os valores `agent`, `customer`, `carrier`, `system` e `unknown`. Chamadas antigas sem essa informacao aparecem como `Desconhecido`.

## Gravacoes

O Asterisk usa `MixMonitor` para gravar em um volume Docker compartilhado e privado. O bridge somente informa uma gravacao quando confirma que o arquivo existe. O Laravel nunca expõe esse volume pela pasta publica: reproducao e download passam por uma rota autenticada, validada pela empresa.

Cada empresa possui `recording_retention_days`, com valor padrao de 90 dias. Deixar o campo vazio significa sem expiracao. A tarefa diaria `reports:purge-expired-assets` remove somente arquivos vencidos, preservando o historico da chamada.

## Exportacoes

O envio cria um `report_exports` com estado `queued`; a fila executa `GenerateReportExport`, atualizando progresso para `processing`, `completed` ou `failed`. CSV, XLSX e PDF ficam no armazenamento privado por 30 dias. A pagina mostra fila e programacoes na mesma area.

Os arquivos usam todos os resultados filtrados; o limite de 25 registros vale apenas para a interface. Downloads exigem autenticacao, permissao de exportacao e a mesma empresa do arquivo.

Programacoes diarias e semanais usam a mesma fila e informam o tipo de relatorio. O agendador evita execucao duplicada no mesmo dia.

## Operacao

No Docker, os servicos relevantes sao `queue`, `scheduler`, `asterisk` e `asterisk-bridge`. Antes de uma rota real, configure as credenciais no painel do super admin e a liberacao de rede da operadora. A presenca da configuracao de gravacao nao substitui a validacao de codec, RTP e permissao da rota real.
