# Segurança

## Autenticação

- **Sanctum** com token **Bearer** (`Authorization: Bearer …`) nas rotas `/api/v1/*` protegidas.

## Gestão pública

- **`manage_token`** opaco; envio via query `manage` ou header **`X-Manage-Token`**; comparação com **`hash_equals`** no servidor.
- Link enviado ao participante não deve incluir esse token.

## Rate limiting

- Limite global na API + limitadores nomeados (login, registro, criação pública de despesa, validação de participante, envio de comprovante, ações com manage).

## Uploads

- Validação por tipo e por **conteúdo** (magic bytes); tamanho limitado; armazenamento em disco não público; download com nome de arquivo seguro.

## CORS

- Origens permitidas via **`CORS_ALLOWED_ORIGINS`** (`.env`); em produção, sem `*`.

## Headers HTTP

- Middleware **`SecurityHeaders`** na API (ex.: `nosniff`, `X-Frame-Options`; HSTS quando HTTPS).

## localStorage

- Token Sanctum guardado no cliente (ex.: React). **Risco:** XSS no mesmo origin pode ler o token. Mitigar com higiene de UI (evitar HTML não confiável, revisar `dangerouslySetInnerHTML`).

## Melhorias futuras sugeridas

- **CSP** no HTML servido pelo Laravel para a SPA  
- **Sentry** (ou similar) para erros em produção  
- Cookies **HttpOnly** exigiriam fluxo diferente do Bearer atual  

## Referências

- [PRODUCTION_CHECKLIST.md](./PRODUCTION_CHECKLIST.md)  
- [ARCHITECTURE.md](./ARCHITECTURE.md)  
