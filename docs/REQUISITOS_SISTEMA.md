# Documento de Especificação de Requisitos de Software (SRS) - Sistema de Discador Automático

## 1. Visão Geral do Projeto
Este documento descreve as especificações funcionais e regras de negócio para o desenvolvimento de um **Sistema de Discador Automático** para operações de Call Center. O sistema deve ser uma plataforma web completa que gerencie todo o ciclo de vida de campanhas de discagem, desde a importação de contatos até o atendimento pelos agentes e supervisão em tempo real.

O objetivo é maximizar a produtividade dos agentes através de algoritmos de discagem automatizados e oferecer ferramentas robustas de gestão para supervisores.

---

## 2. Perfis de Usuário e Acesso
O sistema deve possuir controle de acesso baseado em funções (RBAC) com três perfis distintos, cada um com permissões granulares.

### 2.1. Administrador (Gestor)
*   **Permissão Total**: Acesso irrestrito a todos os módulos e configurações do sistema.
*   **Gestão**: Criação de usuários, times, rotas de telefonia(se não for ilimitado), configurações globais e auditoria.

### 2.2. Supervisor
*   **Foco Operacional**: Gestão de campanhas, monitoramento de agentes e análise de relatórios.
*   **Restrições**: Visualiza e gerencia apenas os dados (campanhas, agentes) atribuídos à sua equipe ou departamento, se configurado. Não tem acesso a configurações sensíveis de infraestrutura.

### 2.3. Agente (Operador)
*   **Foco Executivo**: Interface simplificada focada exclusivamente na execução de chamadas.
*   **Funcionalidades**: Login/Logout, controle de status (Pausa/Disponível), atendimento de chamadas (Webphone), preenchimento de formulários e agendamento de retornos.

---

## 3. Módulos e Requisitos Funcionais

### 3.1. Dashboard Principal
Painel de controle visual com indicadores em tempo real para gestores e supervisores.
*   **KPIs em Tempo Real**:
    *   Chamadas Ativas no momento.
    *   Agentes Logados (Online/Pausados/Em chamada).
    *   Total de Chamadas do Dia.
    *   Taxa de Atendimento (Contact Rate).
*   **Gráficos**:
    *   Volume de chamadas por hora.
    *   Distribuição de resultados de chamadas (Venda, Recusa, Caixa Postal, etc.).
*   **Listagem Recente**: Tabela com as últimas 50 chamadas realizadas e seus status.

### 3.2. Gerenciamento de Usuários e Equipes
*   **Cadastro de Usuários**: CRUD (Criar, Ler, Atualizar, Inativar) de usuários.
    *   Campos: Nome, Email, Senha, Perfil, Ramal (gerado automaticamente e único).
*   **Gestão de Times**: CRUD de grupos de agentes (equipes).
    *   Permitir associar múltiplos agentes a um supervisor.
    *   Atribuição de campanhas a times específicos.
*   **Reativação e Histórico**: Capacidade de reativar usuários inativos, gerando novos ramais se necessário.

### 3.3. Gestão de Campanhas
O "coração" do sistema. Deve permitir a criação e configuração flexível de estratégias de discagem.

#### 3.3.1. Criação de Campanha (Wizard)
Um assistente passo-a-passo para criação intuitiva:
1.  **Info Básica**: Nome, Descrição e Prioridade.
2.  **Modo de Discagem**:
    *   **Power Dialer**: Disca para vários contatos simultaneamente (agressividade configurável 1-10) e entrega ao primeiro agente livre.
    *   **Preditivo**: Algoritmo que ajusta a agressividade automaticamente baseado na ociosidade dos agentes e taxa de abandono.
    *   **Preview**: O agente visualiza os dados do contato antes de decidir discar.
3.  **Configuração de Rotas**: Seleção da rota de telefonia (Bina/Tronco) a ser usada.
4.  **Agendamento**: Definição de dias da semana e horários permitidos para operação da campanha.

#### 3.3.2. Regras de Importação de Contatos
*   **Upload de Arquivos**: Suporte a CSV e TXT (até 20MB).
*   **Mapeamento de Campos**: Interface para o usuário dizer qual coluna do arquivo corresponde a IDENTIFICADO(ID) E Telefones
*   **Sanitização Automática**: Limpeza de caracteres não numéricos dos telefones e validação dos telefones.
*   **Validação**: Rejeição de números com formato inválido.
*   **Deduplicação**: Verificação de duplicidade dentro da lista ou da campanha de acordo com o ID da lista de acordo com o mapeamento.
*   **Pesos de Listas**: Capacidade de atribuir peso maior para listas "quentes" (serem discadas com mais prioridade).

#### 3.3.3. Funcionalidades da Campanha no Painel
*   Ações rápidas: Iniciar, Pausar, Reiniciar (reciclar mailling), Editar e Duplicar campanha.
*   Visualização de progresso da lista (Total importado, pendente, trabalhado).

### 3.4. Motor de Discagem (O Core)
*   **Fila de Discagem**: Gerenciamento inteligente da fila de contatos baseado em peso da lista e novas tentativas.
*   **Detecção de Caixa Postal (AMD)**: Capacidade de detectar se a chamada foi atendida por máquina e desligar.
*   **Controle de Tentativas**: Configuração de número máximo de tentativas por contato e intervalo mínimo entre elas.
*   **Reciclagem**: Regras para rediscar automaticamente para números Ocupados ou Não Atendidos após X minutos.

### 3.5. Painel do Agente (Webphone)
Interface de operação para o atendente.
*   **Softphone Integrado**: Telefone via navegador (WebRTC) sem necessidade de instalação de software externo.
*   **Status do Agente**: Botão para mudar status (Disponível, Pausa Banheiro, Pausa Almoço). O discador só envia chamadas se estiver "Disponível".
*   **Screen Pop**: Ao receber a chamada, a ficha do cliente deve "saltar" na tela com todos os dados.
*   **Script/Formulário**: Exibição de script de atendimento e campos para preenchimento (dados do cliente).
*   **Controles de Chamada**: Mute, Hold (Espera), Transferência (Cega e Assistida) e Conferência.
*   **Tabulação (Pós-atendimento)**:
    *   Obrigatório selecionar o resultado da chamada (Venda, Agendamento, Não Interessado, etc.) antes de receber a próxima.
    *   Temporizador de pós-atendimento (tempo limite para tabular), não havendo a tabulação classificar como "não classificado".
*   **Agendamento de Retorno**: Possibilidade de agendar um callback privado (só para ele) ou público (qualquer agente) para uma data/hora específica.

### 3.6. Supervisão em Tempo Real
Ferramenta para o supervisor monitorar a qualidade.
*   **Grid de Agentes**: Lista mostrando todos os agentes, seus status atuais e há quanto tempo estão nesse status (ex: "Em chamada há 05:23").
*   **Monitoria de Áudio (Spy)**:
    *   **Ouvir (Monitor)**: Supervisor ouve a chamada sem ser notado.
    *   **Sussurrar (Whisper)**: Supervisor fala apenas com o agente (cliente não ouve) para orientação.
    *   **Entrar (Barge/Intervir)**: Supervisor entra na conferência e todos se ouvem.

### 3.7. Relatórios
Sistema de BI para análise de performance.
*   **Filtros Avançados**: Por período (data/hora), Campanha, Equipe, Agente e Resultado da Chamada (Tabulação).
*   **Relatórios Essenciais**:
    *   **Analítico de Chamadas**: Listagem detalhada de todas as ligações.
    *   **Performance de Agente**: TME (Tempo Médio de Espera), TMA (Tempo Médio de Atendimento), Quantidade de chamadas, Conversão.
    *   **Performance de Mailing**: Qualidade das listas, % de contatos efetivos vs inválidos.
*   **Exportação**: Permitir exportar dados em CSV/Excel e PDF.

### 3.8. Configurações e Integrações
*   **Rotas de Telefonia (SIP)**: Interface para cadastrar troncos SIP (IP, Porta, Tech Prefix, Usuário/Senha).
exemplo:
Segue informações de conta SIP

Tech: 8903
Formato de Discagem: Tech+E164 (Ex: 89035511936747200)
Sip Server: 200.201.225.38
Codecs: G729, G711a, G711u
Porta UDP:5060
Porta RTP:10.000 a 65.000

*   **Blacklist (DNC)**: Cadastro de números bloqueados para não serem discados (Do Not Call).
*   **Webhooks**: Disparar notificações HTTP para sistemas externos (CRM) quando uma venda for realizada ou uma chamada finalizada.

---

## 4. Regras de Negócio (Detalhamento)

1.  **Regra de Discagem por Prioridade**: O motor deve priorizar callbacks agendados -> listas com peso maior -> contatos virgens -> rechamadas automáticas.
2.  **Exclusividade de Roteamento**: Uma campanha só pode utilizar as rotas de telefonia a ela atribuídas.
3.  **Segurança de Dados**: Agentes não podem ver a lista completa de contatos, apenas o contato que está atendendo no momento (exceto em modo Preview, se permitido).
4.  **Tabulação Obrigatória**: O sistema não deve permitir que o agente fique "Disponível" sem antes salvar o resultado da chamada anterior.
5.  **Limite de Tentativas**: Se um contato atingir o número máximo de tentativas configurado na campanha (ex: 5 tentativas) sem sucesso, ele deve ser marcado como "Esgotado" e não ser mais discado automaticamente.
6.  **Cooldown de Discagem**: Não rediscar para o mesmo número em um intervalo menor que X minutos (configurável), para evitar assédio.
7.  **Validação de Agendamento**: Não permitir agendar retorno para data/hora passada ou fora do horário de operação da campanha.
8.  **Controle de Licenças/Sessões**: O sistema deve validar se o número de agentes logados simultaneamente respeita o limite contratado (se aplicável).

---

## 5. Requisitos Não-Funcionais

1.  **Desempenho**: O sistema deve suportar múltiplas campanhas rodando simultaneamente com alta volumetria de requisições.
2.  **Latência**: A comunicação de voz (WebRTC) deve ter latência mínima (< 200ms) para garantir qualidade na conversação.
3.  **Escalabilidade**: A arquitetura deve permitir escalabilidade horizontal (adicionar mais servidores de processamento) sem interromper o serviço.
4.  **Interface Responsiva**: O painel deve ser acessível e responsivo, funcionando bem em diferentes resoluções de monitor.
5.  **Auditoria**: Todas as ações críticas (exclusão de campanha, exportação de mailling, alteração de usuário) devem gerar logs de auditoria.
6.  **Backup e Recuperação**: O sistema deve garantir a integridade dos dados e estratégias de backup para as bases de contatos e histórico.

---
