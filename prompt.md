Você é um Tech Lead especialista em Laravel, React/Vite e experiência de produto.

Precisamos ajustar o fluxo de "modo demonstração" sem quebrar autenticação real.

Problema atual:
Quando o usuário clica em "Entrar em modo demonstração", o sistema autentica no usuário teste e salva a sessão como login normal. Ao voltar para a página principal, o usuário continua logado, gerando confusão e má experiência.

Objetivo:
Manter a opção de entrar em modo demonstração, mas separar claramente sessão demo de sessão real.

Regras desejadas:

1. Ao entrar em modo demonstração:

- limpar qualquer sessão real existente;
- autenticar no usuário demo como hoje;
- salvar uma flag explícita de demo, exemplo: contacerta:demo:v1 = true;
- redirecionar para o dashboard/demo normalmente.

2. Enquanto estiver em modo demonstração:

- exibir um banner visível no dashboard e páginas internas:
  "Você está em modo demonstração. Os dados exibidos são fictícios."
- incluir botão "Sair da demonstração";
- o botão deve limpar token, user e flag demo;
- redirecionar para a página principal ou login.

3. Ao acessar a página principal/landing page:

- se estiver em modo demonstração, limpar automaticamente a sessão demo;
- garantir que o usuário não continue logado como teste ao voltar para home.

4. Ao fazer login ou cadastro real:

- limpar qualquer flag de demo antes de salvar a sessão real.

5. Não quebrar:

- login real;
- cadastro real;
- logout;
- proteção de rotas;
- testes existentes;
- fluxo público de despesas.

Arquivos prováveis para revisar:

- frontend/src/lib/auth.tsx
- frontend/src/lib/api/client.ts
- frontend/src/pages/Home.tsx ou landing page equivalente
- frontend/src/pages/Dashboard.tsx
- componentes/layouts autenticados
- testes de auth/dashboard/demo

Implementar:

- helpers claros:
    - isDemoMode()
    - setDemoMode()
    - clearDemoMode()
    - clearAuthStorage()
    - exitDemoMode()
- garantir que logout também limpe demo;
- garantir que login/register limpem demo.

Adicionar ou ajustar testes:

- entrar em demo salva flag demo;
- sair da demo limpa token e flag;
- login real remove flag demo;
- acessar home em modo demo limpa sessão demo;
- dashboard mostra banner quando demo ativo.

Entrega final:

- explicar arquivos alterados;
- explicar novo fluxo;
- listar testes executados;
- apontar possíveis impactos no frontend.
