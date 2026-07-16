# Roadmap - Ajustes Operacionais e Modo Deus

Data: 12/07/2026

## Objetivo

Corrigir a navegação lateral, permitir que o Super Admin acompanhe uma empresa sem criar ou consumir um usuario dela, alinhar as regras de capacidade de chamadas e reduzir a latencia percebida no ambiente Docker Desktop do Windows.

## Decisoes de produto

- O acesso de um Super Admin em uma empresa e um contexto temporario de sessao, nunca um novo usuario e nunca uma alteracao do `company_id` salvo no banco.
- O contexto dara acesso administrativo completo aos modulos da empresa e permanecera visualmente identificado em todas as telas. Acoes de telefonia que originam ou alteram uma chamada permanecem bloqueadas para evitar que uma sessao de acompanhamento use o ramal do Super Admin.
- Usuarios administrativos e supervisores nao consomem capacidade de chamadas.
- Nao existe limite para quantidade de usuarios cadastrados. O Super Admin pode definir, por empresa, um teto opcional de agentes logados simultaneamente; administradores, supervisores e Super Admin nao consomem esse teto. A capacidade de chamadas continua sendo: agentes com status `available` multiplicados pelo limite de chamadas por operador disponivel, com teto global de chamadas somente quando o Super Admin o preencher.

## Etapa 1 - Sidebar recolhivel

- [x] Remover o conflito entre a largura Tailwind fixa e a largura dinamica da sidebar.
- [x] Garantir que o grid e a sidebar usem a mesma variavel de largura no desktop.
- [x] Manter menu completo no mobile e ferramenta de expandir sempre acessivel no desktop.
- [x] Validar o estado aberto, recolhido e a persistencia em novo carregamento.

## Etapa 2 - Modo Deus

- [x] Criar servico de contexto de empresa baseado na sessao do Super Admin.
- [x] Criar entrada e saida do modo na listagem de empresas, com confirmacao modal.
- [x] Aplicar o contexto antes dos middlewares e autorizacoes de tenant.
- [x] Liberar autorizacoes de tenant somente enquanto o contexto estiver ativo e para o Super Admin original.
- [x] Exibir faixa fixa com nome da empresa e comando claro para sair do modo.
- [x] Registrar inicio e fim do acompanhamento na auditoria, mantendo o Super Admin como responsavel.
- [x] Cobrir acesso, isolamento entre empresas, saida e restricao de telefonia por testes.

## Etapa 3 - Capacidade e licenciamento

- [x] Remover a validacao de quantidade maxima de usuarios ao cadastrar usuarios.
- [x] Retirar dos formularios e indicadores os limites de usuarios e de agentes logados.
- [x] Preservar apenas chamadas por operador disponivel e o teto global opcional de chamadas.
- [x] Ajustar indicadores, dashboard e testes para a nova regra.

## Etapa 4 - Desempenho local e consultas

- [x] Eliminar consultas repetidas por empresa no painel do Super Admin por meio de agregacoes em lote.
- [x] Usar volumes Docker separados para caches de framework e bootstrap, evitando I/O lento do bind mount do Windows.
- [x] Restringir o volume do Nginx ao diretorio publico, pois ele nao precisa montar todo o projeto.
- [x] Regerar caches Laravel, medir resposta das paginas principais e verificar ausencia de regressao nos testes.

## Criterios de aceite

- A sidebar ocupa aproximadamente 88 px recolhida e 256 px expandida, sem sobrepor conteudo.
- O Super Admin pode entrar e sair de qualquer empresa ativa sem ser incluido em `users` da empresa, sem aparecer em equipes e sem consumir capacidade.
- Uma empresa pode ter quantidade livre de usuarios cadastrados. O limite opcional de login simultaneo se aplica apenas a agentes; chamadas continuam limitadas por agentes disponiveis e pelo contrato de chamadas.
- A tela de empresas nao executa consultas de uso repetidas para cada registro exibido.
- Build, testes de frontend, testes de recursos afetados, verificacao de formato e verificacao de diff passam antes do envio ao GitHub.
