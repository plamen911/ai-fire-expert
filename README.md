# Forensic Fire Expert — AI-Powered RAG Application

An AI assistant for generating forensic fire investigation reports (*Съдебна пожаро-техническа експертиза*) under Bulgarian law. Built with Laravel 12, Livewire 4, and Retrieval-Augmented Generation (RAG).

**Live:** [fire-expert.uk](https://fire-expert.uk)

## Overview

Administrators populate the knowledge base by uploading reference documents (DOCX, PDF, TXT). The AI agent retrieves relevant context via semantic and keyword search, then reranks the merged results by relevance to select the top matches as context for generating structured forensic reports through a conversational interface. AI inference is offloaded to external APIs (OpenAI, Cohere), so the server only runs the web stack.

## Key Features

- **Conversational AI chat** — real-time streaming responses powered by OpenAI (gpt-5-mini)
- **RAG pipeline** — document ingestion, chunking, vector embeddings (pgvector), similarity search, and result reranking via Cohere
- **Report generation** — structured forensic reports with Markdown and PDF export
- **Self-improving knowledge base** — generated reports are automatically saved back into the knowledge base, allowing the AI to reference its own prior work in future queries
- **Knowledge base management** — admin upload, deduplication, preview, and deletion of reference documents
- **Conversation history** — search, star, rename, export, and resume past conversations
- **Conversation feedback** — per-message thumbs up/down rating
- **User management** — role-based access (admin/user) with Fortify authentication and 2FA support

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12, PHP 8.5 |
| Frontend | Livewire 4, Flux UI, Tailwind CSS 4, Alpine.js |
| Database | PostgreSQL 17 with pgvector |
| Cache/Queue | Redis 7 |
| AI Providers | OpenAI (gpt-5-mini, embeddings), Cohere (reranking) |
| AI Framework | laravel/ai |
| PDF Export | barryvdh/laravel-dompdf |
| Document Parsing | phpoffice/phpword, smalot/pdfparser |
| Auth | Laravel Fortify, spatie/laravel-permission |

## Requirements

- Docker & Docker Compose
- OpenAI API key
- Cohere API key (for reranking)

## Getting Started

```bash
# Clone and enter the project
git clone <repository-url>
cd laravel_rag

# Copy environment file
cp .env.example .env

# Start containers
docker compose up -d

# Install dependencies and set up the database
docker exec laravel_rag_app composer install
docker exec laravel_rag_app php artisan key:generate
docker exec laravel_rag_app php artisan migrate --seed

# Build frontend assets
npm install && npm run build
```

Add your API keys to `.env`:

```
OPENAI_API_KEY=your-key
COHERE_API_KEY=your-key
```

The application is accessible at `http://localhost`.

## Docker Services

| Service | Container | Port |
|---|---|---|
| PHP-FPM | `laravel_rag_app` | — |
| Nginx | `laravel_rag_nginx` | 80 |
| PostgreSQL + pgvector | `laravel_rag_postgres` | 5432 |
| Redis | `laravel_rag_redis` | 6379 |
| pgAdmin | `laravel_rag_pgadmin` | 5050 |

## Deployment

**Recommended:** DigitalOcean Droplet, Frankfurt (fra1), Ubuntu 24.04 — $6/mo (1 GB RAM, 1 vCPU, 25 GB disk).

PostgreSQL + pgvector, Redis, and PHP-FPM exceed 512 MB even without Docker overhead.

Deployment scripts are available in the `deploy/` directory:

- `deploy/setup-server.sh` — initial server provisioning
- `deploy/deploy.sh` — application deployment
- `deploy/setup-ssl.sh` — SSL certificate setup via Let's Encrypt
- `deploy/backup-db.sh` — database backup

**Production tips:**

- Use `docker-compose.prod.yml` instead of the default compose file to exclude pgAdmin and save ~512 MB RAM
- Set `APP_ENV=production`, `APP_DEBUG=false`, and run `php artisan config:cache`, `route:cache`, `view:cache`
- Configure a firewall to expose only ports 80/443

## Testing

```bash
docker exec laravel_rag_app php artisan test --compact
```

## License

MIT
