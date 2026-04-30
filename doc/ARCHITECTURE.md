# Arquitetura

## Visão em camadas

```
Browser (React SPA — dev: Vite :5173; produção: assets em public/spa)
        │  HTTP(S)
        ▼
Laravel — rotas web: shell SPA; rotas /api/*: JSON
        │
        ├── MySQL
        ├── Redis (cache/fila conforme .env)
        └── storage/app (comprovantes)
```

## Backend (Laravel 12)

- Rotas em `routes/api.php`: prefixo **`/api`**; API versionada em **`/api/v1`**.
- **Sanctum:** token Bearer em rotas protegidas.
- **Respostas:** envelope `ApiResponse` (`success`, `message`, `data`, `meta`) na maior parte dos endpoints; rotas de **auth** devolvem JSON direto (`user`, `token`).
- **Fluxo público:** despesa identificada por `public_hash`; gestão com `manage_token` (query `manage` ou header `X-Manage-Token`).
- **Organização do código:** controllers finos, **Actions**, **Services**, **Form Requests**, **Resources**.

## Frontend (React + Vite + TypeScript)

- Código em `frontend/`.
- **Desenvolvimento:** `npm run dev` — servidor Vite no host (não há serviço frontend no Docker Compose).
- **Produção:** `npm run build` gera `public/spa/`; o Laravel serve o HTML shell e os assets sob `/spa/`.
- **API:** `frontend/src/lib/api/client.ts` — base opcional `VITE_API_BASE_URL` para apontar ao Laravel em dev.

## Modelagem de dados

```txt
User
  └── Expense (created_by)
        └── ExpenseParticipant
              └── Charge
                    └── PaymentProof
```

Cada **ExpenseParticipant** é um registro próprio daquele participante **naquela** despesa; **Charge** referencia sempre um participante.

## Decisões relevantes

| Tema | Escolha |
|------|---------|
| SPA + API | Mesmo deploy possível; CORS configurável para origem do Vite em dev |
| Token | Bearer em `Authorization`; persistido no cliente (ver `doc/SECURITY.md`) |
| Upload | Validação de conteúdo no servidor; arquivos em storage privado |
