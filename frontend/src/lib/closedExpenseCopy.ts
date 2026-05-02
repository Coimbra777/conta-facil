/** Textos de cobrança encerrada (`expense.status === "closed"`). */

export const PROOF_UPLOAD_AUTODELETE_NOTICE =
    "O comprovante será usado apenas para validação e será excluído automaticamente quando a cobrança for finalizada.";

export const PROOF_MANAGEMENT_AUTODELETE_NOTICE =
    "Ao finalizar a cobrança, os comprovantes enviados serão excluídos automaticamente.";

export const PROOF_REMOVED_AFTER_CLOSED_NOTICE =
    "Comprovantes excluídos após a finalização da cobrança.";

export const CLOSED_EXPENSE_ORGANIZER = {
    title: "Cobrança finalizada",
    lines: [
        "Todos os participantes tiveram seus pagamentos validados.",
        PROOF_REMOVED_AFTER_CLOSED_NOTICE,
        "Esta cobrança está encerrada e disponível apenas para consulta.",
    ],
} as const;

export const CLOSED_EXPENSE_PUBLIC_PARTICIPANT = {
    title: "Cobrança finalizada",
    lines: [
        "Todos os pagamentos desta cobrança já foram confirmados.",
        PROOF_REMOVED_AFTER_CLOSED_NOTICE,
        "Não é mais necessário enviar comprovante.",
    ],
} as const;

export const CLOSED_EXPENSE_SHARE_DISABLED_HINT =
    "Cobrança finalizada — compartilhamento indisponível.";
