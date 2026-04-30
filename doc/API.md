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

`reject` envia corpo **`reason` obrigatório** (string, máx. 2000 caracteres).

---

## Campos `amount_per_participant` e `average_amount_per_participant`

Em **`Expense`** (JSON autenticado ou público com gestão): **`amount_per_participant`** é persistido pelo backend. Em **divisão igual**, coincide com o valor de cada cobrança; quando há **valores diferentes por participante**, o backend grava a **média aritmética** (total ÷ quantidade de cobranças) para referência agregada — **não** substitui o valor individual de cada `Charge`. O campo **`average_amount_per_participant`** espelha o mesmo número (alias documental).

---

## Público — criar sem login

```txt
POST /api/public/expenses
```

Corpo validado pelo `StorePublicExpenseRequest` (despesa + lista de participantes). **Throttle** dedicado.

**Resposta (201) — `data.expense` inclui:**

- `participant_url` — URL absoluta do link **somente** para participantes (sem `manage_token`).
- `manage_url` — mesma rota pública com fragmento `#manage={token}` para o SPA persistir o token (evite divulgar junto do link de participantes).
- `manage_token` — *(legado / compatibilidade)* token cru; prefira `manage_url` na UI.

---

### Duplicata de telefone (participantes autenticados)

`POST /api/v1/expenses/{id}/participants` com **dois itens com o mesmo telefone normalizado** na mesma requisição → **422**, corpo:

```json
{
  "success": false,
  "message": "Já existe um participante com este telefone nesta despesa.",
  "code": "DUPLICATE_PARTICIPANT",
  "errors": {}
}
```

## Público — por hash (`v1`)

`{hash}` = `public_hash` da despesa.

Principais rotas:

```txt
GET  /api/v1/public/expenses/{hash}
POST /api/v1/public/expenses/{hash}/validate-participant
POST /api/v1/public/expenses/{hash}/submit-proof
```

**GET público sem gestão:** a `expense` retorna apenas dados agregados (`participants_total_count`, `validated_charges_count`, `open_charges_count`, totais, Pix, etc.) — **sem** lista `participants` nem dados individuais.

**GET com gestão válida:** mantém `participants` com nome/telefone/valores administrativos.

**validate-participant** — JSON: `name`, `phone`; resposta inclui `status`, `can_submit_proof`, `amount`, `rejection_reason` quando aplicável.

**due_date (MVP):** permanece **informativo** nas rotas públicas — não bloqueia comprovante nem validação após o vencimento.

**Gestão** (exige `manage` na query ou header `X-Manage-Token`):

```txt
PATCH /api/v1/public/expenses/{hash}
PATCH /api/v1/public/expenses/{hash}/close
POST  /api/v1/public/expenses/{hash}/participants
PATCH /api/v1/public/charges/{id}/validate
PATCH /api/v1/public/charges/{id}/reject
GET   /api/v1/public/charges/{id}/proof
```

**submit-proof** — `multipart/form-data`: `name`, `phone`, `proof` (arquivo).

**reject** (gestão pública ou painel): corpo com **`reason` obrigatório**.

---

## Rate limiting

Throttle global na API + limitadores nomeados (login, registro, criação pública, submit-proof, mutações com manage, etc.).

---

## Referência cruzada

Implementação exata: `routes/api.php`.
