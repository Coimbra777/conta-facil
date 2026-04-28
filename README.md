# Conta Fácil — Laravel API + React SPA

Backend **Laravel 12** expõe API REST em `/api/v1`. O frontend **React + Vite** está em `frontend/` e, em produção, é servido pelo Laravel a partir de `public/spa`.

## Requisitos

- PHP 8.2+, Composer, extensões usuais do Laravel (`pdo_mysql`, dom, xml para CI; `pdo_sqlite` só para PHPUnit)
- MySQL 8.x (ou MariaDB compatível) para desenvolvimento e produção
- Node.js 20+ (frontend)

## Banco de dados (MySQL)

O projeto usa **MySQL** como banco principal (`utf8mb4` / `utf8mb4_unicode_ci` em `config/database.php`).

1. Crie o banco (exemplo):

   ```sql
   CREATE DATABASE conta_facil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Ajuste `DB_*` no `.env` (veja `.env.example`).

3. Rode as migrations: `php artisan migrate`

**Docker Compose:** o serviço `db` expõe MySQL na porta **3300** no host (`3300:3306`). Dentro da rede Docker, use `DB_HOST=db` e `DB_PORT=3306`. No host (artisan fora do container), use `DB_HOST=127.0.0.1` e `DB_PORT=3300`. Alinhe `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` com as variáveis do `docker-compose.yml`.

Se você mudar `DB_DATABASE` depois que o volume `.docker/mysql/dbdata` já foi criado, o MySQL pode ainda não ter esse schema ou o usuário da aplicação pode não ter privilégio nele. Crie o banco e conceda acesso (como `root` no container `db`) ou recrie o volume após ajustar o `.env`.

**Testes:** `phpunit.xml` força `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:` — não é necessário MySQL para `php artisan test`.

## Backend

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API base: `http://localhost:8000/api/v1`

Variáveis úteis:

- `APP_URL=http://localhost:8000`
- `CORS_ALLOWED_ORIGINS=http://localhost:5173` (dev; várias origens separadas por vírgula)

## Frontend (desenvolvimento)

```bash
cd frontend
npm install
cp .env.example .env
# VITE_API_BASE_URL=http://localhost:8000
npm run dev
```

App: `http://localhost:5173` (comunicação com a API no :8000 via CORS).

## Build do React para o Laravel servir

```bash
cd frontend
npm install
npm run build
```

Artefatos em `public/spa/`. Acesse a mesma origem do Laravel, por exemplo `http://localhost:8000/` — o Blade `spa.blade.php` carrega o manifest do Vite.

Se o build ainda não existir, a view mostra instruções para gerá-lo.

## Composer dev (backend + fila + logs + Vite)

```bash
composer run dev
```

Inclui `npm --prefix frontend run dev` na porta **5173**.

## Testes

```bash
php artisan config:clear
php artisan test
```

## Documentação

Índice: **[doc/README.md](doc/README.md)**

- [doc/PROJECT_OVERVIEW.md](doc/PROJECT_OVERVIEW.md) — produto e fluxos
- [doc/ARCHITECTURE.md](doc/ARCHITECTURE.md) — arquitetura técnica
- [doc/API.md](doc/API.md) — referência REST
- [doc/BACKEND.md](doc/BACKEND.md) / [doc/FRONTEND.md](doc/FRONTEND.md)
- [doc/SECURITY.md](doc/SECURITY.md) — segurança e OWASP
- [doc/PRODUCTION_CHECKLIST.md](doc/PRODUCTION_CHECKLIST.md) — deploy
- [doc/FUTURE_FEATURES.md](doc/FUTURE_FEATURES.md) — roadmap sugerido

## Deploy

1. `composer install --no-dev --optimize-autoloader`
2. Configurar `.env` (APP_KEY, DB, `APP_URL`, `CORS_ALLOWED_ORIGINS` com o domínio do frontend se for origem cruzada)
3. `php artisan migrate --force`
4. No diretório `frontend`: `npm ci && npm run build`
5. Servidor web apontando `public/` como document root; garantir que arquivos estáticos em `public/spa/` sejam servidos diretamente

Rotas web: redirecionamentos legados (`/public/expenses/...`, `/p/.../...`) + fallback SPA; rotas `/api/*` não são capturadas pelo fallback.
