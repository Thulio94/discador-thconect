# Exportacao CSV de Relatorios - Design

## Objetivo

Liberar a primeira exportacao real dos relatorios operacionais, substituindo o marcador `Exportar - Em breve` por download CSV.

## Escopo desta fase

- Criar endpoint tenant para exportar chamadas em CSV.
- Respeitar os mesmos filtros da tela: periodo, agente e campanha.
- Garantir isolamento por empresa.
- Incluir dados principais da chamada: data, telefone, agente, campanha, status, canal, tabulacao e observacoes.
- Manter tudo sincronizado com a tela de relatorios, sem integracoes externas.

## Fora do escopo

- PDF.
- Excel nativo.
- Exportacao assincrona.
- Relatorios analiticos separados por agente/campanha/lista.
