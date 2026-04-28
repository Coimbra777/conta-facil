# Features futuras sugeridas

Cada item: objetivo, valor, impacto no banco, API, telas, riscos de segurança, prioridade.

---

## Lembretes por WhatsApp

| | |
|--|--|
| **Objetivo** | Enviar lembrete automático antes do vencimento ou para quem está `pending`. |
| **Valor** | Reduz inadimplência e trabalho manual do organizador. |
| **Banco** | Tabela `notifications` ou `reminder_jobs`; campos `last_reminder_at` em `charges` / `expenses`. |
| **Endpoints** | `POST /teams/{team}/expenses/{expense}/reminders`, webhook de status (opcional). |
| **Telas** | Toggle “lembrar participantes”, histórico de envios. |
| **Riscos** | Spam, opt-in LGPD, segredo de API WhatsApp no backend apenas. |
| **Prioridade** | Média |

## Cobranças recorrentes

| | |
|--|--|
| **Objetivo** | Repetir mensalmente a mesma estrutura de participantes/valores. |
| **Valor** | Condomínios, mensalidades. |
| **Banco** | `expense_templates`, `recurrence_rule`, `parent_expense_id`. |
| **Endpoints** | CRUD templates, `POST .../spawn-next`. |
| **Telas** | Assistente de recorrência, lista de instâncias. |
| **Riscos** | Cobrança duplicada; idempotência no job. |
| **Prioridade** | Baixa |

## Convites por link (participante sem identificar telefone)

| | |
|--|--|
| **Objetivo** | Cada participante recebe link único com token opaco. |
| **Valor** | Menos fricção que nome+telefone; menos ambiguidade. |
| **Banco** | `team_members.invite_token` ou tabela `expense_invites`. |
| **Endpoints** | `GET /public/invite/{token}` resolve participante. |
| **Telas** | Gerar links por participante na UI. |
| **Riscos** | Token longo e secreto; expiração; não logar. |
| **Prioridade** | Alta |

## Importação CSV de participantes

| | |
|--|--|
| **Objetivo** | Colar/upload CSV nome, telefone, valor opcional. |
| **Valor** | Onboarding rápido para grupos grandes. |
| **Banco** | Apenas uso de tabelas existentes após parse. |
| **Endpoints** | `POST .../participants/import` (multipart ou JSON bulk). |
| **Telas** | Preview antes de confirmar; validação de linhas. |
| **Riscos** | CSV gigante (DoS); sanitização; limite de linhas. |
| **Prioridade** | Média |

## Dashboard financeiro mensal

| | |
|--|--|
| **Objetivo** | Totais por mês, taxa de conclusão, tempo médio até `validated`. |
| **Valor** | Visão para power users. |
| **Banco** | Queries agregadas; possível materialized view ou cache. |
| **Endpoints** | `GET /teams/{team}/stats?month=` |
| **Telas** | Gráficos (já existe `chart.tsx` com Recharts). |
| **Riscos** | Vazamento agregado entre equipes — checar escopo. |
| **Prioridade** | Baixa |

## Exportação CSV/PDF

| | |
|--|--|
| **Objetivo** | Exportar lista de participantes e status. |
| **Valor** | Contabilidade / arquivo. |
| **Banco** | Nenhum; leitura. |
| **Endpoints** | `GET .../expenses/{expense}/export?format=csv` |
| **Telas** | Botão exportar no detalhe. |
| **Riscos** | PDF/CSV com dados pessoais — auth forte. |
| **Prioridade** | Média |

## Comentários em comprovantes

| | |
|--|--|
| **Objetivo** | Organizador anota “valor divergente” sem rejeitar. |
| **Valor** | Comunicação assíncrona. |
| **Banco** | `payment_proof_comments` (user_id, body, timestamps). |
| **Endpoints** | nested em `charges` ou `proofs`. |
| **Telas** | Thread minimal na despesa. |
| **Riscos** | XSS — sanitizar/escape; limite de tamanho. |
| **Prioridade** | Baixa |

## Auditoria de ações

| | |
|--|--|
| **Objetivo** | Quem validou/rejeitou, IP, timestamp. |
| **Valor** | Compliance e disputas. |
| **Banco** | `audit_logs` (polimórfico ou por tipo). |
| **Endpoints** | apenas leitura admin `GET .../audit`. |
| **Telas** | Timeline na despesa. |
| **Riscos** | Retenção de dados pessoais; política de privacidade. |
| **Prioridade** | Média |

## Multi-organizador / permissões por equipe

| | |
|--|--|
| **Objetivo** | Vários admins ou papel “financeiro”. |
| **Valor** | Empresas. |
| **Banco** | `team_members.role` expandido, permissões granulares. |
| **Endpoints** | Policies centralizadas. |
| **Telas** | Gestão de membros rica. |
| **Riscos** | Erros de autorização — testes obrigatórios. |
| **Prioridade** | Média |

## Notificações por e-mail

| | |
|--|--|
| **Objetivo** | Resumo diário, “alguém pagou”. |
| **Valor** | Engajamento. |
| **Banco** | `notification_preferences`. |
| **Endpoints** | internos + queue. |
| **Telas** | preferências na conta. |
| **Riscos** | phishing — From correto, não expor tokens em e-mail. |
| **Prioridade** | Baixa |

## Webhooks

| | |
|--|--|
| **Objetivo** | Integrar com n8n, Zapier, ERP. |
| **Valor** | B2B. |
| **Banco** | `webhook_endpoints`, `webhook_deliveries`. |
| **Endpoints** | CRUD endpoints, assinatura HMAC. |
| **Telas** | Configuração avançada. |
| **Riscos** | SSRF se URL livre; replay — usar segredo e retry idempotente. |
| **Prioridade** | Baixa |

## Integração Pix (gateway) / QR EMV real

| | |
|--|--|
| **Objetivo** | Gerar cobrança com QR dinâmico e confirmação automática. |
| **Valor** | Fim do fluxo manual de comprovante. |
| **Banco** | `pix_txid`, status de liquidação. |
| **Endpoints** | Depende do PSP (Itaú, Gerencianet, etc.). |
| **Telas** | Opcional: mostrar QR oficial. |
| **Riscos** | PCI/PSP, segredos, webhook de confirmação. |
| **Prioridade** | Alta (produto) / esforço alto |

## Histórico de cobranças e templates

| | |
|--|--|
| **Objetivo** | Reutilizar “última festa” com os mesmos contatos. |
| **Valor** | Velocidade. |
| **Banco** | snapshot ou FK para template. |
| **Endpoints** | `POST .../duplicate`. |
| **Telas** | “Criar igual à anterior”. |
| **Riscos** | Baixo. |
| **Prioridade** | Média |

## Templates de cobrança

| | |
|--|--|
| **Objetivo** | Texto padrão (“Churras mensal”). |
| **Valor** | Branding leve. |
| **Banco** | `expense_templates` por team. |
| **Endpoints** | CRUD simples. |
| **Telas** | Picker na criação. |
| **Riscos** | XSS em descrição — mesma regra atual. |
| **Prioridade** | Baixa |

## Plano free / pro

| | |
|--|--|
| **Objetivo** | Limitar participantes, equipes ou histórico no free. |
| **Valor** | Monetização. |
| **Banco** | `subscriptions`, `plan`, limites. |
| **Endpoints** | middleware de quota. |
| **Telas** | paywall, upgrade. |
| **Riscos** | Bypass de limites — validar no servidor. |
| **Prioridade** | Média (negócio) |
