import {
    BRAZIL_PHONE_ERROR_MESSAGE,
    digitsOnly,
    isValidBrazilPhone,
    parseMoneyInput,
} from "@/lib/inputMasks";
import { formatBRL } from "@/lib/format";

export type ParticipantDraftRow = {
    name: string;
    phone: string;
    amount: string;
};

export type ParticipantDraftRowErrors = {
    name?: string;
    phone?: string;
    amount?: string;
};

export type ExpenseParticipantsValidationResult =
    | {
          ok: true;
          participantErrors: ParticipantDraftRowErrors[];
      }
    | {
          ok: false;
          message: string;
          participantErrors: ParticipantDraftRowErrors[];
      };

/** Total em reais entre `count` partes; centavos sobrando vão às primeiras parcelas. */
export function splitTotalEquallyInReais(total: number, count: number): number[] {
    if (count <= 0 || !Number.isFinite(total) || total <= 0) return [];
    const totalCents = Math.round(total * 100);
    const baseCents = Math.floor(totalCents / count);
    const remainder = totalCents % count;
    return Array.from({ length: count }, (_, i) => {
        const cents = baseCents + (i < remainder ? 1 : 0);
        return cents / 100;
    });
}

export function decimalToBrAmountInput(value: number): string {
    return value.toFixed(2).replace(".", ",");
}

/** Corpo JSON por participante (telefone só dígitos; valor decimal). */
export function buildParticipantsPayloadForApi(
    participants: ParticipantDraftRow[],
): Array<{ name: string; phone: string; amount: number }> {
    return participants.map((p) => ({
        name: p.name.trim(),
        phone: digitsOnly(p.phone),
        amount: Math.round(parseMoneyInput(p.amount) * 100) / 100,
    }));
}

export function validateExpenseParticipantsPayload(
    participants: ParticipantDraftRow[],
    totalAmount: number,
): ExpenseParticipantsValidationResult {
    if (participants.length === 0) {
        return {
            ok: false,
            message: "Adicione pelo menos um participante.",
            participantErrors: [],
        };
    }

    const totalCents = Math.round(totalAmount * 100);
    let sumCents = 0;
    const participantErrors = participants.map<ParticipantDraftRowErrors>(() => ({}));

    for (const [index, p] of participants.entries()) {
        if (p.name.trim().length === 0) {
            participantErrors[index].name = "Informe o nome do participante.";
        }

        if (digitsOnly(p.phone).length === 0) {
            participantErrors[index].phone =
                "Informe o telefone do participante.";
        } else if (!isValidBrazilPhone(p.phone)) {
            participantErrors[index].phone = BRAZIL_PHONE_ERROR_MESSAGE;
        }

        if (!p.amount.trim()) {
            participantErrors[index].amount = "Informe o valor do participante.";
            continue;
        }

        const amt = parseMoneyInput(p.amount);
        if (!Number.isFinite(amt) || amt <= 0) {
            participantErrors[index].amount =
                "O valor do participante deve ser maior que zero.";
            continue;
        }

        sumCents += Math.round(amt * 100);
    }

    const firstParticipantError = participantErrors
        .flatMap((row) => [row.name, row.phone, row.amount])
        .find((value) => typeof value === "string");
    if (firstParticipantError) {
        return {
            ok: false,
            message: firstParticipantError,
            participantErrors,
        };
    }

    if (sumCents + 2 < totalCents) {
        const missingAmount = (totalCents - sumCents) / 100;
        return {
            ok: false,
            message: `Faltam ${formatBRL(missingAmount)} para fechar o total da cobrança.`,
            participantErrors,
        };
    }

    return { ok: true, participantErrors };
}
