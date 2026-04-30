# Frontend — React + Vite + TypeScript

## Stack

- React 18, React Router 6, TypeScript  
- Vite 5, Tailwind, shadcn/ui  
- Vitest + Testing Library  

## Estrutura

| Caminho | Função |
|---------|--------|
| `src/pages/` | Telas (auth, dashboard, cobrança, público `/p/:hash`, …) |
| `src/components/` | UI composta (`ProofUpload`, listas, shell) |
| `src/lib/api/client.ts` | HTTP, envelope, rotas `/api/v1`, fluxo público |
| `src/lib/api/mockStore.ts` | Demo offline quando não há `VITE_API_BASE_URL` |
| `src/lib/auth.tsx` | Contexto do usuário logado |
| `src/lib/types.ts` | Tipos da UI |

## Vite

- **Dev:** `npm run dev` — URL padrão http://localhost:5173  
- **Build:** `npm run build` — saída em `public/spa/` (`base: '/spa/'`)

## API client

- Variável **`VITE_API_BASE_URL`** (ex.: `http://localhost:8000`) para apontar ao Laravel durante o desenvolvimento.  
- Rotas autenticadas: header **Bearer** com token Sanctum.  
- Rotas públicas sensíveis usam helpers que **não** enviam o Bearer do organizador onde não deve (participante no mesmo navegador).

## Auth: real vs demo

- **Real:** login/registro contra a API; token em `localStorage`.  
- **Demo:** modo disponível na UI quando não há API configurada — dados em memória/localStorage via `mockStore`.

## Rotas principais (SPA)

- `/login`, `/register`  
- Área logada: `/dashboard`, fluxos de nova cobrança e detalhe  
- Público: `/p/:hash` — query `manage` para modo gestão  

## Build e testes

```bash
cd frontend
npm ci
npm run build
npm run test
```

Scripts reais: ver `frontend/package.json` (`test` = `vitest run`, `test:watch` = Vitest interativo).
