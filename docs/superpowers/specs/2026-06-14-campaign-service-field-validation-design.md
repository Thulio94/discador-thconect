# Validacao Avancada de Campos de Atendimento - Design

Data: 2026-06-14

## Objetivo

Evoluir os campos dinamicos configurados por campanha para validar e normalizar os dados preenchidos pelo agente antes da tabulacao.

Os tipos suportados serao:

- texto;
- numero;
- data;
- CPF;
- CNPJ;
- telefone;
- e-mail;
- moeda.

## Escopo

Esta etapa cobre:

- novos tipos no formulario de campanha;
- validacao no backend;
- normalizacao antes da persistencia;
- mascaras de digitacao no painel do agente;
- mensagens de erro no formulario existente;
- compatibilidade com campanhas e chamadas atuais.

Nao fazem parte desta etapa:

- campos condicionais;
- editor com arrastar e soltar;
- integracao com CRM;
- validacao externa de documentos;
- seletores, listas de opcoes ou campos de arquivo;
- alteracao retroativa dos valores ja persistidos.

## Esquema da Campanha

O JSON `campaigns.service_fields_schema` continuara usando:

```json
{
    "key": "cpf_cliente",
    "label": "CPF Cliente",
    "type": "cpf",
    "required": true
}
```

Tipos aceitos:

```text
text
number
date
cpf
cnpj
phone
email
currency
```

Campos desabilitados, sem chave, sem rotulo ou com tipo invalido nao serao persistidos no esquema.

Chaves continuarao normalizadas no formato `snake_case` e deverao ser unicas dentro da campanha. Quando houver duplicidade, somente a primeira definicao valida sera mantida.

## Regras por Tipo

### Texto

- Aceita texto simples.
- Remove espacos no inicio e no fim.
- Limite de 255 caracteres.
- Valor vazio e permitido quando o campo nao for obrigatorio.

### Numero

- Aceita numero inteiro ou decimal.
- Separador decimal aceito: ponto ou virgula.
- Remove espacos.
- Persiste usando ponto como separador decimal.
- Nao adiciona separador de milhar.
- Valores nao numericos sao rejeitados.

Exemplos:

```text
12 -> 12
12,5 -> 12.5
12.50 -> 12.50
```

### Data

- A interface usa data nativa do navegador quando disponivel.
- O backend aceita `AAAA-MM-DD`.
- A data deve existir no calendario.
- Persiste em `AAAA-MM-DD`.
- Datas passadas e futuras sao permitidas; a regra depende do uso do campo.

### CPF

- Aceita entrada com ou sem mascara.
- Remove todos os caracteres nao numericos.
- Exige 11 digitos.
- Rejeita sequencias com todos os digitos iguais.
- Valida os dois digitos verificadores oficiais.
- Persiste somente os 11 digitos.

### CNPJ

- Aceita entrada com ou sem mascara.
- Remove todos os caracteres nao numericos.
- Exige 14 digitos.
- Rejeita sequencias com todos os digitos iguais.
- Valida os dois digitos verificadores oficiais.
- Persiste somente os 14 digitos.

### Telefone

Aceita:

- telefone brasileiro com DDD e 10 ou 11 digitos;
- formato E.164 com sinal `+` e entre 8 e 15 digitos.

Regras:

- remove espacos, parenteses, tracos e demais caracteres de formatacao;
- o sinal `+` e aceito apenas na entrada E.164;
- persiste somente os digitos;
- telefone brasileiro sem codigo do pais permanece com 10 ou 11 digitos;
- E.164 permanece com codigo do pais, sem o sinal `+`;
- entradas com quantidade de digitos fora dos intervalos permitidos sao rejeitadas.

### E-mail

- Remove espacos no inicio e no fim.
- Converte para minusculas.
- Valida formato de e-mail.
- Limite de 255 caracteres.

### Moeda

- Entrada no formato brasileiro.
- Aceita simbolo `R$`, espacos e separadores de milhar opcionais.
- A virgula define os centavos.
- Exige no maximo duas casas decimais.
- Persiste como inteiro em centavos dentro do JSON.
- Valores negativos nao serao aceitos nesta etapa.

Exemplos:

```text
10 -> 1000
10,5 -> 1050
10,50 -> 1050
R$ 1.234,56 -> 123456
```

## Campos Obrigatorios

A validacao de obrigatoriedade permanece definida por `required`.

- Valor ausente, nulo ou composto somente por espacos e invalido.
- O valor textual `0` e valido.
- O erro utiliza a chave `service_fields.<campo>`.
- A mensagem deve citar o rotulo configurado na campanha.

Exemplo:

```text
Informe CPF Cliente.
```

## Mensagens de Erro

As mensagens devem ser exibidas no formulario do painel do agente usando o mecanismo de validacao do Laravel.

Exemplos:

```text
CPF Cliente possui CPF invalido.
CNPJ Empresa possui CNPJ invalido.
Telefone possui telefone invalido.
E-mail possui e-mail invalido.
Valor da proposta possui moeda invalida.
Data de nascimento possui data invalida.
```

Nao sera usado `javascript.alert`, `window.confirm` ou troca de pagina.

## ServiceFieldValidator

Criar `App\Services\Campaigns\ServiceFieldValidator`.

Responsabilidades:

- receber o esquema da campanha e os valores enviados;
- ignorar chaves que nao pertencem ao esquema;
- validar obrigatoriedade;
- validar cada tipo;
- normalizar os valores;
- devolver valores normalizados ou erros por chave.

Interface proposta:

```php
public function validate(array $schema, array $values): ServiceFieldValidationResult;
```

Criar um resultado simples contendo:

```php
final readonly class ServiceFieldValidationResult
{
    public function __construct(
        public array $values,
        public array $errors,
    ) {}

    public function passes(): bool;
}
```

O servico nao consulta banco, nao conhece usuario e nao persiste chamadas. Isso permite testes unitarios rapidos e reutilizacao futura por API ou integracao.

## Fluxo no AgentPanelController

Ao encerrar a chamada:

1. validar os campos fixos da requisicao;
2. resolver o esquema da campanha ou o esquema basico;
3. enviar esquema e valores ao `ServiceFieldValidator`;
4. retornar erros ao formulario quando o resultado falhar;
5. persistir somente os valores normalizados;
6. continuar tabulacao, callback e atualizacao do contato.

Uma falha de validacao nao encerra a chamada nem altera o status do agente.

## Interface da Campanha

O seletor de tipo exibira:

- Texto;
- Numero;
- Data;
- CPF;
- CNPJ;
- Telefone;
- E-mail;
- Moeda.

Nao sera criada uma nova pagina. A configuracao permanece no modal/wizard atual.

## Interface do Agente

Cada tipo usara atributos apropriados:

| Tipo | Input |
|---|---|
| texto | `type="text"` |
| numero | `type="text"`, `inputmode="decimal"` |
| data | `type="date"` |
| CPF | `type="text"`, `inputmode="numeric"` |
| CNPJ | `type="text"`, `inputmode="numeric"` |
| telefone | `type="tel"` |
| e-mail | `type="email"`, `autocomplete="email"` |
| moeda | `type="text"`, `inputmode="decimal"` |

Um componente JavaScript pequeno e reutilizavel aplicara mascara visual para CPF, CNPJ, telefone brasileiro e moeda.

A mascara nao sera considerada uma validacao de seguranca. O backend permanece como fonte de verdade.

## Compatibilidade

- Esquemas atuais com `text`, `number` e `date` continuam validos.
- Campanhas sem esquema continuam usando documento, protocolo e valor.
- O campo basico `valor` usa tipo `number`, preservando o armazenamento atual.
- Valores antigos em `calls.service_fields` nao serao convertidos.
- Exportacoes e relatorios continuam lendo o JSON sem exigir novo formato.

## Testes

### Testes unitarios

O `ServiceFieldValidator` deve cobrir:

1. texto aparado e limitado;
2. numero com ponto e virgula;
3. data real e data impossivel;
4. CPF valido, invalido e repetido;
5. CNPJ valido, invalido e repetido;
6. telefone brasileiro de 10 e 11 digitos;
7. E.164 entre 8 e 15 digitos;
8. telefone fora do intervalo;
9. e-mail normalizado e invalido;
10. moeda brasileira para centavos;
11. moeda negativa ou com mais de duas casas;
12. campo obrigatorio;
13. chave fora do esquema ignorada.

### Testes de integracao

- campanha salva os oito tipos permitidos;
- tipo desconhecido e rejeitado;
- painel renderiza os atributos corretos;
- chamada nao encerra com CPF/CNPJ/telefone/e-mail/moeda invalidos;
- chamada persiste os valores normalizados;
- erro permanece associado ao campo correto;
- outra empresa nao interfere no esquema.

### Testes frontend

- mascara de CPF;
- mascara de CNPJ;
- mascara de telefone brasileiro;
- mascara de moeda brasileira;
- E.164 digitado com `+` nao e transformado em telefone brasileiro.

## Criterios de Aceitacao

- Administrador configura qualquer um dos oito tipos no modal de campanha.
- Agente recebe os campos no formulario existente.
- Backend rejeita valores invalidos, mesmo sem JavaScript.
- CPF e CNPJ validam digitos verificadores.
- Telefone aceita Brasil e E.164.
- Moeda brasileira e persistida em centavos.
- CPF, CNPJ e telefone sao persistidos somente com digitos.
- E-mail e persistido em minusculas.
- Data e persistida em `AAAA-MM-DD`.
- Nenhuma chave fora do esquema e persistida.
- Testes PHP, frontend, formatacao e build passam no ambiente do projeto.
