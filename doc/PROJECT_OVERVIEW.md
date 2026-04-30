# Visão geral do produto

## O que é

**ContaCerta Pix** (nome na UI; repositório também chamado **Conta Fácil**) organiza **cobranças compartilhadas via Pix**: o criador define um valor total, divide entre **participantes**, informa a chave Pix e compartilha um **link público**. Cada participante informa nome e telefone, vê o valor da sua parte, paga no app do banco e envia **comprovante**; o criador **aprova ou rejeita** no painel ou via link de gestão.

## Problema que resolve

- Reduz planilhas e cobranças soltas sem status de quem já pagou.
- Um link por cobrança: visão enxuta para quem paga e visão de gestão para quem tem o token do organizador.

## Quem usa

- **Organizador:** conta registrada (Sanctum) ou fluxo pontual sem conta (`POST /api/public/expenses`).
- **Participante:** qualquer pessoa com o link público; identifica-se com nome + telefone para ver status e enviar comprovante.

## Fluxo principal

1. Registro / login (quando usar conta).
2. Criação da cobrança e inclusão de participantes (`ExpenseParticipant` + `Charge`).
3. Compartilhamento do link `/p/{public_hash}`.
4. Pagamento Pix **fora** do sistema.
5. Upload do comprovante pelo participante.
6. Validação ou rejeição pelo criador (autenticado ou rotas públicas com `manage_token`).
7. Encerramento da despesa quando todas as cobranças estão validadas (conforme regras de negócio).

## Entidades principais

| Entidade | Papel |
|----------|--------|
| `User` | Organizador autenticado |
| `Expense` | Cobrança; Pix, total, vencimento, `public_hash`, `manage_token`, status; `created_by` opcional no fluxo anônimo |
| `ExpenseParticipant` | Snapshot do participante **nesta** cobrança (nome, telefone, valor) |
| `Charge` | Valor devido; ligado a um participante; na API aparece como **`participant`** |
| `PaymentProof` | Arquivo do comprovante |

**Cadeia:** `User → Expense → ExpenseParticipant → Charge → PaymentProof`.

## Status da cobrança (`Charge`)

| API | Significado |
|-----|-------------|
| `pending` | Aguardando comprovante |
| `proof_sent` | Comprovante enviado |
| `validated` | Pagamento confirmado pelo organizador |
| `rejected` | Comprovante recusado |

A despesa (`Expense`) agrega status (ex.: `open`, `closed`) conforme regras do backend.

## Visão de produto

- Forte suporte a **fluxo público** + gestão com `?manage=` / `X-Manage-Token`.
- **Multi-usuário:** cada conta vê apenas cobranças próprias (`created_by`).
- **Pix manual** no MVP (chave + QR opcional copiado); sem gateway de liquidação integrado.
