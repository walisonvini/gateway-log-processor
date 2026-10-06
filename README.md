# Gateway Log Processor

## Descrição
Serviço em Laravel que processa de forma incremental os logs de um API Gateway (arquivo NDJSON), armazena os registros em MySQL e gera relatórios em CSV com métricas de uso e latência.

## 💻 Pré-requisitos
* **Docker** `^24.0`
* **Docker Compose** `^2.0`

## 🐋 Instalação

1. Clone o repositório
```bash
git clone https://github.com/walisonvini/gateway-log-processor.git
cd gateway-log-processor
```

2. Crie o arquivo de ambiente
```bash
cp .env.example .env
```

3. Construa a imagem
```bash
docker compose build
```

4. Suba os containers
```bash
docker compose up -d
```

5. Acompanhe a primeira subida
```bash
# As dependências, a APP_KEY e as migrations são preparadas automaticamente.
# A aplicação está pronta quando aparecer "Server running on [http://0.0.0.0:8000]".
docker compose logs -f app
```

## 🚀 Uso

1. Copie o arquivo de log para a pasta `logs/` do projeto
```bash
cp /caminho/do/seu/logs.txt logs/
```

2. Processe o arquivo
```bash
# A pasta logs/ do projeto é vista pelo container como /logs.
docker compose exec app php artisan logs:ingest /logs/logs.txt
```

O processamento é incremental: ao rodar o comando de novo, apenas as linhas novas do arquivo são processadas.

| Opção | Descrição |
|-------|-----------|
| `--batch=1000` | Quantidade de linhas inseridas por lote, de 100 a 1000 |
| `--restart` | Descarta o ponto de retomada e processa o arquivo desde o início. Use quando o arquivo for substituído; no mesmo arquivo, os registros são duplicados |

3. Gere os relatórios
```bash
docker compose exec app php artisan reports:generate
```

Os arquivos são gravados em `storage/app/reports`:

| Arquivo | Conteúdo |
|---------|----------|
| `requests_by_consumer.csv` | Total de requisições por consumidor |
| `requests_by_service.csv` | Total de requisições por serviço |
| `average_latency_by_service.csv` | Latência média (request, proxy e gateway) por serviço |

Para gerar apenas um relatório, informe o nome dele:
```bash
docker compose exec app php artisan reports:generate requests-by-service
```

## 🧪 Testes

1. Execute os testes
```bash
docker compose exec app php artisan test
```

2. Veja a cobertura
```bash
# O PCOV, que mede a cobertura, fica desligado por padrão para não deixar a aplicação mais lenta.
# A variável PCOV_ENABLED=1 o liga apenas nesta execução.
docker compose exec -e PCOV_ENABLED=1 app php artisan test --coverage
```

Os testes usam um banco MySQL separado, `testing`, criado automaticamente na primeira subida. O banco principal não é afetado.