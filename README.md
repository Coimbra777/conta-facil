# ContaCerta Pix

Sistema para criar cobranças compartilhadas via Pix, adicionar participantes e gerenciar pagamentos.

## Stack

- Laravel 12
- React + Vite + TypeScript
- MySQL
- Docker Compose
- Sanctum (Bearer Token)

## Arquitetura

Backend API + SPA React.

Modelo:

```txt
User → Expense → ExpenseParticipant → Charge → PaymentProof
```

## Rodando com Docker

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Antes do primeiro `up`: `cp .env.example .env` e, no Docker, `DB_HOST=db`, `REDIS_HOST=redis`, `CORS_ALLOWED_ORIGINS=http://localhost:5173`.

## Frontend (dev)

```bash
cd frontend
npm install
cp .env.example .env
# VITE_API_BASE_URL=http://localhost:8000
npm run dev
```

## Acessos

- Frontend: http://localhost:5173
- API: http://localhost:8000/api/v1
- phpMyAdmin: http://localhost:8080

## Rodando sem Docker

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Frontend:

```bash
cd frontend
npm install
npm run dev
```

## Build do frontend

```bash
cd frontend
npm run build
```

Saída em `public/spa/`.

## Testes

Backend:

```bash
docker compose exec app php artisan test
```

Frontend:

```bash
cd frontend
npm run test
npm run build
```

## Fluxo do sistema

1. Usuário cria conta / login
2. Cria cobrança
3. Adiciona participantes
4. Compartilha link público
5. Participantes enviam comprovante
6. Criador valida/rejeita

Mais detalhes: **`doc/`**.
