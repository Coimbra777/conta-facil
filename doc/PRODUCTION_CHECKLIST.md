# Checklist de produção

## Aplicação

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` definida
- [ ] `APP_URL` com HTTPS e domínio final
- [ ] `SANCTUM_TOKEN_EXPIRATION_MINUTES` definido conforme política do ambiente

## Banco

- [ ] MySQL com credenciais fortes
- [ ] Charset `utf8mb4` / collation adequada
- [ ] `php artisan migrate --force`
- [ ] Validar ausência de telefones duplicados por despesa antes da migration da unique `expense_participants_expense_phone_unique`

## HTTPS e rede

- [ ] HTTPS obrigatório (redirect 80 → 443)
- [ ] `CORS_ALLOWED_ORIGINS` apenas origens reais do frontend
- [ ] Confirmar entrega da CSP do Laravel para a SPA em `/`

## Logs

- [ ] `LOG_LEVEL` adequado (`error` / `warning`)
- [ ] Não logar tokens, `manage_token`, dados sensíveis de comprovantes
- [ ] Monitorar warnings de falha ao remover arquivos órfãos de comprovante

## Fila

- [ ] `QUEUE_CONNECTION` apropriado se houver jobs assíncronos
- [ ] Worker supervisado

## Backups

- [ ] Backup automatizado do MySQL e política de retenção

## Frontend

- [ ] `npm ci && npm run build` em `frontend/`
- [ ] Artefatos presentes em `public/spa/`
- [ ] `VITE_*` corretos **no momento do build**
- [ ] Dashboard validado com paginação (`GET /api/v1/expenses`)

## Smoke test

- [ ] Login / registro
- [ ] `GET /api/v1/auth/me` sem CPF bruto
- [ ] Criar cobrança e participantes
- [ ] Link público e upload
- [ ] Reenvio após rejeição substitui o comprovante anterior
- [ ] Validar / rejeitar
- [ ] Download/preview de comprovante com autorização e throttle
