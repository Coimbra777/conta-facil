/**
 * Mensagens amigáveis em PT-BR para erros da API e rede.
 */

const BY_MESSAGE: Record<string, string> = {
    "Invalid credentials.": "E-mail ou senha inválidos.",
    "E-mail ou senha inválidos.": "E-mail ou senha inválidos.",
    "Unauthenticated.": "Sua sessão expirou. Faça login novamente.",
    "Successfully logged out.": "Você saiu da conta.",
    "Forbidden.": "Você não tem permissão para realizar esta ação.",
    "Not found.": "Registro não encontrado.",
    "Validation error.": "Verifique os campos informados.",
};

const BY_CODE: Record<string, string> = {
    ACCOUNT_NOT_FOUND:
        "Não encontramos uma conta com este e-mail.",
    INVALID_CREDENTIALS: "E-mail ou senha inválidos.",
    TOO_MANY_REQUESTS:
        "Muitas tentativas. Tente novamente em instantes.",
    UNAUTHENTICATED: "Sua sessão expirou. Faça login novamente.",
    FORBIDDEN: "Você não tem permissão para realizar esta ação.",
    NOT_FOUND: "Registro não encontrado.",
    VALIDATION_ERROR: "Verifique os campos informados.",
    EXPENSE_CANNOT_BE_DELETED:
        "Essa cobrança já possui movimentações e não pode ser excluída.",
};

export function mapHttpStatusToMessage(status?: number): string | undefined {
    if (status === undefined) return undefined;
    switch (status) {
        case 401:
            return BY_CODE.UNAUTHENTICATED;
        case 403:
            return BY_CODE.FORBIDDEN;
        case 404:
            return BY_CODE.NOT_FOUND;
        case 422:
            return "Verifique os campos informados.";
        case 429:
            return "Muitas tentativas. Tente novamente em instantes.";
        case 500:
        case 502:
        case 503:
            return "Servidor indisponível no momento. Tente novamente em instantes.";
        default:
            return undefined;
    }
}

export function mapApiErrorToUserMessage(input: {
    message: string;
    code?: string;
    status?: number;
    errors?: Record<string, string[]>;
}): string {
    const code = input.code;
    if (code && BY_CODE[code]) return BY_CODE[code];

    const normalizedMsg = input.message.trim();
    if (normalizedMsg && BY_MESSAGE[normalizedMsg]) {
        return BY_MESSAGE[normalizedMsg];
    }

    if (input.errors && Object.keys(input.errors).length > 0) {
        const first = Object.values(input.errors).flat()[0];
        if (first && typeof first === "string") return first;
    }

    const fromStatus = mapHttpStatusToMessage(input.status);
    if (fromStatus) return fromStatus;

    if (/network|fetch|failed to fetch/i.test(normalizedMsg)) {
        return "Sem conexão ou servidor indisponível. Verifique sua internet.";
    }

    return normalizedMsg || "Não foi possível concluir a operação.";
}
