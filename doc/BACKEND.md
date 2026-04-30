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
| `app/Support/` | Normalização, transições de status, **`ExpenseClosedPolicy`** (bloqueio de mutações em despesa `closed`), autorização auxiliar |
| `database/migrations/` | Schema |
| `tests/Feature/` | Testes de API |

Autorização explícita em código: **`ExpenseAuthorizer`** (dono = `created_by`) e concerns/helpers para rotas públicas com **`manage_token`**.

## Models principais

- **User** — `hasMany` Expense via `created_by`
- **Expense** — agrega Pix, totais, `public_hash`, `manage_token`, status; **`amount_per_participant`** persistido como valor igual por pessoa na divisão uniforme, ou **média** quando valores por charge são personalizados (**`average_amount_per_participant`** espelha o mesmo número na API).

No MVP, **`due_date`** não impede envio de comprovante nem validação após o vencimento (campo informativo).
- **ExpenseParticipant** — snapshot por despesa
- **Charge** — `expense_id`, `expense_participant_id`, valor, status, datas
- **PaymentProof** — arquivo ligado ao `Charge`

## Fluxo de dados (exemplo)

1. Organizador **POST /api/v1/expenses** → `Expense` sem cobranças por participante até **POST …/participants**.  
2. Serviço cria **ExpenseParticipant** + **Charge** em **`POST …/participants`** apenas para **telefones novos**; valores novos somam aos já gravados até fechar `total_amount`. Duplicata de telefone (payload, corrida concorrente ou já na despesa) → **422** `DUPLICATED_PARTICIPANT_PHONE`.  
3. Participante no link público → **validate-participant** / **submit-proof** → **PaymentProof** + mudança de status.  
4. Organizador → **PATCH charges/{id}/validate|reject** → atualização de **Charge** e possível fechamento automático da **Expense** (`status = closed` quando todas as charges validadas).

### Despesa `closed`

Com **`Expense.status === closed`**, mutações (despesa, participantes, comprovantes, validar/rejeitar) retornam **`EXPENSE_CLOSED`** (**422**). Leitura (**GET**) e **visualização/download de comprovante** para dono ou gestão pública autorizada **permanecem** disponíveis.

### Criação pública anônima (`POST /api/public/expenses`)

- Em **standby:** middleware **`PublicAnonymousExpenseCreationStandby`** → **410** `PUBLIC_CREATE_EXPENSE_STANDBY`.  
- **`PublicExpenseCreatorService`** segue disponível no código e nos testes (chamada direta); reativação = remover o middleware da rota.

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
