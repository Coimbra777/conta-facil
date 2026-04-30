# Conta Fácil

API **Laravel** (`/api/v1`) + SPA **React/Vite** em `frontend/`.

**Domínio principal:** cobrança compartilhada via Pix — usuário autenticado gerencia `Expense` em `/api/v1/expenses` (sem dependência de equipe). Rotas `/api/v1/teams/*` permanecem como legado.

## Requisitos

- **Docker** e **Docker Compose** (plugin `docker compose`)
- **Node.js 20+** (apenas para o frontend em desenvolvimento com Vite)

O backend roda nos containers; não é obrigatório PHP ou Composer instalados na máquina — os comandos Artisan abaixo usam o serviço `app`.

---

## Rodar localmente com Docker Compose

### 1. Ambiente

Na raiz do repositório:

```bash
cp .env.example .env
```

Ajuste no `.env` pelo menos:

| Variável                      | Dentro do Compose (recomendado)                                                                                         |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `APP_URL`                     | `http://localhost:8000`                                                                                                 |
| `DB_HOST`                     | `db`                                                                                                                    |
| `DB_PORT`                     | `3306`                                                                                                                  |
| `DB_DATABASE`                 | Ex.: `conta_facil` (deve bater com o banco criado pelo MySQL do Compose)                                                |
| `DB_USERNAME` / `DB_PASSWORD` | Alinhados ao `docker-compose.yml` (valores padrão do exemplo: `username` / `userpass` se usar `.env.example` como base) |
| `REDIS_HOST`                  | `redis`                                                                                                                 |
| `CORS_ALLOWED_ORIGINS`        | `http://localhost:5173` (para o Vite no host)                                                                           |

O serviço `db` usa `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` do seu `.env` para criar o MySQL. **Porta no computador:** MySQL em `localhost:3300` → dentro da rede Docker é sempre `db:3306`.

### 2. Subir os containers

```bash
docker compose build
docker compose up -d
```

Aguarde o MySQL ficar pronto (alguns segundos na primeira vez) antes do migrate.

### 3. Dependências e Laravel (dentro do container `app`)

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 4. URLs úteis

| Serviço         | URL                                             |
| --------------- | ----------------------------------------------- |
| API / app       | **http://localhost:8000** (nginx → PHP-FPM)     |
| Endpoint da API | `http://localhost:8000/api/v1`                  |
| phpMyAdmin      | http://localhost:8080 (host `db`, porta `3306`) |

### 5. Frontend (fora do Docker, com hot reload)

```bash
cd frontend
npm install
cp .env.example .env
```

No `.env` do frontend defina `VITE_API_BASE_URL=http://localhost:8000`, depois:

```bash
npm run dev
```

SPA em **http://localhost:5173**, falando com a API em `:8000`.

---

## Testes (backend no Docker)

```bash
docker compose exec app php artisan test
```

---
