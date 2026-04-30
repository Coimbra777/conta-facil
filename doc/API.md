# API REST

**Base:** `{APP_URL}/api` — rotas versionadas em **`/api/v1/...`**.

**Auth (rotas protegidas):** `Authorization: Bearer {token}`.

---

## Envelope

Sucesso típico:

```json
{
  "success": true,
  "message": "…",
  "data": {},
  "meta": {}
}
```

Erro típico:

```json
{
  "success": false,
  "message": "…",
  "code": "ERROR_CODE",
  "errors": {}
}
```

**Auth** (`register`, `login`, `me`, `logout`): corpo **sem** esse envelope — ver `AuthController`.

---

## Auth

```txt
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
```

`me` e `logout` exigem Bearer.

**Exemplo — login (200):**

```json
{
  "user": { "id": 1, "name": "…", "email": "…" },
  "token": "1|…"
}
```

---

## Expenses (autenticado)

`{id}` = ID numérico da despesa (binding Laravel: parâmetro `{expense}`).

```txt
GET    /api/v1/expenses
POST   /api/v1/expenses
GET    /api/v1/expenses/{id}
PATCH  /api/v1/expenses/{id}
DELETE /api/v1/expenses/{id}
POST   /api/v1/expenses/{id}/participants
PATCH  /api/v1/expenses/{id}/participants/{participantId}
DELETE /api/v1/expenses/{id}/participants/{participantId}
```

**Exemplo — criar despesa (POST body simplificado):**

```json
{
  "description": "Churrasco",
  "total_amount": 120,
  "due_date": "2026-05-01",
  "pix_key": "email@exemplo.com",
  "pix_qr_code": null
}
```

---

## Charges (autenticado)

`{id}` = ID numérico da cobrança (`Charge`).

```txt
PATCH /api/v1/charges/{id}/validate
PATCH /api/v1/charges/{id}/reject
GET   /api/v1/charges/{id}/proof
```

`reject` envia corpo com motivo conforme validação do backend (ex.: campo `reason`).

---

## Público — criar sem login

```txt
POST /api/public/expenses
```

Corpo validado pelo `StorePublicExpenseRequest` (despesa + lista de participantes). **Throttle** dedicado.

---

## Público — por hash (`v1`)

`{hash}` = `public_hash` da despesa.

Principais rotas:

```txt
GET  /api/v1/public/expenses/{hash}
POST /api/v1/public/expenses/{hash}/validate-participant
POST /api/v1/public/expenses/{hash}/submit-proof
```

**Gestão** (exige `manage` na query ou header `X-Manage-Token`):

```txt
PATCH /api/v1/public/expenses/{hash}
PATCH /api/v1/public/expenses/{hash}/close
POST  /api/v1/public/expenses/{hash}/participants
PATCH /api/v1/public/charges/{id}/validate
PATCH /api/v1/public/charges/{id}/reject
GET   /api/v1/public/charges/{id}/proof
```

**validate-participant** — JSON: `name`, `phone`.

**submit-proof** — `multipart/form-data`: `name`, `phone`, `proof` (arquivo).

---

## Rate limiting

Throttle global na API + limitadores nomeados (login, registro, criação pública, submit-proof, mutações com manage, etc.).

---

## Referência cruzada

Implementação exata: `routes/api.php`.
