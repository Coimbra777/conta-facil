// Tipos compartilhados do domínio ContaCerta Pix.
// Status internos da API <-> labels exibidos na UI.

export type ApiStatus = "pending" | "proof_sent" | "validated" | "rejected";

export const STATUS_LABEL: Record<ApiStatus, string> = {
    pending: "Pendente",
    proof_sent: "Comprovante enviado",
    validated: "Pago",
    rejected: "Rejeitado",
};

export type PixKeyType =
    | "cpf"
    | "cnpj"
    | "email"
    | "phone"
    | "random"
    | "copia_cola";

export const PIX_KEY_LABEL: Record<PixKeyType, string> = {
    cpf: "CPF",
    cnpj: "CNPJ",
    email: "E-mail",
    phone: "Telefone",
    random: "Aleatória",
    copia_cola: "Copia e Cola",
};

export interface User {
    id: string;
    name: string;
    email: string;
    /** Telefone do perfil (API real); opcional. */
    phone?: string;
}

export interface LoginPayload {
    email: string;
    password: string;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    passwordConfirmation: string;
}

export interface Participant {
    id: string;
    name: string;
    phone: string;
    amount: number;
    status: ApiStatus;
    /** Indica se há arquivo de comprovante no servidor (última versão). */
    hasProof?: boolean;
    proofUrl?: string;
    proofSentAt?: string;
    rejectionReason?: string;
}

export interface Expense {
    id: string;
    publicHash: string;
    title: string;
    description?: string;
    totalAmount: number;
    /** `open` | `closed` na API pública; opcional em mocks antigos. */
    status?: string;
    dueDate?: string;
    pixKeyType: PixKeyType;
    pixKey: string;
    pixReceiverName: string;
    organizerName: string;
    participants: Participant[];
    /** Resumo quando GET público não lista participantes (privacidade). */
    participantsTotalCount?: number;
    validatedChargesCount?: number;
    openChargesCount?: number;
    /** Igual a `amount_per_participant` na API; é média quando há valores personalizados. */
    averageAmountPerParticipant?: number;
    createdAt: string;
    /** Resposta pública com token de gestão válido (header). */
    canManage?: boolean;
}

export interface ApiSuccess<T> {
    success: true;
    message?: string;
    data: T;
    meta?: Record<string, unknown>;
}

export interface ApiError {
    success: false;
    message: string;
    code?: string;
    errors?: Record<string, string[]>;
}

export type ApiResponse<T> = ApiSuccess<T> | ApiError;
