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

3. Suba os containers
```bash
docker compose up -d --build
```

4. Acompanhe a primeira subida
```bash
# As dependências, a APP_KEY e as migrations são preparadas automaticamente.
# A aplicação está pronta quando aparecer "Server running on [http://0.0.0.0:8000]".
docker compose logs -f app
```
