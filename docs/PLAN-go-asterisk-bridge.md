# Projeto: Motor de Discagem em Golang (Go -> Asterisk Bridge)

## Visão Geral
Este documento descreve a arquitetura para usar a linguagem **Golang (Go)** como ponte (Daemon/Serviço) entre a inteligência do Discador (CRM) e o motor de telefonia (Asterisk puro). Go é a escolha ideal devido ao seu suporte a concorrência massiva (Goroutines) e baixíssimo overhead de memória (ideal para manter milhares de conexões ativas simultaneamente sem sobrecarregar a máquina).

## Objetivos
O motor em Go terá as seguintes responsabilidades:
1. **Monitorar a Fila de Discagem:** Ler o banco de dados (tabela de leads/campanhas ativas) de forma contínua.
2. **Avaliar Regras de Negócio (Lógica de Disparo):** 
   - Monitorar quantos agentes estão *Disponíveis* na mesma fila da campanha. 
   - Calcular a agressividade/ritmo (`Pacing`) do dialer (ex: se o índice for de 2 chamadas por agente, e temos 10 livres = acalma-se e dispara 20 ligações num lote).
3. **Comunicação com o Asterisk (Disparo):** Enviar ordens massivas usando a interface de gerência `AMI` (Asterisk Manager Interface).
4. **Tratamento da Ligação:**
   - Delegar e analisar eventos do Asterisk para saber se a ligação chamou no cliente (Ringing), se o telefone não existe, ou se deu Timeout.
   - Filtrar chamadas improdutivas diretamente pelo timeout nativo enviado (ex: se tocar e passar de 20s, a ponte orienta desligar e taga "Não Atende").
5. **Reportar ao CRM:** Atualizar dinamicamente o *status* do lead no banco e jogar chamadas "Humanas e Atendidas" para o ramal SIP do operador.

---

## Fases da Implementação

### Fase 1: Setup do Projeto Go
- Inicializar módulo (`go mod config`).
- Implementar carregamento de `.env` (credenciais seguras de MySQL, Asterisk AMI TCP).
- Estruturar diretórios no modelo moderno de Go: `cmd/` (entrypoint main), `internal/asterisk` (ami client), `internal/db` (repository).

### Fase 2: Otimização com Redis e Persistência MySQL
- Incorporar **Redis** como camada de estado em memória ultrarrápida. Ele armazenará a fila quente das ligações sendo tentadas em tempo real e os contadores, aliviando o banco relacional.
- Integrar com MySQL/MariaDB apenas para baixar o "lote de envios" e salvar os resultados históricos finais.
- Interface `CountAvailableAgents`: Com a investigação do código (`User.php`), detectamos que o status fica na tabela `agent_status` onde a flag de livre é `'available'`. O motor do Go manterá a contagem desses agentes livres direto pelo Redis ou buscando as `status IN ('available')` no banco em baixa latência para alimentar o pacing.

### Fase 3: Camada Asterisk (AMI Client & Event Loop)
- Implementar TCP socket TCP Pool com o Asterisk (Porta 5038).
- Criar listener de Eventos AMI assíncrono usando Go Channels.
- Ação `OriginateAsync`: Geração do disparo contendo cabeçalhos que identifiquem a chamada. Timeout embutido de segundos de discagem.

### Fase 4: O "Cérebro" do Discador (Dialer Engine Preditivo/Power)
- Loop do Pacing rodando em microssegundos no Go, cruzando (Agentes 'available' * Agressividade = Ligações a gerar via Goroutines).
- **Integração com Classificador da Rota:** Como a operadora devolve o status da classificação (Humano/Máquina/Improdutiva), o motor Go irá interceptar o header SIP correspondente (ou *Hangup Cause* emitido pelo classificador remoto) para derrubar de imediato ou transbordar a chamada para a Fila do agente livre associado.

---

## Próximos Passos Confirmados
As respostas dadas permitiram que a arquitetura ideal fosse fechada com precisão. O ecossistema fará a seguinte ponte: **CRM PHP/MySQL <-> REDIS <-> DISCADOR GOLANG <-> ASTERISK <-> ROTA VOIP COM CLASSIFICAÇÃO**.

Para confirmar e começarmos:
> Execute o comando `/create` ou autorize para que eu crie a estrutura de diretórios do ambiente Go e a primeira versão funcional em código.
