# Arquitetura do Sistema

> **Nota:** documentação consolidada e alinhada ao código atual: **[ARCHITECTURE.md](./ARCHITECTURE.md)**. O texto abaixo pode conter referências legadas (ex.: Asaas).

## Visao Geral

Sistema de **cobrança compartilhada via Pix** com API Laravel e SPA React. Fluxo principal: **User → Expense → ExpenseParticipant → Charge → PaymentProof**. Documentação consolidada: **[ARCHITECTURE.md](./ARCHITECTURE.md)**.

```
[React SPA / Vite]  ←HTTP→  [API REST Laravel]
   Vite dev ou /public/spa    Sanctum (Bearer) + rotas públicas por hash
```

## Backend

### Camadas

```
Controllers (thin)
    ↓
Services (logica de negocio)
    ↓
Models (Eloquent)
    ↓
Database (MySQL)
```

### Controllers

| Controller | Responsabilidade |
|-----------|-----------------|
| AuthController | Login, registro, logout, me |
| ExpenseController | CRUD cobranças autenticadas |
| PublicExpenseController | Link público, comprovantes, gestão com token |
| ChargeValidationController | Validar/rejeitar/download comprovante (autenticado) |

### Services

| Service | Responsabilidade |
|---------|-----------------|
| ExpenseService | CRUD cobrança, participantes, redistribuição de valores |
| PublicExpenseCreatorService | Criação pública sem usuário |
| PaymentProofService | Upload e armazenamento de comprovantes |
| NotificationService | Notificações (ex.: WhatsApp stub) |

### Models e Relacionamentos (atual)

```
User
  └── hasMany Expense (created_by)

Expense
  ├── belongsTo User (created_by, opcional no fluxo público)
  ├── hasMany ExpenseParticipant
  └── hasMany Charge

ExpenseParticipant
  ├── belongsTo Expense
  └── hasOne Charge

Charge
  ├── belongsTo Expense
  ├── belongsTo ExpenseParticipant
  └── hasMany PaymentProof
```

_O trecho abaixo descreve um desenho antigo (Asaas / equipes) e não reflete o código atual._

<details>
<summary>Histórico / legado (ignorar)</summary>

### Fluxo de Criacao de Despesa

```
1. Admin cria despesa no time
2. ExpenseService valida todos os membros tem Asaas
...
```

### Fluxo de Pagamento (Webhook)

```
1. Usuario paga PIX
2. Asaas envia webhook ...
```

</details>

## Frontend

### Arquitetura

Código em `frontend/` (React + TypeScript + Vite). Em produção o build vai para `public/spa/` e o Laravel serve o shell em `resources/views/spa.blade.php` para qualquer rota que não seja `/api/*`.

```
React Router (SPA)
    ↓
src/lib/api/client.ts (fetch + Bearer)
    ↓
API REST (/api/v1/*)
```

### Fluxo de Autenticacao

```
1. Login/Register → API retorna { user, token } (JSON cru)
2. Token salvo em localStorage
3. Cliente inclui Authorization: Bearer nas rotas autenticadas
4. 401 em rotas protegidas → limpa token, redireciona /login
5. AuthProvider chama GET /auth/me quando há token
```

### CORS

Com o frontend em origem diferente (ex.: `http://localhost:5173`), use `CORS_ALLOWED_ORIGINS` no `.env` e `config/cors.php`.

## Seguranca

- Senhas hasheadas via model cast (`'hashed'`)
- Tokens Sanctum com revogacao individual
- Cobrança autenticada: apenas `created_by`; público: `manage_token` / `X-Manage-Token`
- Rate limiting na API
- Validação via Form Requests; upload de comprovante validado no servidor
- CORS conforme `config/cors.php`

## Testes

```bash
docker compose run --rm app php artisan test
```

Veja também **`doc/BACKEND.md`**.

## Banco de Dados

### Tabelas principais

| Tabela | Descricao |
|--------|-----------|
| users | Usuários |
| personal_access_tokens | Tokens Sanctum |
| expenses | Cobranças / despesas |
| expense_participants | Snapshot de participantes por despesa |
| charges | Valor devido por participante |
| payment_proofs | Comprovantes enviados |
| sessions / cache / jobs | Infra Laravel |

### Variaveis de Ambiente

Variáveis específicas do projeto em `.env.example` (API, WhatsApp opcional, armazenamento).

## Docker

```bash
docker compose up -d       # Subir containers
docker compose exec app bash  # Acessar container PHP
docker compose down        # Parar containers
```

Servicos:
- **app**: PHP 8.4 + Laravel
- **nginx**: Porta 8000
- **db**: MySQL 8.0, porta 3300
- **redis**: Cache
- **phpmyadmin**: Porta 8080
