# Frontend — React + Vite + TypeScript

## Stack

- React 18, React Router 6, TypeScript, Tailwind, shadcn/ui (componentes em `components/ui`).
- TanStack Query instalado; uso ainda majoritariamente imperativo via `api` singleton.
- Vitest + Testing Library (poucos testes).

## Estrutura

| Caminho | Função |
|---------|--------|
| `src/pages/` | Telas: `Landing`, `Auth`, `Dashboard`, `NewExpense`, `ExpenseDetail`, `PublicExpense`, etc. |
| `src/components/` | UI reutilizável, `AppShell`, `ProofUpload`, `ParticipantList` |
| `src/lib/api/client.ts` | Client HTTP, envelope, auth, rotas v1 |
| `src/lib/api/mockStore.ts` | Mock + localStorage quando sem `VITE_API_BASE_URL` |
| `src/lib/types.ts` | Tipos do domínio na UI |
| `src/lib/format.ts` | BRL, datas, `buildPublicLink` (`/p/{hash}` sem `manage`) |
| `src/lib/auth.tsx` | Context de usuário |

## Variáveis de ambiente

- `VITE_API_BASE_URL` — URL do Laravel (ex.: `http://localhost:8000`). Não colocar segredos; só origem pública da API.

## Rotas relevantes

- `/login`, `/register` — auth.
- `/dashboard`, `/cobrancas/...` — área logada (`ProtectedRoute`).
- `/p/:hash` — página pública; lê `?manage=` para modo gestão.

## API client

- Cobranças autenticadas: `GET/POST /expenses`, `POST /expenses/{id}/participants`, `GET /expenses/{id}`, `DELETE /expenses/{id}` — sem `ensureTeamId` nem `/teams/.../expenses`.
- `getToken` / `setToken` — `localStorage` chave `contacerta:auth:v1`.
- Rotas autenticadas: `v1Fetch` com Bearer.
- Rotas públicas sensíveis: **`publicV1Fetch`** para `validate-participant` (sem Bearer); `getPublicExpense` / `submitProof` usam `fetch` sem header de auth.
- **401** em rotas autenticadas: limpa token e redireciona para `/login`.
- **403**: mensagem de acesso negado.
- **422**: erros de validação mapeados para `ApiClientError`.

## Build

```bash
cd frontend
npm ci
cp .env.example .env   # ajustar VITE_API_BASE_URL
npm run dev
npm run build          # gera public/spa
```

## Boas práticas

- Preferir texto vindo da API como filhos de React (escapado), não `dangerouslySetInnerHTML`.
- Não logar token nem `manage` no console.
- Link para participantes: usar `buildPublicLink` (sem token); link de gestão só quando o produto exigir, em canal privado.
