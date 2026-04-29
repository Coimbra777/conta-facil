import { digitsOnly, parseMoneyInput } from "@/lib/inputMasks";

export type ParticipantDraftRow = {
    name: string;
    phone: string;
    amount: string;
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
): { ok: true } | { ok: false; message: string } {
    if (participants.length === 0) {
        return {
            ok: false,
            message:
                'Clique em "dividir igualmente" ou informe o valor de cada participante.',
        };
    }

    const totalCents = Math.round(totalAmount * 100);
    let sumCents = 0;

    for (const p of participants) {
        if (p.name.trim().length === 0 || digitsOnly(p.phone).length < 10) {
            return {
                ok: false,
                message:
                    'Clique em "dividir igualmente" ou informe o valor de cada participante.',
            };
        }
        if (!p.amount.trim()) {
            return {
                ok: false,
                message:
                    'Clique em "dividir igualmente" ou informe o valor de cada participante.',
            };
        }
        const amt = parseMoneyInput(p.amount);
        if (!Number.isFinite(amt) || amt <= 0) {
            return {
                ok: false,
                message:
                    'Clique em "dividir igualmente" ou informe o valor de cada participante.',
            };
        }
        sumCents += Math.round(amt * 100);
    }

    if (Math.abs(sumCents - totalCents) > 2) {
        return {
            ok: false,
            message:
                "A soma dos participantes precisa ser igual ao valor total da cobrança.",
        };
    }

    return { ok: true };
}
