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

`register`, `login`, `me` e `logout` usam **o mesmo envelope** acima.

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
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "user": { "id": 1, "name": "…", "email": "…", "phone": "…" },
    "token": "1|…"
  },
  "meta": {}
}
```

O cadastro não coleta CPF. `UserResource` também não expõe `email_verified_at`.

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

### Paginação do índice

`GET /api/v1/expenses` usa paginação.

- `per_page` opcional; default **15**
- `per_page` máximo **50**
- resposta em `data.expenses`
- metadados em `meta.pagination`

Exemplo:

```json
{
  "success": true,
  "message": "Despesas carregadas com sucesso.",
  "data": {
    "expenses": []
  },
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 15,
      "total": 41
    }
  }
}
```

### Status `open` e `closed`

- **`open`** — cobrança ativa: mutações permitidas conforme regras existentes (participantes, Pix, validação/rejeição, comprovantes).
- **`closed`** — cobrança **finalizada**: quando **todas** as `Charge` estão **`validated`**, o backend marca a despesa como **`closed`** (também é possível encerrar pela rota pública **`PATCH .../close`** com `manage_token` quando todas validadas). Neste estado, **nenhuma mutação** é aceita (edição da despesa, exclusão, participantes, envio de comprovante, validar/rejeitar); leitura (`GET`) e **visualização/dowload de comprovante** autorizados continuam funcionando.

Tentativa de alteração com despesa **`closed`** → **422** com código **`EXPENSE_CLOSED`** e mensagem estável *(Esta cobrança já foi finalizada e não pode mais ser alterada.)*.

### `POST .../participants` — adicionar apenas participantes novos

Cada telefone no corpo deve ser **novo** nesta despesa. Duplicata no payload ou telefone já cadastrado → **422** `DUPLICATED_PARTICIPANT_PHONE` com a mensagem *Já existe um participante com este telefone nesta despesa.*

**Soma dos valores:** `sum(amount das cobranças já existentes) + sum(amount deste POST) == total_amount` da despesa. Na primeira chamada (sem cobranças), basta o payload fechar o total.

Alterar ou remover quem já está na lista: **`PATCH`/`DELETE .../participants/{participantId}`**. Subir o valor total da despesa pode **redistribuir** valores entre cobranças já existentes conforme `ExpenseService::updateExpense`.

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
GET   /api/v1/charges/{id}/proofs/latest/view
```

`GET …/proof` faz download (disposition típica de anexo). **`GET …/proofs/latest/view`** envia o mesmo arquivo com **`Content-Disposition: inline`** para visualização no navegador (imagens/PDF), sempre após autorização — não expõe path interno do storage.

Objeto **`Charge`** (quando `paymentProofs` vier eager-loaded) pode incluir **`has_proof`**, **`proof_uploaded_at`** (ISO 8601 do último comprovante) e **`proof_status`**.

`reject` envia corpo **`reason` obrigatório** (string, máx. 2000 caracteres).

---

## Campos `amount_per_participant` e `average_amount_per_participant`

Em **`Expense`** (JSON autenticado ou público com gestão): **`amount_per_participant`** é persistido pelo backend. Em **divisão igual**, coincide com o valor de cada cobrança; quando há **valores diferentes por participante**, o backend grava a **média aritmética** (total ÷ quantidade de cobranças) para referência agregada — **não** substitui o valor individual de cada `Charge`. O campo **`average_amount_per_participant`** espelha o mesmo número (alias documental).

---

## Público — criar sem login **(standby)**

```txt
POST /api/public/expenses
```

**Estado atual:** rota mantida por compatibilidade; middleware **`public-expense-create-standby`** responde **410 Gone** com código **`PUBLIC_CREATE_EXPENSE_STANDBY`** e mensagem orientando criação de conta. O **`PublicExpenseController::store`** e **`PublicExpenseCreatorService`** permanecem implementados — para reativar, remova o middleware desta rota em `routes/api.php`.

**Throttle:** continua aplicado antes do standby.

Quando reativado historicamente, o corpo seria validado por `StorePublicExpenseRequest` e a resposta **201** incluiria em `data.expense`:

- `participant_url` — URL absoluta do link **somente** para participantes (sem `manage_token`).
- `manage_url` — mesma rota pública com fragmento `#manage={token}` para o SPA persistir o token (evite divulgar junto do link de participantes).
- `manage_token` — *(legado / compatibilidade)* token cru; prefira `manage_url` na UI.

---

### Duplicata de telefone (`POST /api/v1/expenses/{id}/participants`)

**Mesmo telefone duas vezes no JSON** ou **telefone igual ao de um participante já cadastrado na despesa** → **422**, exemplo de corpo:

```json
{
  "success": false,
  "message": "Já existe um participante com este telefone nesta despesa.",
  "code": "DUPLICATED_PARTICIPANT_PHONE",
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
GET   /api/v1/public/charges/{id}/proofs/latest/view
```

**submit-proof** — `multipart/form-data`: `name`, `phone`, `proof` (arquivo).

Se a cobrança estiver `rejected`, um novo envio substitui o comprovante anterior. O backend mantém apenas o arquivo mais recente por `charge`.

**reject** (gestão pública ou painel): corpo com **`reason` obrigatório**.

---

## Rate limiting

Throttle global na API + limitadores nomeados:

- `auth-login`: 5/min por IP + e-mail
- `auth-register`: 3/min por IP
- `public-expense-show`: 60/min por IP + hash
- `public-validate-participant`: 20/min por IP + hash
- `public-submit-proof`: 10/min por IP + hash
- `public-proof-download`: 20/min por IP + charge
- `public-proof-preview`: 20/min por IP + charge

Erros estáveis relevantes:

- `EXPENSE_CLOSED`
- `INVALID_MANAGE_TOKEN`
- `DUPLICATED_PARTICIPANT_PHONE`
- `PROOF_ALREADY_SENT`
- `PARTICIPANT_ALREADY_VALIDATED`
- `PROOF_NOT_FOUND`

---

## Referência cruzada

Implementação exata: `routes/api.php`.
