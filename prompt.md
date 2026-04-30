Ajustar a Landing com a regra final de CTAs por estado de autenticação.

Regras finais:

HEADER:

1. Usuário deslogado:

- mostrar "Entrar"
- mostrar "Criar conta"

2. Usuário logado com conta real:

- mostrar "Sair"
- mostrar "Minhas cobranças"

3. Usuário em modo demo:

- ao acessar a landing, encerrar automaticamente a sessão demo;
- depois tratar como visitante deslogado.

BODY / HERO:

1. Usuário deslogado:

- mostrar "Criar conta grátis"
- mostrar "Entrar"
- mostrar "Ver demonstração"

2. Usuário logado com conta real:

- mostrar "Ir para minhas cobranças"
- mostrar "Criar nova cobrança"

3. Nunca mostrar:

- "Sair" no body/hero;
- "Ver demonstração" para usuário logado.

LOADING:

- Durante carregamento, evitar flicker com botões incorretos.

Arquivos prováveis:

- frontend/src/pages/Landing.tsx
- frontend/src/lib/auth.test.tsx
- outros componentes de CTA/header se existirem.

Testes esperados:

- visitante deslogado:
    - header mostra "Entrar" e "Criar conta";
    - body mostra "Criar conta grátis", "Entrar" e "Ver demonstração";
    - header não mostra "Ver demonstração".

- usuário logado:
    - header mostra "Sair" e "Minhas cobranças";
    - body mostra "Ir para minhas cobranças" e "Criar nova cobrança";
    - body não mostra "Sair";
    - body não mostra "Ver demonstração";
    - body não mostra "Entrar".

- usuário demo:
    - ao acessar landing, encerra demo automaticamente;
    - volta ao estado deslogado;
    - mostra CTAs de visitante.

Executar:

- npm run test
- npm run build

Entrega:

- arquivos alterados;
- comportamento final;
- testes executados;
- impactos no frontend.
