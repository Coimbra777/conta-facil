# Segurança

## Autenticação

- **Sanctum** com token **Bearer** (`Authorization: Bearer …`) nas rotas `/api/v1/*` protegidas.
- Tokens de acesso têm expiração configurável por **`SANCTUM_TOKEN_EXPIRATION_MINUTES`**. Default do projeto: **43200 minutos (30 dias)**.
- `login`, `register`, `me` e `logout` seguem o mesmo envelope `ApiResponse` do restante da API.
- `logout` revoga o **token atual**.

## Gestão pública

- **`manage_token`** opaco; envio via query `manage` ou header **`X-Manage-Token`**; comparação com **`hash_equals`** no servidor.
- O link **para participantes** não deve incluir esse token. Em fluxos onde a API devolve links de criação/gestão, prefira **`participant_url`** e **`manage_url`** (fragmento `#manage=`) à exposição manual do token (campo `manage_token` pode existir por compatibilidade).
- **Fluxo principal atual:** organizador **autenticado** no painel. Gestão só com **`manage_token`** é **experimental / standby** como narrativa de produto (cobranças `created_by = null` não aparecem no painel).
- **GET público** sem gestão retorna apenas **totais agregados** (quantidade de participantes, pagos, em aberto) — não lista nome/telefone/status por pessoa.
- **`manage_token`** não autoriza mutação se a despesa já estiver **`closed`** — resposta **`EXPENSE_CLOSED`** (**422**); comprovantes antigos ainda podem ser lidos pelos endpoints dedicados quando permitido pela mesma autorização de gestão/dono.
- Token inválido de gestão retorna **`INVALID_MANAGE_TOKEN`** (**403**).

### Criação sem cadastro (standby)

- **`POST /api/public/expenses`** responde **410** até remoção do middleware de standby; não confundir com rotas de participante (`GET` por hash, `validate-participant`, `submit-proof`), que permanecem ativas.

## Validação, rejeição e prazo

- **Rejeição de cobrança** (`PATCH .../reject`): **`reason` obrigatório** (painel autenticado e gestão pública com `manage_token`).
- **`due_date`:** no MVP é **informativo** — não bloqueia envio de comprovante nem validação após o vencimento (ver também API.md).

## Rate limiting

- Limite global na API + limitadores nomeados.
- `auth-login`: **5/min** por IP + e-mail.
- `auth-register`: **3/min** por IP.
- `public-expense-show`: **60/min** por IP + hash.
- `public-validate-participant`: **20/min** por IP + hash.
- `public-submit-proof`: **10/min** por IP + hash.
- `public-proof-download`: **20/min** por IP + charge.
- `public-proof-preview`: **20/min** por IP + charge.
- `public-sensitive-mutation`: **40/min** por IP + hash.
- `public-charge-action`: **30/min** por IP + charge.

## Uploads

- Validação por tipo e por **conteúdo** (magic bytes); tamanho limitado; armazenamento em disco não público; download com nome de arquivo seguro.
- Ao excluir **`PaymentProof`**, **`Charge`**, participante ou despesa, o backend agenda a remoção do arquivo físico após commit.
- Reenvio após rejeição mantém apenas o comprovante mais recente por cobrança; o arquivo antigo é removido para evitar acúmulo indefinido.

## CORS

- Origens permitidas via **`CORS_ALLOWED_ORIGINS`** (`.env`); em produção, sem `*`.

## Headers HTTP

- Middleware **`SecurityHeaders`** em API e SPA.
- CSP mínima atual:
  - `default-src 'self'`
  - `script-src 'self'`
  - `style-src 'self' 'unsafe-inline'`
  - `img-src 'self' data: blob:`
  - `connect-src 'self'`
  - `frame-ancestors 'none'`

## localStorage

- Token Sanctum guardado no cliente (ex.: React). **Risco:** XSS no mesmo origin pode ler o token. Mitigar com higiene de UI (evitar HTML não confiável, revisar `dangerouslySetInnerHTML`).
- A CSP reduz a superfície para XSS refletido/persistido, mas **não elimina** o risco inerente ao armazenamento do Bearer em `localStorage`.

## Minimização de dados

- O produto não coleta **CPF** nos fluxos atuais de cadastro/autenticação.
- O endpoint de sessão autenticada também não expõe `email_verified_at`.

## Melhorias futuras sugeridas

- **CSP** no HTML servido pelo Laravel para a SPA  
- **Sentry** (ou similar) para erros em produção  
- Cookies **HttpOnly** exigiriam fluxo diferente do Bearer atual  

## Referências

- [PRODUCTION_CHECKLIST.md](./PRODUCTION_CHECKLIST.md)  
- [ARCHITECTURE.md](./ARCHITECTURE.md)  
