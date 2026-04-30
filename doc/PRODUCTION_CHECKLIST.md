# Checklist de produção

## Aplicação

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` definida
- [ ] `APP_URL` com HTTPS e domínio final

## Banco

- [ ] MySQL com credenciais fortes
- [ ] Charset `utf8mb4` / collation adequada
- [ ] `php artisan migrate --force`

## HTTPS e rede

- [ ] HTTPS obrigatório (redirect 80 → 443)
- [ ] `CORS_ALLOWED_ORIGINS` apenas origens reais do frontend

## Logs

- [ ] `LOG_LEVEL` adequado (`error` / `warning`)
- [ ] Não logar tokens, `manage_token`, dados sensíveis de comprovantes

## Fila

- [ ] `QUEUE_CONNECTION` apropriado se houver jobs assíncronos
- [ ] Worker supervisado

## Backups

- [ ] Backup automatizado do MySQL e política de retenção

## Frontend

- [ ] `npm ci && npm run build` em `frontend/`
- [ ] Artefatos presentes em `public/spa/`
- [ ] `VITE_*` corretos **no momento do build**

## Smoke test

- [ ] Login / registro
- [ ] Criar cobrança e participantes
- [ ] Link público e upload
- [ ] Validar / rejeitar
