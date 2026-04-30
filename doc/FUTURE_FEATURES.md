# Roadmap / ideias futuras

Itens sugeridos (objetivo, valor, impacto em banco/API/UI, riscos). **Não é compromisso de escopo.**

**Modelagem atual:** `users`, `expenses`, `expense_participants`, `charges`, `payment_proofs`.

---

## Convite por link por participante

Token opaco por linha de `expense_participants`; menos fricção que nome + telefone.

## Lembretes (WhatsApp / fila)

Antes do vencimento ou para cobranças `pending`; opt-in e LGPD.

## Notificações por e-mail

Resumo e alertas de pagamento; preferências por usuário.

## Dashboard financeiro

Agregações por período para o organizador (`created_by`); gráficos na SPA.

## Exportação CSV/PDF

Lista de participantes e status na despesa.

## Cobranças recorrentes

Templates e geração automática de novas instâncias.

## Importação em massa

CSV de participantes com validação e preview.

## QR Code Pix (BR Code) real + gateway

Integração com PSP para QR dinâmico e confirmação automática (substitui ou complementa upload manual).

## Monitoramento e observabilidade

Sentry ou equivalente; métricas e alertas em produção.

## PWA

Instalável no celular; cache offline leve para telas públicas.

## Segurança avançada

CSP no HTML da SPA; revisão periódica de dependências (`composer audit`, `npm audit`).

## Histórico / duplicar cobrança

Clonar estrutura de participantes para novo evento.

## Webhooks outbound

Integrações B2B com assinatura e retries idempotentes.

---

*(Lista anterior detalhada por tabela pode ser reintroduzida se o time voltar a priorizar specs longas por feature.)*
