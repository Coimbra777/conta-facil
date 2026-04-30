Você é um desenvolvedor Frontend Sênior especialista em React/Vite, TypeScript, Context API, autenticação, rotas protegidas, UX e segurança no frontend.

Preciso que você corrija o fluxo de autenticação do ContaCerta.

## Contexto

Hoje o sistema tem um problema:

- o token fica salvo no `localStorage`;
- o `user` fica apenas no estado React;
- `ProtectedRoute` depende de `user`;
- `api.me()` roda só uma vez no mount;
- se `api.me()` falha, o sistema pode ficar com token salvo, mas `user = null`;
- ao navegar para a landing ou voltar ao painel, o usuário parece deslogado;
- a landing não reflete usuário logado;
- login/cadastro não redirecionam corretamente quando já existe sessão;
- ao criar conta, o usuário deve sair logado automaticamente.

## Objetivo

Reestruturar o fluxo de login usando `AuthContext`, `loading`, persistência de sessão e boas práticas de segurança.

---

# Regras obrigatórias

## 1. AuthContext como fonte central

Usar/ajustar o contexto de autenticação existente.

O contexto deve expor algo equivalente a:

```ts
type AuthContextValue = {
  user: User | null;
  loading: boolean;
  isAuthenticated: boolean;
  login: (payload: LoginPayload) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  loginDemo?: () => Promise<void>;
};

Não duplicar lógica de autenticação em páginas.

2. Hidratação inicial segura

No AuthProvider:

ao montar a aplicação, verificar se existe token salvo;
se existir token, chamar api.me();
enquanto isso, manter loading = true;
se api.me() retornar usuário, preencher user;
se retornar 401, limpar token e user;
se falhar por rede/500, tratar de forma segura e não deixar estado quebrado;
nunca deixar a aplicação presa em loading.

Critério importante:

ProtectedRoute não pode redirecionar antes da hidratação terminar.
3. Login

Ao fazer login:

sair do modo demo, se estiver ativo;
chamar API real;
salvar token;
normalizar usuário;
atualizar user;
redirecionar para:
redirect da query string, se existir e for seguro;
ou /dashboard.
4. Cadastro com login automático

Ao criar conta:

chamar endpoint de cadastro;
receber token + user;
salvar token;
atualizar user;
redirecionar automaticamente para /dashboard ou para o redirect.

O usuário não deve precisar logar manualmente após cadastro.

5. Logout

Ao sair:

chamar endpoint de logout se existir;
limpar token;
limpar user;
limpar modo demo, se fizer sentido;
redirecionar para /;
mesmo se a chamada de logout falhar, limpar estado local.
6. ProtectedRoute

Ajustar para:

se loading, mostrar tela/spinner de carregamento;
se não tem user, redirecionar para /login?redirect=rota_atual;
preservar pathname + search;
usar replace;
nunca renderizar conteúdo protegido antes da validação.
7. Landing

Atualizar Landing.tsx para consultar useAuth.

Usuário deslogado

Mostrar:

Criar conta grátis
Entrar
Ver demonstração
Usuário logado

Mostrar:

Ir para minhas cobranças
Criar nova cobrança
Sair
Ver demonstração, se fizer sentido

Texto sugerido:

Você está logado.
Continue gerenciando suas cobranças.
8. Login/Cadastro com usuário já logado

Em Auth.tsx:

se loading, mostrar carregamento;
se user já existe, redirecionar para /dashboard ou redirect seguro;
não mostrar formulário de login/cadastro para usuário autenticado.
9. Segurança no frontend

Aplicar boas práticas:

não salvar senha;
não colocar token em query string;
não logar token no console;
não misturar sessão real com demo;
limpar token em 401;
evitar flash de conteúdo protegido;
aceitar redirect apenas interno.

Criar helper se necessário:

export function getSafeRedirect(value: string | null, fallback = "/dashboard"): string {
  if (!value || !value.startsWith("/") || value.startsWith("//")) {
    return fallback;
  }

  return value;
}
Demo/mock

Manter demo funcionando.

Garantir:

loginDemo não sobrescreve token real indevidamente;
demo usa storage separado;
/demo continua pública;
/p/demo-churrasco-2025 continua usando mock;
sair do modo demo não quebra login real.
Arquivos para revisar

Verificar principalmente:

frontend/src/lib/auth.tsx
frontend/src/lib/api/client.ts
frontend/src/components/ProtectedRoute.tsx
frontend/src/pages/Auth.tsx
frontend/src/pages/Landing.tsx
frontend/src/pages/Dashboard.tsx
frontend/src/App.tsx
frontend/src/lib/types.ts
frontend/src/lib/api/mockStore.ts

Procure por:

AuthContext
useAuth
ProtectedRoute
login
register
logout
loginDemo
api.me
authMe
getToken
setToken
onUnauthorized
localStorage
contacerta:auth:v1
contacerta:demo:v1
Testes esperados

Adicionar ou ajustar testes, se já houver estrutura com Vitest/RTL:

login salva sessão e redireciona;
cadastro salva sessão e redireciona;
refresh com token válido restaura usuário;
refresh sem token não autentica;
landing mostra CTAs diferentes quando logado;
login/cadastro redirecionam quando já logado;
ProtectedRoute aguarda loading;
logout limpa sessão;
redirect externo é bloqueado;
demo não sobrescreve auth real.
Comandos obrigatórios

Executar:

npm run build
npm run test -- --run

Se algum comando falhar, explicar o motivo e o que foi feito.

Critérios de aceite
Usuário logado continua autenticado ao navegar pela aplicação.
Usuário logado continua autenticado após reload se token válido.
Landing mostra estado logado.
Login/cadastro redirecionam se usuário já está logado.
Cadastro já autentica automaticamente.
ProtectedRoute não redireciona antes do loading finalizar.
Logout limpa corretamente.
Token é enviado nas chamadas autenticadas.
401 limpa sessão.
Demo continua isolada.
Build e testes passam.
Entrega esperada

Ao final, retorne:

Arquivos alterados.
Como ficou o AuthContext.
Como funciona a hidratação inicial.
Como funciona login/cadastro/logout.
Como ficou a landing para logado/deslogado.
Como ficou o ProtectedRoute.
Testes criados/alterados.
Testes executados.
Riscos ou pendências restantes.
```
