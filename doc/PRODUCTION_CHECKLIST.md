# Checklist de produção

Antes de expor o ambiente publicamente.

## Aplicação Laravel

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` definida (`php artisan key:generate` em setup seguro)
- [ ] `APP_URL` com HTTPS e domínio final
- [ ] MySQL: credenciais fortes; charset `utf8mb4_unicode_ci`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache` e `php artisan route:cache` quando fizer sentido ao deploy

## Segurança HTTP e API

- [ ] HTTPS obrigatório (redirect 80 → 443)
- [ ] `CORS_ALLOWED_ORIGINS` **sem** `*`; apenas origens reais do frontend
- [ ] Headers de segurança na API (`SecurityHeaders` + Nginx)
- [ ] Rate limits Laravel ativos; considerar camada na borda (WAF / Cloudflare)

## Storage e uploads

- [ ] Comprovantes em `storage/app` (ou disco dedicado): sem execução de PHP sob o path servido
- [ ] `php artisan storage:link` se usar disco `public` para assets estáticos
- [ ] Permissões adequadas ao usuário do PHP-FPM
- [ ] Política de retenção / exclusão de arquivos (LGPD)

## Filas e workers

- [ ] `QUEUE_CONNECTION` adequado (não `sync` se houver jobs assíncronos)
- [ ] Worker supervisado (systemd / supervisor / container dedicado)
- [ ] Credenciais Redis seguras se aplicável

## Logs e monitoramento

- [ ] `LOG_LEVEL` (`error` / `warning` em produção)
- [ ] Não logar tokens, `manage_token`, PII sensível nem conteúdo de comprovantes
- [ ] Alertas (Sentry, CloudWatch, etc.) conforme política da operação

## Frontend

- [ ] `npm ci && npm run build` em `frontend/`
- [ ] Artefatos em `public/spa/` versionados ou gerados na pipeline
- [ ] `VITE_API_BASE_URL` (ou URLs relativas) apontando para a API pública correta **no momento do build**
- [ ] Avaliar exposição de source maps

## Backups e dados

- [ ] Backup automático do MySQL (e testes de restore)
- [ ] Política de retenção documentada

## Qualidade e dependências

- [ ] `php artisan test` na CI
- [ ] `composer audit` sem vulnerabilidades críticas não mitigadas
- [ ] `npm audit` revisado (muitos achados podem ser só devDependencies)

## Smoke test pós-deploy

- [ ] Registro / login
- [ ] Criar cobrança e participantes
- [ ] Link público; identificar participante e upload
- [ ] Validar / rejeitar comprovante
- [ ] Fechar despesa quando todas as cobranças validadas
