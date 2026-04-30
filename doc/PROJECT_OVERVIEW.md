# Visão geral do produto

## O que é

**ContaCerta Pix** é uma aplicação para **cobranças compartilhadas via Pix**: o organizador define o valor total, cadastra **participantes**, informa a chave Pix e compartilha um **link público**. Quem deve pagar identifica-se, envia **comprovante**, e o organizador **aprova ou rejeita**.

## Problema que resolve

Substituir planilhas e cobranças informais sem visibilidade de quem já pagou ou pendências por participante.

## Fluxo simples

1. Cadastro / login (organizador) ou criação pública sem conta.
2. Criação da cobrança e inclusão de participantes com valores.
3. Distribuição do link público (`/p/{hash}`).
4. Pagamento Pix fora do sistema.
5. Upload do comprovante pelo participante.
6. Validação ou rejeição pelo organizador (painel ou gestão via token).

## Entidades principais

| Entidade | Papel |
|----------|--------|
| **User** | Organizador autenticado |
| **Expense** | Cobrança (Pix, total, prazo, link público, token de gestão) |
| **ExpenseParticipant** | Snapshot do participante naquela cobrança |
| **Charge** | Valor devido e ciclo de status por participante |
| **PaymentProof** | Arquivo do comprovante ligado à cobrança |

Relação: `User → Expense → ExpenseParticipant → Charge → PaymentProof`.

## Estados da cobrança individual (`Charge`)

- `pending` — aguardando comprovante  
- `proof_sent` — comprovante enviado  
- `validated` — aprovado pelo organizador  
- `rejected` — recusado  

A **Expense** também tem status agregado (`open` / `closed`) conforme regras do backend.
