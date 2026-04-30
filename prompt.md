Corrigir exposição/uso indevido de dados reais e texto do organizador na página do participante.

Problemas:

1. Na página do participante ainda aparece o nome do organizador da cobrança.
2. Em labels/placeholders/exemplos de telefone está aparecendo um número real do usuário.
3. Todo campo de telefone deve usar exemplo genérico.

Tarefas:

## 1. Página do participante sem nome do organizador

Na página pública do participante, não exibir o nome real do organizador da cobrança.

Regras:

- Não mostrar "Organizado por {nome}" na visão do participante.
- Usar texto genérico:
  "Organizado pelo responsável da cobrança"
- Se já houver modo gestor com manage_token, avaliar se o nome pode aparecer apenas no modo gestor; para visitante/participante comum, manter genérico.
- Garantir que "Organizado por Organizador" também não volte.

Arquivos prováveis:

- frontend/src/pages/PublicExpense.tsx
- resources/views ou componentes relacionados ao link público
- testes da página pública

## 2. Remover número real de telefone de labels/placeholders

Buscar em todo o frontend e documentação qualquer número real usado como exemplo de telefone.

Substituir por exemplos genéricos:

- Placeholder preferencial:
  "(11) 99999-9999"

Ou, se quiser evitar DDD real:
"(00) 00000-0000"

Regras:

- Nenhum placeholder, label, helper text, teste visual ou mensagem deve conter número real do usuário.
- Isso vale para:
    - formulário de cadastro;
    - criação de cobrança;
    - validação de participante;
    - listagem/demo;
    - documentação se houver exemplo sensível.
- Manter máscara visual funcionando.
- Não alterar normalização interna do telefone.

Arquivos prováveis:

- frontend/src/pages/PublicExpense.tsx
- frontend/src/pages/NewExpense.tsx
- frontend/src/pages/Auth.tsx
- frontend/src/components/\*
- frontend/src/lib/\*
- README.md
- doc/API.md

## 3. Testes

Atualizar/adicionar testes:

- página pública do participante mostra:
  "Organizado pelo responsável da cobrança"
- página pública do participante não mostra nome real do organizador
- nenhum componente público usa telefone real como placeholder
- campos de telefone usam placeholder genérico
- testes existentes continuam passando

Executar:

- npm run test
- npm run build
- php artisan test, se ambiente permitir

Entrega final:

- arquivos alterados
- onde o nome do organizador foi removido
- quais placeholders de telefone foram trocados
- testes executados
- impactos
