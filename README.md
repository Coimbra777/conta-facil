# ContaCerta Pix

Sistema para criar **cobranças compartilhadas via Pix**: definir total, cadastrar participantes, compartilhar link público, receber comprovantes e aprovar ou rejeitar pagamentos.

_Repositório também referido como **Conta Fácil**._

## Stack

- **Backend:** Laravel 12 — API REST em `/api/v1`
- **Frontend:** React + Vite + TypeScript (`frontend/`)
- **Banco:** MySQL 8
- **Auth:** Laravel Sanctum (Bearer token)
- **Orquestração local:** Docker Compose (`docker-compose.yml`)

## Arquitetura

API REST + SPA React (build em `public/spa/` em produção).

**Modelo de dados:**

```txt
User → Expense → ExpenseParticipant → Charge → PaymentProof
```

## Rodando com Docker

Na raiz do projeto, copie e ajuste o `.env` (veja comentários em `.env.example`). Com Compose, use **`DB_HOST=db`**, **`DB_PORT=3306`**, **`REDIS_HOST=redis`**, e **`CORS_ALLOWED_ORIGINS=http://localhost:5173`** se for desenvolver o frontend no host.

Subir serviços (PHP-FPM, Nginx, MySQL, Redis, phpMyAdmin):

```bash
docker compose up -d --build
```

Dependências PHP e chave da aplicação (uma vez, ou após clonar):

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

> **`migrate:fresh` apaga todas as tabelas.** Use só quando quiser resetar o banco:  
> `docker compose exec app php artisan migrate:fresh --force`

**URLs usuais**

| O quê            | URL                                      |
|------------------|------------------------------------------|
| API (via Nginx)  | http://localhost:8000                  |
| Prefixo JSON     | http://localhost:8000/api/v1             |
| MySQL no host    | `localhost:3300` (mapeado para o container) |
| phpMyAdmin       | http://localhost:8080                    |

O frontend **não** está no `docker-compose.yml`. Para hot reload, rode o Vite **no host** (abaixo).

## Rodando sem Docker

Requisitos: PHP 8.2+, Composer, Node 20+, MySQL acessível.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Ajuste DB_* no .env (ex.: 127.0.0.1:3306)
php artisan migrate
php artisan serve
```

Opcional — backend + fila + logs + Vite juntos (precisa de `npx`/`npm`):

```bash
composer run dev
```

### Frontend (com ou sem Docker no backend)

```bash
cd frontend
npm install
cp .env.example .env
```

Defina no `frontend/.env` (desenvolvimento com API em `:8000`):

```env
VITE_API_BASE_URL=http://localhost:8000
```

```bash
npm run dev
```

SPA em **http://localhost:5173** · API em **http://localhost:8000/api/v1**

## Build do frontend

```bash
cd frontend
npm ci
npm run build
```

Saída em `public/spa/` (servida pelo Laravel em produção).

## Testes

**Backend (PHPUnit)** — com containers no ar:

```bash
docker compose exec app php artisan test
```

Ou one-shot (sobe `app` + dependências declaradas no serviço):

```bash
docker compose run --rm app php artisan test
```

**Frontend (Vitest)** — conforme `frontend/package.json`:

```bash
cd frontend
npm run test
npm run build
```

## Documentação

Detalhes de API, arquitetura, segurança e deploy: pasta **`doc/`**. Índice em **`doc/README.md`**.

## Fluxo principal

1. Usuário cria conta / login  
2. Cria cobrança (`Expense`)  
3. Adiciona participantes (`ExpenseParticipant` + `Charge`)  
4. Compartilha link público (`/p/{hash}`)  
5. Participante identifica-se e envia comprovante  
6. Criador valida ou rejeita; pode usar link de gestão com `manage_token` (`?manage=` ou header `X-Manage-Token`)
