# Backend — Laravel 12

## Stack

- PHP 8.2+, Laravel 12, Sanctum  
- MySQL em desenvolvimento/produção  
- PHPUnit com SQLite em memória (`phpunit.xml`)

## Estrutura (principais pastas)

| Caminho | Função |
|---------|--------|
| `app/Http/Controllers/Api/V1/` | Auth, expenses, participantes, cobranças, público |
| `app/Actions/` | Casos de uso (ex.: validar/rejeitar cobrança, proof) |
| `app/Services/` | Orquestração (`ExpenseService`, `PaymentProofService`, …) |
| `app/Http/Requests/Api/V1/` | Validação |
| `app/Http/Resources/` | Formato JSON das respostas |
| `app/Http/Responses/ApiResponse.php` | Envelope padrão |
| `app/Models/` | Eloquent (`User`, `Expense`, `ExpenseParticipant`, `Charge`, `PaymentProof`) |
| `app/Support/` | Normalização, transições de status, autorização auxiliar |
| `database/migrations/` | Schema |
| `tests/Feature/` | Testes de API |

Autorização explícita em código: **`ExpenseAuthorizer`** (dono = `created_by`) e concerns/helpers para rotas públicas com **`manage_token`**.

## Models principais

- **User** — `hasMany` Expense via `created_by`
- **Expense** — agrega Pix, totais, `public_hash`, `manage_token`, status
- **ExpenseParticipant** — snapshot por despesa
- **Charge** — `expense_id`, `expense_participant_id`, valor, status, datas
- **PaymentProof** — arquivo ligado ao `Charge`

## Fluxo de dados (exemplo)

1. Organizador **POST /expenses** → `Expense` sem cobranças por participante até **POST …/participants**.  
2. Serviço cria **ExpenseParticipant** + **Charge** e distribui valores.  
3. Participante no link público → **validate-participant** / **submit-proof** → **PaymentProof** + mudança de status.  
4. Organizador → **PATCH charges/{id}/validate|reject** → atualização de **Charge** e possível fechamento da **Expense**.

## Comandos úteis

```bash
php artisan route:list
php artisan migrate
php artisan test
composer audit
```

Com Docker:

```bash
docker compose exec app php artisan test
```
