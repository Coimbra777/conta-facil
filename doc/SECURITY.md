# Segurança e hardening

Visão orientada ao estado atual: **Laravel 12 API** + **React/Vite SPA** + **MySQL**. Modelo de autorização por **criador da cobrança** (`expenses.created_by`) e, no fluxo público, por **`manage_token`** (query `manage` ou header `X-Manage-Token`), com `hash_equals` no servidor.

---

## 1. Superfícies principais

| Superfície | Controle |
|------------|----------|
| API autenticada | Sanctum **Bearer**; escopo por dono da despesa |
| API pública (participante) | Sem secrets; rate limit dedicado em mutações sensíveis |
| API pública (gestão) | `manage_token` separado do link amigável ao participante |
| Upload de comprovante | Validação por conteúdo (magic bytes), limites de tamanho/tipo, armazenamento com nomes não previsíveis |
| SPA | Token no `localStorage`; CORS restritivo em produção |

---

## 2. Autenticação e sessão

- **Bearer token** emitido pelo Sanctum; logout revoga o token atual.
- **Trade-off:** token em `localStorage` é simples, porém vulnerável a **XSS**. Mitigação: não injetar HTML não confiável; revisar uso de `dangerouslySetInnerHTML`; considerar **CSP** no HTML da SPA no futuro.
- Fluxos públicos (`validate-participant`, `submit-proof`) **não** devem enviar o Bearer do organizador — ver `frontend/src/lib/api/client.ts`.

---

## 3. Autorização de domínio

- Cobrança autenticada: apenas usuário com `created_by` correspondente (`ExpenseAuthorizer`).
- Gestão anônima: token opaco `manage_token`; comparação em tempo constante.
- Downloads de comprovante: mesmo critério de autorização dos fluxos autenticado ou público gerenciado.

---

## 4. Uploads e arquivos

- Tipos permitidos e validação reforçada no servidor (`PaymentProofService`).
- Nome seguro para `Content-Disposition` (`SafeDownloadFilename`).
- Diretório de storage não deve permitir execução de código.

---

## 5. Transporte e headers

- Produção: **HTTPS** obrigatório; middleware aplica **HSTS** quando a requisição é segura.
- **SecurityHeaders:** `X-Content-Type-Options`, `X-Frame-Options`, etc., no grupo API.

---

## 6. Rate limiting

- Throttle global na API + limitadores nomeados para login, registro, criação pública de despesa, validação de participante, envio de comprovante e ações com `manage`.

---

## 7. CORS

- Origens configuráveis via `CORS_ALLOWED_ORIGINS` (`.env`); sem curinga em produção.

---

## 8. Logging e privacidade

- Evitar registrar hashes públicos, tokens ou dados pessoais desnecessários.
- Definir política de retenção para comprovantes e logs.

---

## 9. Dependências

- Rodar periodicamente `composer audit` e `npm audit`; upgrades majors exigem regressão.

---

## 10. OWASP Top 10 (resumo)

| Categoria | Verificação |
|-----------|-------------|
| A01 Access control | Sanctum + checagens por dono da despesa / manage token |
| A02 Cryptographic failures | HTTPS; senhas hasheadas; tokens opacos |
| A03 Injection | Eloquent + Form Requests |
| A04 Insecure design | Separação participante vs. gestão; limites de upload |
| A05 Misconfiguration | `.env` produção; debug desligado; CORS explícito |
| A06 Vulnerable components | Auditorias Composer/npm |
| A07 Auth failures | Rate limit em login; mensagens de erro documentadas (enumeração de e-mail é trade-off do MVP — ver abaixo) |
| A08 Integrity | Validação de conteúdo de arquivo |
| A09 Logging | Reduzir PII em logs |
| A10 SSRF | Sem fluxo crítico de URL arbitrária neste escopo |

### Login e enumeração de e-mail

O login pode diferenciar “conta inexistente” e “senha incorreta” para melhor UX. Isso permite inferir se um e-mail está cadastrado. Mitigação parcial: **rate limit** dedicado. Para ambientes exigentes, avaliar resposta genérica única.

---

## 11. Melhorias futuras sugeridas

- **CSP** no shell Blade da SPA.
- **Sentry** (ou similar) para erros backend e frontend build.
- Cookies **HttpOnly** exigiriam fluxo SPA diferente do Bearer atual — decisão de produto.
- **WAF** / rate limit na borda (Nginx, CDN).

---

## Referências

- [PRODUCTION_CHECKLIST.md](./PRODUCTION_CHECKLIST.md)
- [ARCHITECTURE.md](./ARCHITECTURE.md)
