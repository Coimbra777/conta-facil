# Visão geral do produto

## O que é

**Conta Fácil** (marca na UI também como **ContaCerta Pix**) é um organizador de **cobranças compartilhadas via Pix**: um criador define um valor total, divide entre participantes, informa a chave Pix e compartilha um **link público**. Cada participante identifica-se (nome + telefone), vê sua fatia, paga no app do banco e envia **comprovante**; o organizador **valida ou rejeita** o comprovante.

## Problema que resolve

- Evita planilhas e cobranças manuais no WhatsApp sem controle de quem pagou.
- Um único link público por cobrança, com visão mínima para o participante e visão de gestão para quem tem o token de organizador.

## Principais fluxos

1. **Registro/login** (API Sanctum) → equipe padrão ou equipe criada.
2. **Criação de despesa** (time autenticado ou fluxo público sem conta) → participantes → cobranças (`charges`) com valores.
3. **Link público** `/p/{public_hash}` → participante informa nome/telefone → API confere participante na despesa.
4. **Pagamento** fora do sistema (Pix) → upload de comprovante.
5. **Validação/rejeição** (painel autenticado ou rotas públicas com `manage_token`) → transição de status.
6. **Fechamento da despesa** quando todas as cobranças estão `validated` (e regras de negócio atendidas).

## Entidades principais

| Entidade | Papel |
|----------|--------|
| `User` | Organizador autenticado |
| `Team` | Grupo; membros com papel `admin` ou não |
| `TeamMember` | Participante cadastral (nome, telefone, vínculo opcional com `User`) |
| `Expense` | Cobrança/despesa; `public_hash`, `manage_token`, Pix, total, vencimento, status |
| `Charge` | Parte de um participante na despesa; valor, status, `rejection_reason` |
| `PaymentProof` | Arquivo de comprovante ligado à cobrança |

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
