# Backup de gravações no Google Drive

Este recurso é exclusivo do super admin. Empresas não recebem credenciais OAuth, links do Drive nem a localização dos arquivos.

## Preparação no Google Cloud

1. Crie ou selecione um projeto no Google Cloud e ative a **Google Drive API**.
2. Em **APIs e serviços > Credenciais**, crie um cliente OAuth do tipo **Aplicativo da Web**.
3. Cadastre exatamente a URL exibida em `Super admin > Backup de gravações` como URI de redirecionamento autorizado. Em produção ela deve usar o domínio HTTPS final do discador.
4. No painel privado do super admin, informe o Client ID e Client Secret, escolha o horário diário e salve.
5. Selecione **Conectar Google Drive** e autorize a conta que possui espaço disponível.

O sistema solicita somente o escopo `drive.file`: ele cria e administra apenas os arquivos e pastas deste backup. Client Secret e refresh token ficam criptografados no banco e nunca são retornados para as empresas.

## Estrutura e retenção

No primeiro envio, o discador cria automaticamente:

```text
THCONECT - Backup de Gravacoes/
  000123 - nome-da-empresa/
    2026-08-30/
      protocolo_telefone.wav
```

O índice auditável permanece no banco do discador em `recording_backups`, incluindo empresa, chamada, checksum, tamanho, data, status e identificador remoto. As chamadas continuam acessíveis apenas por rotas autenticadas da própria empresa; o Drive não é exposto ao navegador.

O agendador enfileira, no horário configurado, somente gravações concluídas antes do dia atual. A cópia local é removida apenas depois de o Google confirmar o upload e o índice ser gravado. É possível manter a cópia local desmarcando essa opção no painel.

## Operação

- Mantenha `RECORDINGS_DISK=recordings`; o Google Drive é um backup pós-gravação, não um disco de gravação síncrono.
- O worker da fila `reports` e o `scheduler` devem estar ativos.
- Em revogação da conta Google, a execução falha sem apagar a cópia local. Reconecte a conta no painel do super admin e execute o backup manualmente ou aguarde o próximo horário.
- A retenção configurada por empresa remove também o arquivo remoto quando aplicável.
