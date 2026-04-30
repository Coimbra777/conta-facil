# API REST — `/api/v1`

Base: `{APP_URL}/api/v1`  
Autenticação (rotas protegidas): header `Authorization: Bearer {token}`  
Envelope de sucesso/erro (maioria dos endpoints):

```json
{
  "success": true,
  "message": "…",
  "data": { },
  "meta": { }
}
```

```json
{
  "success": false,
  "message": "…",
  "code": "ERROR_CODE",
  "errors": { }
}
```

**Exceção — autenticação:** `POST register`, `POST login`, `GET me`, `POST logout` retornam corpo direto (`user`, `token` ou `message`) sem envelope — o client React já trata isso.

Rate limit: throttle global da API (ex.: 60/min por IP) + limitadores nomeados em rotas sensíveis (`auth-login`, `auth-register`, `public-submit-proof`, etc.).

---

## Auth

### `POST /api/v1/auth/register`

Body (JSON): `name`, `email`, `password`, `password_confirmation`, opcional `phone`, `cpf` (11 dígitos, único).

**201** — `{ "user": {…}, "token": "…" }`  
**422** — validação Laravel (no envelope se passar pelo handler global) / erros de campo.

### `POST /api/v1/auth/login`

Body: `email`, `password`.

**200** — `{ "user", "token" }`  
**401** — `{ "message": "Invalid credentials." }`

### `POST /api/v1/auth/logout` (Bearer)

**200** — `{ "message": "Successfully logged out." }`

### `GET /api/v1/auth/me` (Bearer)

**200** — `{ "user": {…} }`

---

## Modelagem de dados (API v1)

Tabelas ativas: **`users`**, **`expenses`**, **`expense_participants`**, **`charges`**, **`payment_proofs`**. Sem `teams` / `team_members` no schema atual.

---

## Cobranças — usuário autenticado (Bearer)

Dono da cobrança: `expenses.created_by` = usuário logado. Participantes: `expense_participants` + `charges`.

Objeto `expense` inclui `amount_per_participant` (valor médio por cobrança quando aplicável). Cada item em `charges[]` inclui `participant` (snapshot; sem campo `member`).

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/expenses` | Lista cobranças do usuário |
| POST | `/expenses` | Cria cobrança (sem equipe) |
| GET | `/expenses/{expense}` | Detalhe com `charges` |
| PATCH | `/expenses/{expense}` | Atualiza |
| DELETE | `/expenses/{expense}` | Exclui (se todas as charges `pending`) |
| POST | `/expenses/{expense}/participants` | Adiciona participantes e valores |
| PATCH | `/expenses/{expense}/participants/{participant}` | Atualiza snapshot do participante |
| DELETE | `/expenses/{expense}/participants/{participant}` | Remove participante (recalcula divisão) |

---

## Validação de cobrança autenticada (Bearer)

| Método | Rota | Descrição |
|--------|------|-----------|
| PATCH | `/charges/{charge}/validate` | Aprova comprovante |
| PATCH | `/charges/{charge}/reject` | Rejeita (`reason` opcional/limitado) |
| GET | `/charges/{charge}/proof` | Download comprovante |

---

## Público — criar despesa sem login

### `POST /api/public/expenses`

Fora do prefixo `v1` no arquivo de rotas: **`/api/public/expenses`**.  
Cria despesa “anônima” com participantes; retorno em envelope com `expense` resumido.

---

## Público — `v1`

### `GET /api/v1/public/expenses/{hash}`

Query opcional: `manage={manage_token}` — com token correto, `participants` lista cada cobrança (`charge_id`, `charge_status`, `amount`, nome, telefone). Sem token, `participants` traz apenas nome + status (visão mínima). Campo monetário médio: **`amount_per_participant`** (coluna homônima em `expenses`).

**404** — hash inexistente (envelope `NOT_FOUND`).

### `POST /api/v1/public/expenses/{hash}/validate-participant`

Body: `name`, `phone` (normalizados no backend).

**200** — envelope com `status`, `rejection_reason`, `can_submit_proof`.  
**422** — `PARTICIPANT_NOT_FOUND` se nome/telefone não batem.

### `POST /api/v1/public/expenses/{hash}/submit-proof`

`multipart/form-data`: `name`, `phone`, `proof` (arquivo).  
Validação forte no servidor (tipo/tamanho/conteúdo).

### Gestão pública (requer `manage` ou header)

- `PATCH /api/v1/public/expenses/{hash}` — atualizar valor/Pix (regras de estado).
- `PATCH /api/v1/public/expenses/{hash}/close` — fechar despesa.
- `POST /api/v1/public/expenses/{hash}/participants` — adicionar participantes.
- `PATCH /api/v1/public/charges/{charge}/validate` | `/reject`
- `GET /api/v1/public/charges/{charge}/proof` — download

**403** — `manage_token` ausente ou inválido.

---

## Erros comuns

| HTTP | code (exemplo) | Quando |
|------|------------------|--------|
| 401 | UNAUTHENTICATED | Bearer inválido/ausente |
| 403 | FORBIDDEN | Sem permissão / manage inválido |
| 404 | NOT_FOUND | Recurso inexistente ou escopo errado |
| 422 | VALIDATION_ERROR / domínio | Validação ou regra de negócio |

---

## Compatibilidade com o frontend

- Envelope: `unwrapEnvelope` em `client.ts` aceita resposta com ou sem envelope em alguns casos legadas.
- Rotas públicas de identificação e upload **não** devem enviar Bearer (implementação atual usa `fetch` sem auth ou `publicV1Fetch` para não vazar token nem disparar logout global em 401).
