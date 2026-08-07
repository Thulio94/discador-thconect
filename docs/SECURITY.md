# Seguranca

## Protecoes aplicadas

- Login: cinco tentativas por e-mail e IP antes do bloqueio temporario.
- Recuperacao e redefinicao de senha: tres tentativas por minuto por e-mail e IP.
- Importacoes, exportacoes, testes de webhook, testes de telefonia e eventos da ponte Asterisk possuem limites independentes.
- Formularios web usam protecao CSRF. O unico endpoint dispensado e a ponte Asterisk, protegida por assinatura HMAC e limite de requisicoes.
- Consultas usam Eloquent e parametros vinculados. As expressoes SQL brutas do discador sao constantes internas, sem interpolacao de entrada HTTP.
- Endpoints de webhook exigem HTTPS e rejeitam localhost, IPs privados e IPs reservados.
- Cabecalhos de seguranca incluem protecao contra enquadramento, MIME sniffing, politica de referencias, permissoes do navegador e isolamento de recursos.
- A versao do PHP nao e enviada no cabecalho HTTP `X-Powered-By`.
- MySQL, Redis e Reverb ficam publicados apenas em 127.0.0.1 no ambiente Docker local.

## Producao

- Use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS e `SESSION_SECURE_COOKIE=true`.
- Gere chaves e segredos aleatorios para APP_KEY, Asterisk AMI, ponte Asterisk, Reverb e banco de dados. Nunca versione o arquivo `.env`.
- Mantenha o firewall liberando somente HTTP/HTTPS publicos e as portas SIP/RTP estritamente necessarias para a operadora.
- Execute `composer audit --no-interaction` antes de cada publicacao e aplique atualizacoes de seguranca.
