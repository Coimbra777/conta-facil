Você é um Tech Lead especialista em Laravel, segurança, APIs REST, LGPD e prontidão para produção.

Analise o repositório inteiro antes de alterar qualquer coisa. O objetivo é executar a FASE 1 — HARDENING do sistema ContaCerta/Conta Certa, deixando o sistema menos frágil para produção, sem mudar o escopo funcional principal.

Contexto do produto:
É um sistema de cobrança compartilhada com:

- criação de despesas/cobranças;
- participantes;
- link público via public_hash;
- manage_token para gestão pública;
- upload de comprovantes;
- fluxo de status: pending → proof_sent → validated/rejected;
- rejected pode voltar para pending;
- validated é terminal;
- estado closed bloqueia mutações.

Objetivo desta tarefa:
Implementar ajustes críticos de segurança, consistência, dados e robustez operacional da Fase 1.

NÃO faça:

- não reescreva a arquitetura inteira;
- não mude o fluxo de negócio principal;
- não remova funcionalidades existentes;
- não implemente S3 agora;
- não implemente infraestrutura AWS;
- não altere visual de forma desnecessária;
- não faça refactor cosmético grande.

Faça as alterações abaixo:

---

## 1. Minimização de dados sensíveis no UserResource

Problema:
O UserResource atualmente expõe campos sensíveis/desnecessários, como CPF.

Tarefa:

- Revisar app/Http/Resources/UserResource.php.
- Remover CPF da resposta padrão da API, salvo se houver uso funcional indispensável no frontend.
- Se o frontend precisar exibir algum dado, retornar apenas versão mascarada, exemplo: **_._**.**\*-**.
- Remover campos desnecessários como email_verified_at se não forem usados.
- Ajustar testes afetados.

Critério de aceite:

- Endpoint de usuário autenticado não deve expor CPF bruto.
- Frontend não deve quebrar.

---

## 2. Expiração e gestão básica de tokens Sanctum

Problema:
config/sanctum.php usa expiration null, deixando tokens válidos indefinidamente.

Tarefa:

- Configurar expiration via env, exemplo SANCTUM_TOKEN_EXPIRATION_MINUTES.
- Definir default seguro, por exemplo 43200 minutos, equivalente a 30 dias.
- Atualizar .env.example.
- Garantir que login continue emitindo token normalmente.
- Se houver logout, garantir revogação do token atual.
- Documentar comportamento no README ou doc/SECURITY.md.

Critério de aceite:

- Sanctum não deve ficar com expiration null em produção.
- Deve ser possível configurar a expiração por variável de ambiente.

---

## 3. Lifecycle de arquivos de comprovantes

Problema:
Ao excluir cobrança/despesa/prova, os registros podem ser removidos do banco, mas os arquivos físicos ficam órfãos no storage.

Tarefa:

- Localizar todos os fluxos que deletam:
    - PaymentProof;
    - Charge;
    - Expense;
    - participantes/cobranças associados.
- Garantir que antes/depois da remoção do registro, o arquivo correspondente também seja removido do Storage::disk configurado.
- Preferir encapsular isso em service dedicado ou método claro, evitando duplicação.
- Garantir que falha ao apagar arquivo não quebre indevidamente a operação principal, mas seja logada.
- Verificar reenvio após rejeição:
    - decidir e implementar uma política simples para MVP:
        - ou apagar provas antigas substituídas;
        - ou marcar apenas a última como ativa;
    - preferencialmente manter apenas a última prova ativa por charge e remover arquivo antigo quando uma nova prova for aceita.

Critério de aceite:

- Nenhum comprovante deve ficar órfão ao excluir despesa/cobrança/prova.
- Reenvio de comprovante não deve acumular arquivos indefinidamente.
- Deve haver teste cobrindo exclusão de arquivo.

---

## 4. Constraint de unicidade para participante por telefone na mesma despesa

Problema:
Existe validação na aplicação, mas a migration não garante UNIQUE(expense_id, phone_normalized), deixando risco de duplicidade em concorrência.

Tarefa:

- Criar migration para adicionar unique composto em expense_participants:
    - expense_id
    - phone_normalized
- Antes de adicionar a constraint, tratar dados duplicados existentes de forma segura:
    - se não houver script real necessário, ao menos documentar a estratégia;
    - se possível, criar migration defensiva que falhe com mensagem clara caso existam duplicados.
- Ajustar código para capturar erro de banco e retornar 422 amigável quando telefone duplicado ocorrer por corrida concorrente.
- Ajustar testes.

Critério de aceite:

- Banco impede duplicidade de telefone por despesa.
- API retorna erro amigável em caso de duplicidade.

---

## 5. Padronização de respostas da API

Problema:
Algumas respostas, principalmente auth, usam response()->json direto, enquanto outras usam ApiResponse.

Tarefa:

- Revisar AuthController e controllers relacionados.
- Padronizar respostas usando ApiResponse, se essa for a convenção do projeto.
- Manter compatibilidade com o frontend.
- Padronizar estrutura de erro quando possível:
    - message;
    - code;
    - errors, quando validação.
- Evitar breaking changes desnecessários.

Critério de aceite:

- Auth login/register/me/logout seguem padrão documentado.
- Frontend continua funcionando.

---

## 6. Padronização de mensagens e códigos de erro

Problema:
Há mensagens em inglês, exemplo "No proof found.", e erros sem code estável.

Tarefa:

- Buscar mensagens de erro em inglês ou inconsistentes.
- Padronizar mensagens principais para PT-BR.
- Criar/usar codes estáveis para erros importantes:
    - PROOF_NOT_FOUND;
    - EXPENSE_CLOSED;
    - INVALID_MANAGE_TOKEN;
    - DUPLICATED_PARTICIPANT_PHONE;
    - PARTICIPANT_ALREADY_VALIDATED;
    - PROOF_ALREADY_SENT.
- Ajustar testes e documentação.

Critério de aceite:

- Principais erros de domínio retornam mensagem em PT-BR e code estável.
- Testes existentes continuam passando.

---

## 7. CSP básica para SPA

Problema:
O sistema usa localStorage para token. Sem CSP, um XSS teria impacto maior.

Tarefa:

- Avaliar onde a SPA é servida: resources/views/spa.blade.php ou middleware.
- Adicionar uma Content-Security-Policy inicial e compatível com Vite build.
- Começar conservador e funcional:
    - default-src 'self';
    - script-src 'self';
    - style-src 'self' 'unsafe-inline' se necessário;
    - img-src 'self' data: blob:;
    - connect-src 'self' URLs necessárias da API/WhatsApp se aplicável;
    - frame-ancestors 'none';
- Garantir que ambiente local continue funcionando, ou condicionar a CSP por ambiente se necessário.
- Documentar em doc/SECURITY.md.

Critério de aceite:

- SPA continua carregando em produção/build.
- Há CSP mínima reduzindo risco de XSS.

---

## 8. Rate limits em rotas públicas sensíveis

Problema:
Rotas públicas podem sofrer abuso/scraping/upload repetido.

Tarefa:

- Revisar limitadores em AppServiceProvider/bootstrap/app.php.
- Garantir rate limit específico para:
    - GET /public/expenses/{hash};
    - validação de participante;
    - upload de comprovante;
    - download/preview de comprovante;
    - auth login/register.
- Usar nomes claros nos limiters.
- Documentar limites.

Critério de aceite:

- Rotas públicas sensíveis têm throttle específico.
- Abuso básico fica mitigado.

---

## 9. Paginação mínima em listagens autenticadas

Problema:
ExpenseController::index usa get() sem paginação.

Tarefa:

- Alterar listagem de despesas para paginação.
- Definir per_page default, exemplo 15.
- Permitir per_page limitado, exemplo máximo 50.
- Manter compatibilidade com frontend:
    - se o frontend espera array simples, adaptar frontend para ler data;
    - ou retornar envelope compatível com data/meta.
- Adicionar testes ou ajustar existentes.

Critério de aceite:

- Listagem não carrega tudo indefinidamente.
- Frontend lista despesas corretamente.

---

## 10. Testes obrigatórios

Adicionar ou ajustar testes para cobrir:

- UserResource não expõe CPF bruto;
- token Sanctum possui expiration configurável;
- exclusão de despesa remove arquivos de comprovante;
- reenvio de comprovante não acumula arquivo antigo;
- unique de participante por telefone;
- auth segue envelope esperado;
- erro de comprovante inexistente retorna PT-BR + code;
- listagem paginada funciona.

Rodar:

- composer test ou php artisan test;
- npm run test;
- npm run build.

Se algum teste não rodar por limitação do ambiente, documentar exatamente:

- comando executado;
- erro;
- provável causa;
- como validar localmente/CI.

---

## 11. Documentação

Atualizar:

- README.md, se necessário;
- doc/SECURITY.md;
- doc/PRODUCTION_CHECKLIST.md;
- doc/API.md, se contratos mudarem.

Documentar:

- SANCTUM_TOKEN_EXPIRATION_MINUTES;
- política de arquivos de comprovante;
- CSP;
- rate limits;
- paginação;
- mudança de CPF no UserResource;
- unique de telefone por despesa.

---

## 12. Entrega final esperada

No final, me entregue um relatório em Markdown com:

1. Resumo das alterações feitas.
2. Arquivos alterados.
3. Riscos resolvidos.
4. Possíveis impactos no frontend.
5. Novas variáveis de ambiente.
6. Migrations criadas.
7. Testes criados/alterados.
8. Comandos executados e resultados.
9. Pontos que ficaram para próxima fase.

Importante:
Antes de alterar, faça uma análise inicial e liste o plano.
Depois implemente em commits pequenos e lógicos, se possível:

- hardening-auth
- hardening-files
- hardening-db
- hardening-api-contracts
- hardening-security-headers
- hardening-pagination
- hardening-docs
