# Answering Machine Detection (AMD) - Detecção de Voz

> Recurso para filtragem automática de caixas postais e secretárias eletrônicas.

O sistema **Diamond Dialer** integra o módulo de reconhecimento de voz do Asterisk para garantir que os agentes recebam apenas chamadas atendidas por seres humanos.

---

## Como Funciona

Quando o AMD está ativado para uma campanha:
1. O sistema dispara a chamada via **AMI (Asterisk Manager Interface)**.
2. Em vez de conectar diretamente ao agente, o canal é enviado para o contexto `[amd-check]`.
3. O Asterisk analisa o áudio inicial por um período configurável (Padrão: 3 segundos).
4. O módulo detecta padrões de fala:
   - Se detectar uma saudação curta ("Alô?"), classifica como **HUMAN**.
   - Se detectar um áudio longo ou bips, classifica como **MACHINE**.
5. Chamadas **HUMAN** são transferidas para o agente; chamadas **MACHINE** são encerradas imediatamente.

---

## Configuração na Campanha

As configurações podem ser ajustadas no **Wizard (Step 2)** ou na **Edição da Campanha**:

| Parâmetro | Padrão | Descrição |
|-----------|--------|-----------|
| **AMD Ativo** | `Inativo` | Ativa ou desativa a filtragem para a campanha. |
| **Tempo de Análise** | `3000ms` | Tempo máximo que o sistema aguardará para decidir se é humano ou máquina. |
| **Duração Mín. Voz** | `100ms` | Duração mínima de um som contínuo para ser considerado voz. |
| **Máx. de Palavras** | `3` | Quantidade de palavras curtas detectadas antes de decidir. |

---

## Requisitos Técnicos

### 1. Módulo Asterisk
O módulo `app_amd.so` deve estar carregado no Asterisk. Verifique com:
```bash
asterisk -rx "module show like app_amd"
```

### 2. Contexto no Dialplan
Adicione o seguinte contexto ao seu arquivo `extensions.conf`:

```ini
[amd-check]
exten => s,1,NoOp(Iniciando AMD para Campanha ${DIALER_CAMPAIGN_ID})
 same => n,Set(ORIGINAL_CONTEXT=${DIALER_ORIGINAL_CONTEXT})
 same => n,Set(AGENT_ID=${DIALER_AGENT_ID})
 
 ; Executa o módulo AMD
 same => n,AMD(${AMD_TIMEOUT},${AMD_WORD_LENGTH},${AMD_MAX_WORDS})
 same => n,NoOp(AMD STATUS: ${AMDSTATUS} - CAUSE: ${AMDCAUSE})

 ; Decisão
 same => n,GotoIf($["${AMDSTATUS}" = "HUMAN"]?is-human:not-human)

 ; Humano detectado
 same => n(is-human),NoOp(Humano detectado! Conectando ao agente ${AGENT_ID})
 same => n,Goto(${ORIGINAL_CONTEXT},${AGENT_ID},1)

 ; Máquina detectada
 same => n(not-human),NoOp(Caixa Postal detectada. LARGANDO CHAMADA.)
 same => n,Hangup()
```

---

## Boas Práticas

- **Threshold de 3 segundos**: É o tempo "mágico" para não irritar o cliente humano e ainda capturar a maioria das caixas postais.
- **Calibração**: Se estiver deixando passar muitas caixas postais, reduza o `Tempo de Análise`. Se estiver derrubando muitos humanos, aumente a `Duração Mín. Voz`.
- **Impacto no Canal**: Lembre-se que o canal fica ocupado por 3 segundos antes de chegar ao agente. Planje sua volumetria considerando esse "delay" inicial.
