# Frontend — React + Vite (SPA)

> **Atualizado:** veja também **[FRONTEND.md](./FRONTEND.md)** (estrutura, client, variáveis, boas práticas).

O frontend principal fica em `frontend/`. Ele consome a API Laravel em `/api/v1` com `Authorization: Bearer {token}` (Laravel Sanctum).

## Stack

- React 18 + TypeScript
- Vite 5 (`vite.config.ts`)
- React Router
- TanStack Query
- Tailwind + shadcn/ui

## Desenvolvimento local

Na raiz do backend, configure CORS (já previsto em `config/cors.php` e `.env.example`):

```env
CORS_ALLOWED_ORIGINS=http://localhost:5173
```

No frontend:

```bash
cd frontend
npm install
cp .env.example .env
# Edite VITE_API_BASE_URL=http://localhost:8000
npm run dev
```

O Vite sobe em `http://localhost:5173`. A API deve estar em `http://localhost:8000` (`php artisan serve` ou Docker).

## Build para produção (servido pelo Laravel)

```bash
cd frontend
npm install
npm run build
```

Saída: `public/spa/` (manifest em `public/spa/.vite/manifest.json`). O Laravel injeta CSS/JS via `resources/views/spa.blade.php`.

Base de assets em produção: `base: '/spa/'` no Vite.

## Autenticação

Endpoints JSON “cru” (sem envelope `ApiResponse`): `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`.

Demais endpoints autenticados usam o envelope padrão (`success`, `data`, …) onde aplicável.

## Histórico

O antigo bundle em `resources/js` foi removido. O Laravel não usa mais plugin Vite na raiz do projeto; apenas o shell `spa.blade.php` e o build em `public/spa/`.
