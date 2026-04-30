Você é um Tech Lead responsável por aprovar ou bloquear o deploy de produção.

Faça um review completo do projeto antes da Fase 2/deploy.

Analise:

1. Segurança

- APP_DEBUG
- CORS
- headers
- CSP
- tokens
- rotas públicas
- uploads
- autorização
- vazamento de dados sensíveis

2. Banco

- migrations
- índices
- constraints
- cascades
- dados duplicados
- rollback seguro

3. API

- envelope padrão
- erros com code
- status HTTP
- paginação
- compatibilidade com frontend

4. Frontend

- build
- envs
- fluxo auth/demo
- rotas protegidas
- tratamento de 401/422/429

5. Arquivos

- comprovantes
- storage privado
- limpeza de arquivos
- risco de arquivos órfãos

6. Deploy

- Dockerfile/docker-compose
- Nginx
- permissões de storage/bootstrap/cache
- queue worker
- scheduler
- logs
- backups

7. Testes

- php artisan test
- npm run test
- npm run build
- gaps de cobertura

8. LGPD

- CPF
- privacidade
- exclusão de dados
- retenção de comprovantes

No final, entregue:

- status: aprovado / aprovado com ressalvas / bloqueado
- bloqueantes
- melhorias antes do deploy
- melhorias pós-deploy
- checklist final de produção
- comandos para validar localmente
