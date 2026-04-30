Precisamos fazer ajustes funcionais e visuais no sistema de cobrança compartilhada.

Contexto:
O projeto não utilizará CPF. Também precisamos melhorar alguns textos e visualizações na interface.

Tarefas:

## 1. Remover CPF do sistema

O CPF não será utilizado no produto.

Revisar e remover CPF dos fluxos de cadastro/usuário:

- RegisterRequest
- migrations/model se necessário sem quebrar banco existente
- frontend de cadastro
- types/interfaces do frontend
- testes de auth
- documentação

Regras:

- Cadastro deve funcionar sem CPF.
- API não deve exigir CPF.
- Frontend não deve mostrar ou enviar CPF.
- Se o campo já existir no banco, não precisa necessariamente dropar agora se isso gerar risco, mas o sistema não deve mais coletar nem depender dele.
- Atualizar docs/LGPD informando que CPF não é coletado.

Critérios:

- Usuário consegue criar conta sem CPF.
- Nenhum formulário novo pede CPF.
- Testes atualizados.

---

## 2. Máscara visual de telefone na listagem de participantes

Problema:
Na listagem de participantes, o telefone aparece sem formatação ou pouco legível.

Tarefa:

- Criar ou reutilizar helper de formatação de telefone.
- Exibir telefone com máscara brasileira.

Exemplos:

- 11999998888 → (11) 99999-8888
- 1133334444 → (11) 3333-4444

Regras:

- A máscara é apenas visual.
- Não alterar o valor normalizado salvo no banco.
- Não quebrar validações existentes.
- Aplicar nas listagens de participantes e cobranças onde fizer sentido.

Critérios:

- Participantes exibem telefone formatado.
- Backend continua recebendo/enviando dados como antes.

---

## 3. Corrigir texto “Organizado por Organizador”

Na página pública/compartilhada aparece:

"Organizado por Organizador"

Isso está ruim/repetitivo.

Tarefa:

- Localizar onde esse texto é renderizado.
- Corrigir para um texto mais natural.

Sugestões:

- Se houver nome real do organizador:
  "Organizado por Gabriel"
- Se não houver nome:
  "Organizado pelo responsável da cobrança"
- Evitar repetir "Organizador".

Critérios:

- Não deve aparecer mais "Organizado por Organizador".
- Texto deve ficar natural mesmo quando não houver nome do usuário.

---

## 4. Melhorar explicação da Diferença no resumo da cobrança

Problema:
O campo "Diferença" no resumo não deixa claro o significado.

Regra de negócio:

- Diferença positiva significa que ainda falta valor para fechar o total.
- Diferença negativa significa que há valor excedente e esse valor ficará em caixa.
- Diferença zero significa que a cobrança está balanceada corretamente.

Tarefa:

- Melhorar a UI/texto do resumo da cobrança.
- Exibir uma mensagem explicativa junto da diferença.

Exemplos:

Se diferença > 0:
"Faltam R$ 20,00 para fechar o total da cobrança."

Se diferença < 0:
"Há R$ 15,00 excedente. Esse valor ficará em caixa."

Se diferença == 0:
"Os valores estão balanceados."

Importante:

- Usar valor absoluto na mensagem quando for negativo.
- Manter o número original se ele já for exibido, mas deixar a explicação clara.
- Aplicar isso no resumo da criação/edição de cobrança onde esse campo aparece.

Critérios:

- Usuário entende claramente o que significa diferença positiva, negativa ou zero.
- Não alterar regra de cálculo sem necessidade.
- Apenas melhorar apresentação e texto.

---

## 5. Testes

Atualizar/adicionar testes para:

- cadastro sem CPF;
- frontend não renderiza campo CPF;
- telefone aparece formatado;
- texto "Organizado por Organizador" não aparece;
- diferença positiva mostra mensagem de valor faltante;
- diferença negativa mostra mensagem de valor excedente/caixa;
- diferença zero mostra mensagem balanceada.

Executar:

- php artisan test
- npm run test
- npm run build

Entrega final:

- arquivos alterados;
- mudanças feitas;
- testes executados;
- impactos no frontend/backend;
- qualquer ponto pendente.
