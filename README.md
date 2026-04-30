# ContaCerta Pix

Sistema para criar cobranças compartilhadas via Pix, adicionar participantes e gerenciar pagamentos.

## Stack

- Laravel 12
- React + Vite + TypeScript
- MySQL
- Docker Compose
- Sanctum (Bearer Token)

## Hardening Fase 1

- `UserResource` não expõe CPF bruto nem `email_verified_at` no contrato padrão de auth.
- Tokens Sanctum expiram por padrão em `43200` minutos (30 dias) via `SANCTUM_TOKEN_EXPIRATION_MINUTES`.
- Comprovantes usam storage privado e o backend remove arquivo + registro ao excluir despesa/participante ou substituir proof reenviado.
- `GET /api/v1/expenses` agora é paginado (`per_page` padrão `15`, máximo `50`).
- O banco agora exige telefone único por despesa em `expense_participants`.
- A SPA servida pelo Laravel envia CSP mínima e headers de segurança adicionais.

## Arquitetura

Backend API + SPA React.

Modelo:

```txt
User → Expense → ExpenseParticipant → Charge → PaymentProof
```

**Estados da cobrança (`Expense.status`):** **`open`** (ativa) e **`closed`** (finalizada). O fechamento ocorre automaticamente quando todas as cobranças individuais (`Charge`) estão validadas; em **`closed`** a API rejeita alterações (`EXPENSE_CLOSED`), mas mantém consulta e visualização de comprovantes para quem está autorizado. No painel e na página pública, a UI trata **`closed`** como somente leitura (sem compartilhamento ativo nem envio de comprovante).

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

Variáveis adicionais relevantes para produção:

```bash
SANCTUM_TOKEN_EXPIRATION_MINUTES=43200
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

Se o PHP local não tiver as extensões do PHPUnit (`dom`, `mbstring`, `xml`, `xmlwriter`), rode os testes backend no container/CI.

Frontend:

```bash
cd frontend
npm run test
npm run build
```

## Fluxo do sistema (principal)

1. Usuário **cria conta** ou faz **login**
2. **Cria despesa** autenticada e **adiciona participantes** com valores
3. **Compartilha o link público** (`/p/{public_hash}`)
4. Participante informa **nome + telefone**, envia **comprovante** quando permitido
5. Organizador **valida ou rejeita** no painel (**motivo obrigatório** na rejeição)
6. Com todas as cobranças **validadas**, a despesa pode ser **encerrada** automaticamente conforme regras do backend

**Standby:** criação de cobrança **sem cadastro** está desativada na API/UI; ver `doc/PROJECT_OVERVIEW.md` e `doc/API.md`.

Mais detalhes: **`doc/`** (incl. **SECURITY.md** para `public_hash`, `manage_token` e privacidade do GET público).
