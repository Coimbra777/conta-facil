# Visão geral do produto

## O que é

**Conta Fácil** (marca na UI também como **ContaCerta Pix**) é um organizador de **cobranças compartilhadas via Pix**: um criador define um valor total, divide entre participantes, informa a chave Pix e compartilha um **link público**. Cada participante identifica-se (nome + telefone), vê sua fatia, paga no app do banco e envia **comprovante**; o organizador **valida ou rejeita** o comprovante.

## Problema que resolve

- Evita planilhas e cobranças manuais no WhatsApp sem controle de quem pagou.
- Um único link público por cobrança, com visão mínima para o participante e visão de gestão para quem tem o token de organizador.

## Principais fluxos

1. **Registro/login** (API Sanctum) → equipe padrão ou equipe criada.
2. **Criação de cobrança** (usuário logado: `POST /expenses` + participantes; ou fluxo público sem conta) → `ExpenseParticipant` + `charges`.
3. **Link público** `/p/{public_hash}` → participante informa nome/telefone → API confere participante na despesa.
4. **Pagamento** fora do sistema (Pix) → upload de comprovante.
5. **Validação/rejeição** (painel autenticado ou rotas públicas com `manage_token`) → transição de status.
6. **Fechamento da despesa** quando todas as cobranças estão `validated` (e regras de negócio atendidas).

## Entidades principais

| Entidade | Papel |
|----------|--------|
| `User` | Organizador autenticado |
| `Team` | Workspace/grupo interno ligado à despesa; rotas `/teams/...` mantidas |
| `TeamMember` | Cadastro legado/futuro no time (não é mais a fonte da verdade do participante na cobrança) |
| `Expense` | Cobrança/despesa; `public_hash`, `manage_token`, Pix, total, vencimento, status |
| `ExpenseParticipant` | Snapshot do participante **nesta** cobrança (nome, telefone, valor); histórico preservado |
| `Charge` | Parte devida por um participante; liga-se a `expense_participant_id` (preferencial) ou legado `team_member_id` |
| `PaymentProof` | Arquivo de comprovante ligado à cobrança |

O módulo `teams` / `team_members` foi preservado para futuras features como times de futebol, grupos recorrentes ou agenda de contatos, mas o fluxo principal de cobrança usa **`expense_participants`**.

## Status da cobrança (`Charge`)

| API | UI (exemplo) |
|-----|----------------|
| `pending` | Pendente |
| `proof_sent` | Comprovante enviado |
| `validated` | Pago |
| `rejected` | Rejeitado |

A despesa (`Expense`) tem também status agregado (ex.: `open`, `closed`) conforme migrations e serviços.

## Visão de produto

- **MVP forte** em fluxo público + gestão por link com `?manage=`.
- **SaaS**: multi-usuário por equipe, autenticação Bearer, CORS para SPA separada em dev.
- **Pix** hoje é **manual** (chave + cópia); não há gateway de pagamento integrado.
