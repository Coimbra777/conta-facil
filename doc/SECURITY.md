# Relatório de segurança e hardening

Data da revisão: alinhado ao estado atual do repositório (Laravel 12 API + React/Vite).

---

## 1. Resumo executivo

Foram reforçados **upload de comprovantes** (conteúdo real via magic bytes, nome de arquivo seguro, UUID no storage), **rate limiting** em rotas sensíveis (auth e fluxo público), **cabeçalhos HTTP** de segurança na API, **redução de mass assignment** em `Expense` (`public_hash` / `manage_token`), **download de comprovantes** com nome de arquivo seguro, **validação** de motivo de rejeição, **CORS** com origens filtradas, **remoção de log** que incluía hash de despesa pública, e **ajustes no frontend** (query `manage` na API pública, tratamento 403, mapeamento da resposta “organizador”).  
**Composer audit**: sem vulnerabilidades reportadas. **npm audit**: várias issues em dependências transitivas (PostCSS, Rollup, yaml, etc.); correção automática pode exigir upgrades maiores — ver seção 7.

---

## 2. Vulnerabilidades / riscos identificados

### Críticas

- Nenhuma crítica nova identificada que exigisse mudança de arquitetura; o upload confiava apenas em MIME declarado — **corrigido** com verificação de assinatura de arquivo.

### Altas

- **Upload de comprovante**: risco de arquivo malicioso ou MIME falsificado; armazenamento com nome previsível era mitigado pelo `store()` do Laravel, mas o conteúdo não era validado por bytes — **corrigido** (`PaymentProofService`).
- **Mass assignment**: `public_hash` e `manage_token` em `$fillable` permitiam teoricamente definição via mass assignment em código futuro — **mitigado** (removidos do `fillable`; geração no `boot`).

### Médias

- **Brute force** em login/registro e abuso de endpoints públicos — **mitigado** (rate limiters dedicados).
- **Download de comprovante**: uso de `original_filename` do cliente em `Content-Disposition` — **mitigado** (`SafeDownloadFilename`).
- **Log** `Public expense lookup` com identificador — **removido** (evitar vazamento em agregações de logs).

### Baixas

- Ausência de cabeçalhos `X-Frame-Options`, `nosniff`, etc. na API — **corrigido** (`SecurityHeaders`).

### Melhorias preventivas

- Respostas de auth ainda em JSON “cru” (fora do envelope `ApiResponse`) — mantido por compatibilidade; padronizar no futuro.
- Token em **localStorage** no React: risco de XSS; mitigação principal é não introduzir XSS e sanitizar renderizações. Documentado abaixo.
- **npm audit**: vulnerabilidades em cadeia de build — acompanhar upgrades do Vite/ecossistema.

---

## 3. Correções aplicadas (arquivo → problema → impacto)

| Arquivo | Problema | Impacto / risco reduzido |
|--------|----------|---------------------------|
| `app/Services/PaymentProofService.php` | MIME só declarado; nome original cru | Validação por magic bytes; UUID + extensão permitida; nome original sanitizado |
| `app/Models/Expense.php` | Tokens/hash mass assignable | Reduz escalação se algum endpoint passar input amplo |
| `app/Services/ExpenseService.php`, `PublicExpenseCreatorService.php` | Uso redundante de hash no create | Alinhado ao modelo (boot gera tokens) |
| `app/Http/Middleware/SecurityHeaders.php` | Falta de headers | Clickjacking, MIME sniffing, referrer; HSTS em produção HTTPS |
| `bootstrap/app.php` | Sem headers; throttle global único | API com headers; throttle global 60/min + limitadores nomeados nas rotas sensíveis |
| `app/Providers/AppServiceProvider.php` | Sem rate limits granulares | Limita login, registro, criação despesa pública, validate-participant, submit-proof, mutações com `manage`, ações em charge |
| `routes/api.php` | Rotas públicas sem throttle específico | `GET` público de despesa usa só o throttle global; demais rotas públicas sensíveis usam `throttle:*` nomeado |
| `app/Support/SafeDownloadFilename.php` | Path traversal / nome estranho no download | Nome derivado de MIME + sanitização |
| `app/Http/Controllers/Api/V1/PublicExpenseController.php` | Log com hash; download inseguro | Menos dados em log; download seguro |
| `app/Http/Controllers/Api/V1/ChargeValidationController.php` | Idem download | Idem |
| `app/Http/Requests/Api/V1/RejectChargeRequest.php` | `reason` sem limite | DoS / payloads grandes |
| `app/Http/Requests/Api/V1/SubmitPublicProofRequest.php` | Só `mimes` | Reforço com `mimetypes` |
| `config/cors.php` | Origens com espaços; falta de orientação | `trim` + comentário produção |
| `.env.example` | Pouca orientação de produção | Nota APP_DEBUG / APP_ENV |
| `frontend/src/lib/api/client.ts` | GET público sem `manage`; 403 genérico | Fluxo organizador; erro de permissão |
| `frontend/src/pages/PublicExpense.tsx` | Não lia `?manage=` | Paridade com API |
| `tests/Support/ProofUploadFixture.php` | GD ausente no Docker | JPEG mínimo válido para testes |
| `tests/Feature/Security/SecurityHardeningTest.php` | Cobertura de segurança | Token inválido, rate limit, extensão, headers |

---

## 4. Backend: mudanças feitas

- Middleware `SecurityHeaders` registrado no grupo `api`.
- `RateLimiter` nomeados e rotas em `routes/api.php` atualizadas. O `GET` público de despesa não usa limitador extra: o throttle global da API (60/min por IP) já se aplica; um segundo limitador mais alto era redundante e não aumentava o teto real.
- `PaymentProofService`: `storeAs` com UUID; validação JPEG/PNG/PDF por prefixo de arquivo; rollback em tipo inválido.
- `Expense`: `public_hash` e `manage_token` removidos de `$fillable` (continuam em `$hidden`); testes/factories usam `forceFill` quando precisam de valores fixos.
- Downloads de comprovante com `SafeDownloadFilename`.
- `RejectChargeRequest` nos dois fluxos de rejeição.
- Remoção de `Log::info` com hash em `PublicExpenseController@show`.
- CORS: `array_filter` + `trim` nas origens.

---

## 5. Frontend: mudanças feitas

- `manage` da query string repassado a `getPublicExpense` e `identifyParticipant`.
- `mapPublicExpenseFromApi` aceita `members` (visão organizador) além de `participants`.
- `v1Fetch`: erro explícito em **403** (sem redirecionar como 401).

**Tokens (localStorage)**  
O token Sanctum permanece em `localStorage`. Em caso de XSS no mesmo origin do front, um script poderia lê-lo. Mitigações: evitar `dangerouslySetInnerHTML` com dados da API; o `chart.tsx` usa CSS só a partir de config estática do app, não da API.

---

## 6. Testes criados/alterados

- Novo: `tests/Feature/Security/SecurityHardeningTest.php` (manage inválido, rate limit login, extensão `.exe`, headers).
- Novo: `tests/Support/ProofUploadFixture.php` (JPEG mínimo para uploads reais nos testes).
- Ajustes: `PublicExpenseTest`, `PaymentValidationTest`, `ApiEnvelopeResponseTest` — `forceFill` para hashes; uploads com `ProofUploadFixture`.

---

## 7. Comandos executados

```bash
docker compose run --rm app php artisan test   # 89 passed
docker compose run --rm app composer audit     # No advisories
cd frontend && npm audit                       # 19 issues reported (transitive)
cd frontend && npm run build                   # OK
cd frontend && npm test                        # OK
```

**npm audit**: não foi aplicado `npm audit fix --force` para evitar upgrade quebrando Vite/Rollup sem validação manual.

---

## 8. Pontos que ainda merecem atenção

- Padronizar respostas de **auth** com `ApiResponse`.
- Política de **retention** e exclusão de arquivos em `storage/app` (LGPD).
- **WAF** / **fail2ban** ou rate limit na borda (Nginx/Cloudflare) em produção.
- **Dependabot** ou renovação periódica de pacotes npm/composer.
- **CSP** para o HTML servido pelo Laravel (`spa.blade.php`) — hoje foco foi API JSON.
- Testes E2E para fluxo `/p/:hash?manage=...` no React.

---

## 9. Checklist final de produção

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` definida
- [ ] HTTPS obrigatório (e HSTS ativo via middleware quando `$request->secure()`)
- [ ] `CORS_ALLOWED_ORIGINS` apenas domínios reais (sem `*`)
- [ ] Logs sem PII/tokens (revisar outros pontos da app)
- [ ] `storage/` e `public/spa/` com permissões corretas; sem listagem de diretório
- [ ] Rate limits ativos (Laravel + infra)
- [ ] Build React gerado (`npm run build` → `public/spa`)
- [ ] Testes passando (`php artisan test`)

---

## 10. Como rodar após as alterações

**Backend**

```bash
php artisan serve
```

**Frontend**

```bash
cd frontend
npm run dev
```

**Build**

```bash
cd frontend
npm run build
```

---

## OWASP Top 10 (verificação resumida)

| Categoria | Verificação |
|-----------|-------------|
| A01 Broken Access Control | Rotas Sanctum; `authorizeAdmin` / `AuthorizesPublicExpense` com `hash_equals`; testes de 403 |
| A02 Cryptographic Failures | HTTPS/HSTS em prod; tokens Sanctum; senhas hasheadas |
| A03 Injection | Form Requests; SQL via Eloquent |
| A04 Insecure Design | `manage_token` separado de link público; limites de upload |
| A05 Security Misconfiguration | Headers; CORS; `.env.example`; debug em prod |
| A06 Vulnerable Components | `composer audit` OK; `npm audit` com pendências |
| A07 Identification/Auth Failures | Bearer; logout revoga token; rate limit login |
| A08 Software/Data Integrity | Upload validado por conteúdo; nomes de arquivo controlados |
| A09 Logging/Monitoring | Remoção de log com hash público; orientação geral |
| A10 SSRF | Sem fluxo de URL arbitrária crítico nesta revisão |

---

## Recomendações não aplicadas automaticamente

- **`npm audit fix --force`**: pode atualizar major versions; avaliar em branch dedicada.
- **Cookies HttpOnly** em vez de localStorage exigiria fluxo Sanctum SPA diferente do Bearer atual — fora do escopo pedido.

---

## Atualização — revisão frontend (rotas públicas)

- **`publicV1Fetch` em `frontend/src/lib/api/client.ts`:** `POST /public/expenses/{hash}/validate-participant` não envia mais header `Authorization`. Isso evita (1) vazar o Bearer do organizador para um endpoint público quando participante e organizador usam o mesmo navegador e (2) respostas **401** no upload/identificação que disparavam `onUnauthorized()` e apagavam a sessão do painel.
- **`submitProof`:** removido tratamento que chamava `onUnauthorized()` em **401** no fluxo público; comprovante não deve deslogar o organizador por engano.
- **XSS — `chart.tsx`:** uso de `dangerouslySetInnerHTML` limitado a CSS gerado a partir de configuração de tema/cores do próprio componente (não dados da API). Manter assim; não reutilizar o padrão para texto de usuário.

---

## OWASP — estado atual (resumo)

As verificações da tabela na seção “OWASP Top 10” permanecem válidas. Pendências típicas: **npm** (cadeia de build/dev), padronizar **auth** no envelope `ApiResponse`, **CSP** no HTML da SPA se quiser camada extra contra XSS.

---

## Login — enumeração de e-mail (MVP / UX)

O endpoint `POST /api/v1/auth/login` diferencia resposta quando **o e-mail não está cadastrado** (`422`, código `ACCOUNT_NOT_FOUND`) vs **senha incorreta** (`401`, código `INVALID_CREDENTIALS`). Isso permite orientar o usuário ao cadastro, mas **permite descobrir se um endereço já possui conta**.

**Mitigações aceitas neste produto:** rate limit dedicado (`auth-login` em `routes/api.php`), não expor nome do usuário nem estado da conta; apenas a existência do e-mail.

Para ambientes com requisito anti-enumeração, avaliar resposta única genérica e fluxo de “esqueci minha senha” / magic link.

---

## Referência cruzada

- Checklist operacional: [PRODUCTION_CHECKLIST.md](./PRODUCTION_CHECKLIST.md)
- Visão de arquitetura e fluxos: [ARCHITECTURE.md](./ARCHITECTURE.md)

