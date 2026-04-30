# Backend — Laravel 12

## Stack

- PHP 8.2+, Laravel 12, Sanctum, Doctrine DBAL (migrations com `change()`).
- MySQL em dev/prod; SQLite em memória nos testes (`phpunit.xml`).

## Estrutura de pastas (principais)

| Caminho | Função |
|---------|--------|
| `app/Http/Controllers/Api/V1/` | Controllers finos: auth, expenses, charges, public |
| `app/Actions/` | Casos de uso por agregado (Charge, Expense) |
| `app/Services/` | Orquestração e regras mais longas |
| `app/Http/Requests/Api/V1/` | Validação e normalização |
| `app/Http/Resources/` | Shape JSON |
| `app/Http/Responses/ApiResponse.php` | Envelope padrão |
| `app/Exceptions/` | `HttpApiException`, exceções de domínio |
| `app/Models/` | Eloquent; `Expense` protege `manage_token` / `public_hash` do mass assignment |
| `app/Support/` | Parsers, normalizadores, transições de status |
| `database/migrations/` | Schema versionado |
| `tests/Feature/` | Fluxos API, público, segurança |

Não há **Policies** Laravel separadas: autorização por `ExpenseAuthorizer` (`created_by`) e, no fluxo público, por `manage_token` / header `X-Manage-Token`.

## Actions e services exemplares

- `CreateExpenseAction` / `UpdateExpenseAction` / `DeleteExpenseAction` / `AddExpenseParticipantsAction` / `UpdateExpenseParticipantAction` / `DeleteExpenseParticipantAction` + `ExpenseService`.
- `SubmitPaymentProofAction` + `PaymentProofService` — upload seguro, transação.
- `ValidateChargeAction` / `RejectChargeAction` — transições de `Charge`.
- `PublicExpenseCreatorService` — fluxo sem usuário autenticado (`created_by` nulo; opcionalmente `owner_name` / `owner_phone`).

## Testes

- `php artisan test` — Feature cobre auth, despesa, público, upload, manage token, rate limit, headers.
- Fixture `tests/Support/ProofUploadFixture.php` — JPEG mínimo válido sem GD.

## Banco

- FKs com `cascadeOnDelete` / `nullOnDelete` conforme migration.
- Índices únicos em tokens/hashes onde aplicável.
- Tipos monetários: `decimal(10,2)`.

### Participantes da cobrança (`expense_participants`)

Quem aparece no link público e nas cobranças é **`ExpenseParticipant`** (snapshot por despesa). Cada charge expõe **`participant`** serializado a partir de `charge.expenseParticipant`. O fluxo público usa **`PublicParticipantChargeResolver`** para casar nome + telefone à cobrança correta.

## Comandos úteis

```bash
php artisan route:list
php artisan migrate
php artisan test
composer audit
```
