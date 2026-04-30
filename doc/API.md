# API REST

**Base autenticada e público `v1`:** `{APP_URL}/api/v1`  
**Criação pública fora de `v1`:** `{APP_URL}/api/public/...`

Autenticação (rotas protegidas): header `Authorization: Bearer {token}` (Sanctum).

---

## Envelope JSON

Maioria dos endpoints devolve:

```json
{
  "success": true,
  "message": "…",
  "data": { },
  "meta": { }
}
```

Erro típico:

```json
{
  "success": false,
  "message": "…",
  "code": "ERROR_CODE",
  "errors": { }
}
```

**Exceção — auth:** `POST .../auth/register`, `POST .../auth/login`, `GET .../auth/me`, `POST .../auth/logout` retornam JSON direto (`user`, `token`, `message`) **sem** esse envelope — o app React já trata esse formato.

**Rate limiting:** throttle global na API (ex.: 60 req/min por IP) + limitadores nomeados em rotas sensíveis (`auth-login`, `auth-register`, `public-submit-proof`, etc.).

---

## Modelagem exposta

Domínio: **`users`**, **`expenses`**, **`expense_participants`**, **`charges`**, **`payment_proofs`**.

- Objeto `expense` inclui `amount_per_participant` quando aplicável.
- Cada item em `charges[]` inclui **`participant`** (snapshot: nome, telefone, valores), derivado de `ExpenseParticipant`.

---

## Auth

| Método | Rota |
|--------|------|
| `POST` | `/api/v1/auth/register` |
| `POST` | `/api/v1/auth/login` |
| `GET` | `/api/v1/auth/me` |
| `POST` | `/api/v1/auth/logout` |

**Registrar** — body JSON: `name`, `email`, `password`, `password_confirmation`; opcional `phone`, `cpf` (11 dígitos, único).  
**201:** `{ "user": {…}, "token": "…" }`

**Login** — `email`, `password`.  
**200:** `{ "user", "token" }` · **401:** credenciais inválidas (ou código específico conforme implementação).

---

## Cobranças — usuário autenticado

Prefixo: **`/api/v1`** + Bearer.

| Método | Rota |
|--------|------|
| `GET` | `/api/v1/expenses` |
| `POST` | `/api/v1/expenses` |
| `GET` | `/api/v1/expenses/{expense}` |
| `PATCH` | `/api/v1/expenses/{expense}` |
| `DELETE` | `/api/v1/expenses/{expense}` |
| `POST` | `/api/v1/expenses/{expense}/participants` |
| `PATCH` | `/api/v1/expenses/{expense}/participants/{participant}` |
| `DELETE` | `/api/v1/expenses/{expense}/participants/{participant}` |

Dono da cobrança: `expenses.created_by` = usuário logado.

**Exemplo — criar cobrança (resposta simplificada):**

```json
{
  "success": true,
  "data": {
    "expense": {
      "id": 1,
      "description": "Churrasco",
      "total_amount": "120.00",
      "amount_per_participant": "0.00",
      "pix_key": "email@exemplo.com",
      "status": "open",
      "public_hash": "…",
      "charges": []
    }
  }
}
```

---

## Charges — validação autenticada

| Método | Rota |
|--------|------|
| `PATCH` | `/api/v1/charges/{charge}/validate` |
| `PATCH` | `/api/v1/charges/{charge}/reject` |
| `GET` | `/api/v1/charges/{charge}/proof` |

**Reject:** enviar corpo com campo de motivo conforme `RejectChargeRequest` (ex.: `reason`), limitado no servidor.

---

## Público — criar cobrança sem login

| Método | Rota |
|--------|------|
| `POST` | `/api/public/expenses` |

Rota **sem** o segmento `v1`. Cria `Expense` com participantes; resposta em envelope com dados resumidos da cobrança (incl. links/tokens conforme resource).

---

## Público — consultar e operar por hash (`v1`)

Prefixo: **`/api/v1/public`**.

| Método | Rota |
|--------|------|
| `GET` | `/api/v1/public/expenses/{hash}` |
| `PATCH` | `/api/v1/public/expenses/{hash}` |
| `PATCH` | `/api/v1/public/expenses/{hash}/close` |
| `POST` | `/api/v1/public/expenses/{hash}/participants` |
| `POST` | `/api/v1/public/expenses/{hash}/validate-participant` |
| `POST` | `/api/v1/public/expenses/{hash}/submit-proof` |
| `PATCH` | `/api/v1/public/charges/{charge}/validate` |
| `PATCH` | `/api/v1/public/charges/{charge}/reject` |
| `GET` | `/api/v1/public/charges/{charge}/proof` |

**Gestão:** query `?manage={manage_token}` ou header **`X-Manage-Token`**. Comparação segura no servidor (`hash_equals`). Sem token válido, operações de gestão retornam **403**.

**GET despesa pública:** com `manage` correto, `participants` pode incluir por cobrança: `charge_id`, `charge_status`, `amount`, nome, telefone; sem manage, visão mínima (ex.: nome + status). Campo **`amount_per_participant`** na despesa quando exposto.

**validate-participant:** body `name`, `phone`.  
**submit-proof:** `multipart/form-data` com `name`, `phone`, `proof` (arquivo).

---

## Códigos HTTP frequentes

| HTTP | code (exemplo) | Situação |
|------|----------------|----------|
| 401 | `UNAUTHENTICATED` | Bearer inválido ou ausente |
| 403 | `FORBIDDEN` | Sem permissão ou token de gestão inválido |
| 404 | `NOT_FOUND` | Recurso inexistente |
| 422 | `VALIDATION_ERROR` / domínio | Validação ou regra de negócio |

---

## Frontend / CORS

Rotas públicas de identificação e upload **não** devem enviar `Authorization: Bearer` do organizador (evita misturar sessão do painel com fluxo do participante). Ver implementação em `frontend/src/lib/api/client.ts` (`publicV1Fetch`, `fetch` dedicados).
