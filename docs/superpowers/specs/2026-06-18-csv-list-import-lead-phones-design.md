# Importacao CSV/TXT com Telefones do Lead - Design

Data: 2026-06-18

## Objetivo

Criar uma experiencia de importacao de listas por arquivo CSV/TXT, dentro do design atual do Discador THConect, permitindo que o usuario escolha dinamicamente quais colunas sobem e quais colunas representam telefones validos do mesmo lead.

O discador deve usar os telefones alternativos do lead sem gerar rechamada indevida quando um telefone ja foi direcionado para operador ou ramal.

## Escopo

Esta etapa cobre:

- upload de arquivo CSV/TXT por clique ou arrastar e soltar;
- leitura da primeira linha como cabecalho das colunas;
- previa das colunas e das duas primeiras linhas de dados;
- selecao dinamica de colunas que serao importadas;
- marcacao de uma ou mais colunas como telefone;
- exigencia de pelo menos uma coluna de telefone;
- armazenamento de telefones alternativos no lead;
- armazenamento das demais colunas selecionadas como dados extras;
- configuracao na campanha para definir a estrategia de tentativa dos telefones do lead;
- regra anti-rechamada quando qualquer telefone do lead for direcionado para operador/ramal.

Fora do escopo:

- XLSX/Excel;
- importacao por API;
- validacao externa de telefone;
- enriquecimento de dados;
- deduplicacao entre listas diferentes;
- editor de layout de arquivo;
- mapeamento obrigatorio de tipo para todas as colunas.

## Experiencia da Lista

O modal de nova lista passa a ter duas etapas.

### Etapa 1 - Arquivo

Campos:

- nome da lista;
- descricao opcional;
- area de upload com clique ou arrastar e soltar;
- separador, com padrao `;`;
- delimitador, com padrao `"`;
- opcao informativa de que a primeira linha sera usada como cabecalho.

Regras:

- aceitar apenas CSV/TXT;
- rejeitar arquivo vazio;
- rejeitar arquivo sem cabecalho;
- usar a primeira linha como nome das colunas;
- descartar colunas sem cabecalho;
- exibir notificacao visual no canto direito para erros e sucesso;
- nao usar `javascript.alert`, `window.confirm` ou troca de pagina.

### Etapa 2 - Ajuste da Lista

Depois da leitura do arquivo, o modal mostra uma tabela de ajuste:

- coluna ativa/inativa;
- tipo da coluna;
- nome da coluna;
- linha 1;
- linha 2.

Tipos disponiveis:

- `Dado extra`;
- `Telefone`.

Comportamento:

- por padrao, colunas com cabecalho ficam ativas como `Dado extra`;
- o usuario pode desmarcar qualquer coluna que nao quer importar;
- o usuario deve marcar pelo menos uma coluna como `Telefone`;
- uma ou mais colunas podem ser marcadas como `Telefone`;
- a primeira coluna marcada como `Telefone` sera considerada telefone principal;
- as demais colunas marcadas como `Telefone` serao telefones alternativos;
- nao e necessario mapear tipo de todas as colunas.

As colunas desmarcadas nao serao persistidas.

## Modelo de Lead e Telefones

O conceito correto para esta regra e lead com multiplos telefones, nao um contato independente por telefone.

Motivo:

- se cada telefone virar contato separado, o discador pode falar com a mesma pessoa mais de uma vez;
- quando um telefone do lead completa e vai para operador, os telefones restantes nao devem ser discados;
- o lead precisa carregar os dados extras uma unica vez.

Modelo esperado:

- `contacts` continua representando o lead;
- `contacts.phone` pode continuar guardando o telefone principal para compatibilidade;
- novos telefones do lead devem ser armazenados em estrutura propria, mantendo:
  - telefone normalizado;
  - ordem de tentativa;
  - nome da coluna original;
  - status/tentativas por telefone;
  - indicador de telefone principal.

As colunas extras selecionadas ficam em `contacts.extra`.

## Normalizacao de Telefones

Na importacao:

- remover caracteres nao numericos;
- aceitar DDD + telefone;
- telefone valido deve ter pelo menos 10 digitos;
- telefone invalido nao entra na lista de telefones do lead;
- se uma linha nao tiver nenhum telefone valido nas colunas marcadas, o lead nao sera importado;
- telefones duplicados dentro do mesmo lead sao considerados uma unica opcao;
- blacklist ativa da empresa deve continuar sendo aplicada.

Resumo de importacao:

- leads importados;
- telefones importados;
- linhas sem telefone valido;
- telefones bloqueados por blacklist;
- telefones duplicados.

## Estrategia de Telefones na Campanha

Adicionar na campanha uma configuracao:

`Estrategia de telefones do lead`

Valores:

### Horizontal ativado

O discador tenta os telefones do mesmo lead antes de passar para outro lead.

Fluxo:

1. Lead A telefone principal;
2. se nao completou, Lead A telefone alternativo 1;
3. se nao completou, Lead A telefone alternativo 2;
4. depois passa para Lead B.

### Horizontal desativado

O discador prioriza o telefone principal de todos os leads antes de voltar aos alternativos.

Fluxo:

1. Lead A telefone principal;
2. Lead B telefone principal;
3. Lead C telefone principal;
4. depois Lead A telefone alternativo 1;
5. Lead B telefone alternativo 1;
6. e assim por diante.

Esta estrategia deve ser configurada por campanha, pois a mesma lista pode ser usada em campanhas com regras diferentes.

## Regra de Sucesso e Anti-Rechamada

Sucesso do lead nao depende da tabulacao manual.

Um lead e considerado contatado quando:

- a chamada foi direcionada/conectada para um operador ou ramal.

Quando isso acontece:

- o lead fica bloqueado para novas tentativas de telefones alternativos naquela campanha;
- os demais telefones do lead nao devem ser discados;
- evita falar com a mesma pessoa mais de uma vez.

Insucesso do telefone:

- chamada nao completou;
- nao chegou em operador/ramal;
- caiu antes do atendimento;
- numero invalido;
- ocupado;
- sem resposta;
- falha tecnica.

Nesses casos o discador pode tentar outro telefone valido do mesmo lead, conforme a estrategia da campanha.

## Compatibilidade

- Listas antigas continuam funcionando com um unico telefone em `contacts.phone`.
- O discador deve tratar leads antigos como leads com apenas um telefone principal.
- O textarea antigo pode ser removido da experiencia principal de criacao, mas a logica de compatibilidade deve permanecer durante a transicao.
- Relatorios atuais continuam lendo `contacts.phone` como telefone principal.
- Futuras exportacoes podem incluir telefones alternativos, mas isso nao faz parte desta etapa.

## Interface

O visual deve seguir o design system atual do Discador THConect:

- modal existente;
- bordas leves;
- botoes do sistema;
- cards sem exagero;
- nenhuma pagina nova para CRUD;
- nenhuma caixa nativa de alerta;
- notificacoes no canto direito da tela;
- tabela de ajuste compacta, parecida com as imagens de referencia, mas adaptada ao estilo do sistema.

Estados esperados:

- aguardando arquivo;
- arquivo carregado;
- processando;
- erro de leitura;
- ajuste das colunas;
- salvando;
- sucesso com resumo de importacao.

## Testes

### Backend

Cobrir:

- upload CSV/TXT com cabecalho;
- rejeicao de arquivo vazio;
- rejeicao sem coluna de telefone;
- importacao com uma coluna de telefone;
- importacao com multiplas colunas de telefone;
- linha sem telefone valido nao cria lead;
- telefones duplicados do mesmo lead sao deduplicados;
- blacklist aplicada em todos os telefones;
- colunas desmarcadas nao sao persistidas em `extra`;
- colunas extras selecionadas sao persistidas em `extra`;
- lista antiga com telefone unico continua funcionando;
- estrategia horizontal ativada escolhe o proximo telefone do mesmo lead;
- estrategia horizontal desativada prioriza principais antes dos alternativos;
- lead contatado por operador/ramal bloqueia telefones restantes.

### Frontend

Cobrir:

- parser CSV/TXT com separador `;`;
- parser com delimitador `"`;
- preview usando primeira linha como cabecalho;
- colunas sem cabecalho descartadas;
- selecao de tipo `Telefone`;
- obrigatoriedade de pelo menos uma coluna de telefone;
- drag and drop;
- nenhuma chamada a `alert` ou `confirm`.

## Criterios de Aceitacao

- Usuario cria lista por upload CSV/TXT dentro do modal.
- Primeira linha vira cabecalho.
- Usuario escolhe quais colunas importar.
- Usuario marca uma ou mais colunas como telefone.
- Pelo menos uma coluna de telefone e obrigatoria.
- Nao existe obrigatoriedade de mapear tipo de todas as colunas.
- Lead com varios telefones nao gera contatos independentes que possam ser chamados depois de atendimento.
- Campanha define estrategia horizontal ativada ou desativada.
- Se qualquer telefone do lead for direcionado para operador/ramal, o lead nao tenta outros telefones.
- CSV/TXT e suportado; XLSX nao e suportado.
- Nenhuma interacao usa `javascript.alert`, `window.confirm` ou nova pagina de CRUD.
