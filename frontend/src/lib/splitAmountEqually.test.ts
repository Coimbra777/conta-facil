import { describe, expect, it } from "vitest";
import {
    buildParticipantsPayloadForApi,
    splitTotalEquallyInReais,
    validateExpenseParticipantsPayload,
} from "./splitAmountEqually";

describe("splitTotalEquallyInReais", () => {
    it("divide R$ 100 entre 3 como 33.34 / 33.33 / 33.33", () => {
        expect(splitTotalEquallyInReais(100, 3)).toEqual([33.34, 33.33, 33.33]);
    });

    it("soma exatamente o total informado", () => {
        const parts = splitTotalEquallyInReais(10, 3);
        const sum = Math.round(parts.reduce((s, x) => s + x, 0) * 100) / 100;
        expect(sum).toBe(10);
    });
});

describe("buildParticipantsPayloadForApi", () => {
    it("envia amount numérico e telefone só dígitos", () => {
        const payload = buildParticipantsPayloadForApi([
            { name: "A", phone: "(11) 99999-9999", amount: "33,34" },
            { name: "B", phone: "11988888888", amount: "33,33" },
        ]);
        expect(payload).toEqual([
            { name: "A", phone: "11999999999", amount: 33.34 },
            { name: "B", phone: "11988888888", amount: 33.33 },
        ]);
    });
});

describe("validateExpenseParticipantsPayload", () => {
    const rows = [
        { name: "A", phone: "11999999999", amount: "33,34" },
        { name: "B", phone: "11988888888", amount: "33,33" },
        { name: "C", phone: "11977777777", amount: "33,33" },
    ];

    it("aceita soma fechando com o total", () => {
        expect(validateExpenseParticipantsPayload(rows, 100)).toEqual({
            ok: true,
            participantErrors: [{}, {}, {}],
        });
    });

    it("recusa amount vazio", () => {
        expect(
            validateExpenseParticipantsPayload(
                [{ name: "A", phone: "11999999999", amount: "" }],
                10,
            ),
        ).toEqual({
            ok: false,
            message: "Informe o valor do participante.",
            participantErrors: [{ amount: "Informe o valor do participante." }],
        });
    });

    it("recusa soma diferente do total", () => {
        const result = validateExpenseParticipantsPayload(
            [
                { name: "A", phone: "11999999999", amount: "10,00" },
                { name: "B", phone: "11988888888", amount: "10,00" },
            ],
            100,
        );

        expect(result).toMatchObject({
            ok: false,
            participantErrors: [{}, {}],
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.message).toMatch(
                /Faltam R\$\s*80,00 para fechar o total da cobrança\./,
            );
        }
    });

    it("aceita soma acima do total", () => {
        expect(
            validateExpenseParticipantsPayload(
                [
                    { name: "A", phone: "11999999999", amount: "70,00" },
                    { name: "B", phone: "11988888888", amount: "50,00" },
                ],
                100,
            ),
        ).toEqual({ ok: true, participantErrors: [{}, {}] });
    });
});
