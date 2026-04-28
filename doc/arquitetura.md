# Arquitetura do Sistema

> **Nota:** documentação consolidada e alinhada ao código atual: **[ARCHITECTURE.md](./ARCHITECTURE.md)**. O texto abaixo pode conter referências legadas (ex.: Asaas).

## Visao Geral

Sistema de divisao de despesas (Caixinha) com pagamento PIX via Asaas.

```
[React SPA / Vite]  ←HTTP→  [API REST Laravel]  ←→  [Asaas API]
   localhost:5173          Sanctum (Bearer)        PIX / Webhook
   ou / via public/spa     Services
                           Eloquent
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
| TeamController | CRUD equipes + dashboard |
| TeamMemberController | Add/remove membros |
| ExpenseController | CRUD despesas |
| ChargeController | Cobrancas individuais + sync |
| WebhookController | Webhooks Asaas |

### Services

| Service | Responsabilidade |
|---------|-----------------|
| AsaasClient | HTTP client centralizado para API Asaas |
| AsaasCustomerService | Criar customer (idempotente) |
| AsaasChargeService | Criar cobranca PIX, buscar QR Code, consultar pagamento |
| ChargeService | Orquestrar criacao de charge + sync |
| ExpenseService | Dividir despesa entre membros, criar charges |

### Models e Relacionamentos

```
User
  ├── hasMany Charge
  ├── hasMany Team (owner)
  └── belongsToMany Team (via team_members)

Team
  ├── belongsTo User (owner)
  ├── belongsToMany User (members, com pivot role)
  └── hasMany Expense

Expense
  ├── belongsTo Team
  ├── belongsTo User (created_by)
  └── hasMany Charge

Charge
  ├── belongsTo User
  └── belongsTo Expense (nullable)
```

### Fluxo de Criacao de Despesa

```
1. Admin cria despesa no time
2. ExpenseService valida todos os membros tem Asaas
3. Calcula split (floor + resto no ultimo)
4. Para cada membro:
   a. ChargeService.createCharge()
   b. AsaasChargeService.createPixCharge() → POST /payments
   c. AsaasChargeService.getPixQrCode() → GET /payments/{id}/pixQrCode
   d. Salva Charge no banco com PIX data
5. Retorna Expense com todas as Charges
```

### Fluxo de Pagamento (Webhook)

```
1. Usuario paga PIX
2. Asaas envia webhook → POST /api/v1/webhooks/asaas
3. WebhookController:
   a. Valida token do header
   b. Busca Charge por asaas_charge_id
   c. Atualiza status + paid_at (idempotente)
   d. Se charge tem expense_id:
      - Recalcula status da Expense
      - open → PARTIALLY_PAID → PAID
```

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
- `asaas_customer_id` nunca exposto nas respostas (hidden no model)
- API key nunca logada
- Webhook protegido por token no header
- Autorizacao inline em controllers (membership + role check)
- Rate limiting: 60 req/min na API
- Validacao completa via FormRequest em todos os endpoints
- CORS configurado para o host do Vite em desenvolvimento e para o domínio do SPA em produção, quando aplicável

## Testes

42 testes cobrindo:
- Autenticacao (11 testes)
- Cobrancas + webhook (13 testes)
- Despesas + split + dashboard (10 testes)
- Equipes + membros (7 testes)
- Exemplo (1 teste)

```bash
docker compose exec app php artisan test
```

## Banco de Dados

### Tabelas

| Tabela | Descricao |
|--------|-----------|
| users | Usuarios com asaas_customer_id |
| personal_access_tokens | Tokens Sanctum |
| teams | Equipes |
| team_members | Pivot usuario-equipe com role |
| expenses | Despesas do time |
| charges | Cobrancas PIX individuais |
| sessions | Sessoes web |
| cache | Cache do framework |
| jobs | Fila de jobs |

### Variaveis de Ambiente

| Variavel | Descricao |
|----------|-----------|
| ASAAS_API_URL | URL base da API Asaas (sandbox ou producao) |
| ASAAS_API_KEY | Chave da API Asaas |
| ASAAS_WEBHOOK_TOKEN | Token para validar webhooks |

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
