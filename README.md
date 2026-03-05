# Forensic Fire Expert — AI-Powered RAG Application

An AI assistant for generating forensic fire investigation reports (*Съдебна пожаро-техническа експертиза*) under Bulgarian law. Built with Laravel 12, Livewire 4, and Retrieval-Augmented Generation (RAG).

## Overview

Administrators populate the knowledge base by uploading reference documents (DOCX, PDF, TXT). The AI agent retrieves relevant context via semantic and keyword search, then assists users in drafting structured forensic reports through a conversational interface.

## Key Features

- **Conversational AI chat** — real-time streaming responses powered by Groq (primary) with OpenAI fallback
- **RAG pipeline** — document ingestion, chunking, vector embeddings (pgvector), similarity search, and result reranking
- **Report generation** — structured forensic reports with Markdown and PDF export
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
| AI Providers | Groq (qwen3-32b), OpenAI (fallback + embeddings), Cohere (reranking) |
| AI Framework | laravel/ai |
| PDF Export | barryvdh/laravel-dompdf |
| Document Parsing | phpoffice/phpword, smalot/pdfparser |
| Auth | Laravel Fortify, spatie/laravel-permission |

## Requirements

- Docker & Docker Compose
- Groq API key
- OpenAI API key
- Cohere API key (for reranking — re-scores merged search results by relevance, selecting the top matches to pass as context to the AI agent)

## Getting Started

```bash
# Clone and enter the project
git clone <repository-url>
cd laravel_rag

# Copy environment files
cp .env.example .env
cp .docker-env.example .docker-env

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
GROQ_API_KEY=your-key
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

## Testing

```bash
docker exec laravel_rag_app php artisan test --compact
```

## License

MIT
