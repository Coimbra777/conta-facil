# Checklist de produção

Use antes de liberar ambiente público.

## Aplicação Laravel

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` definida (`php artisan key:generate` já executado uma vez)
- [ ] `APP_URL` com HTTPS e domínio final
- [ ] MySQL: credenciais fortes; banco criado com `utf8mb4_unicode_ci`
- [ ] `php artisan migrate --force` aplicado
- [ ] `php artisan config:cache` e `route:cache` (se aplicável ao deploy)

## Segurança HTTP

- [ ] HTTPS obrigatório (redirect 80 → 443)
- [ ] `CORS_ALLOWED_ORIGINS` **sem** `*`; apenas origens do frontend
- [ ] Headers de segurança ativos na API (`SecurityHeaders` + config nginx)
- [ ] Rate limits ativos (Laravel + opcional WAF/Cloudflare)

## Storage e uploads

- [ ] `storage/app` (ou disco de comprovantes) **fora** de document root ou com `.htaccess`/rules que impeçam execução PHP
- [ ] `php artisan storage:link` se usar disco `public` para assets permitidos
- [ ] Permissões de arquivo adequadas ao usuário do PHP-FPM

## Filas e jobs

- [ ] `QUEUE_CONNECTION` não `sync` em produção se houver jobs pesados
- [ ] Worker supervisado (systemd/supervisor)
- [ ] Redis/credenciais seguras se usados

## Logs e monitoramento

- [ ] `LOG_LEVEL` adequado (`error` ou `warning` em prod)
- [ ] Sem logar tokens, `manage_token`, telefones completos ou conteúdo de comprovantes
- [ ] Alertas (Sentry, CloudWatch, etc.) opcionais

## Frontend

- [ ] `npm ci && npm run build` no diretório `frontend`
- [ ] Artefatos presentes em `public/spa/`
- [ ] `VITE_*` de build apontando para API pública correta
- [ ] Source maps: avaliar exposição (pode desabilitar ou restringir)

## Dados e backup

- [ ] Backup automático do MySQL
- [ ] Política de retenção de comprovantes / LGPD

## Qualidade

- [ ] `php artisan test` passando na CI
- [ ] `composer audit` sem vulnerabilidades críticas não mitigadas
- [ ] `npm audit` revisado (muitas issues podem ser só devDependencies)

## Pós-deploy smoke test

- [ ] Registro/login
- [ ] Criar despesa e participantes
- [ ] Link público abre; identificação e upload
- [ ] Validar/rejeitar comprovante
- [ ] Fechar despesa quando todos validados
