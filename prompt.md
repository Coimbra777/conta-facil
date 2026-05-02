Ajustar organização dos comprovantes no storage.

Problema:
Atualmente o sistema cria uma pasta separada para cada comprovante. Queremos organizar os arquivos por cobrança.

Nova regra:

- Cada cobrança deve ter uma pasta própria no storage.
- Todos os comprovantes daquela cobrança devem ficar dentro dessa pasta.
- O nome do arquivo deve permitir identificar o participante sem expor nome:
    - usar telefone normalizado + data/hora;
    - exemplo: payment-proofs/expense-{expense_id}/{phone_normalized}-{timestamp}.{ext}

Exemplo:
payment-proofs/expense-42/98997013066-20260502-184530.pdf

Regras:

1. Não usar nome do participante no arquivo.
2. Usar telefone normalizado.
3. Usar timestamp para evitar conflito.
4. Manter storage privado.
5. Manter validação por extensão/magic bytes.
6. Manter exclusão automática dos arquivos quando a cobrança for finalizada.
7. Manter comportamento de reenvio após rejeição:
    - quando um novo comprovante for enviado após rejeição, excluir o arquivo anterior.
8. Rejeitar comprovante NÃO deve obrigatoriamente excluir o arquivo imediatamente, porque o organizador pode precisar revisar histórico da rejeição enquanto a cobrança está aberta.
9. Após finalização da cobrança, todos os comprovantes devem ser excluídos e file_path deve virar null.

Revisar arquivos:

- app/Services/PaymentProofService.php
- app/Actions/Charge/RejectChargeAction.php
- app/Actions/Charge/ValidateChargeAction.php
- app/Services/ExpenseService.php
- app/Support/ChargeProofHttpResponse.php
- testes de PaymentProof/PublicExpense/PaymentValidation

Testes esperados:

- upload salva comprovante em pasta da cobrança;
- nome do arquivo contém telefone normalizado e timestamp;
- não cria pasta única por comprovante;
- reenvio após rejeição remove arquivo antigo;
- rejeição sozinha mantém arquivo disponível enquanto cobrança está aberta;
- finalização da cobrança remove todos os arquivos da pasta da cobrança;
- preview/download continua funcionando antes do fechamento;
- preview/download retorna PROOF_REMOVED_AFTER_EXPENSE_CLOSED após fechamento.

Executar:

- php artisan test
- npm run test
- npm run build

Entregar:

- arquivos alterados;
- novo padrão de path;
- regra confirmada sobre rejeição;
- testes executados;
- impacto no fluxo.
