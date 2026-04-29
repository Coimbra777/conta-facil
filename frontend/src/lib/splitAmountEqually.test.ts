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
            { name: "A", phone: "(98) 99999-9999", amount: "33,34" },
            { name: "B", phone: "98888888888", amount: "33,33" },
        ]);
        expect(payload).toEqual([
            { name: "A", phone: "98999999999", amount: 33.34 },
            { name: "B", phone: "98888888888", amount: 33.33 },
        ]);
    });
});

describe("validateExpenseParticipantsPayload", () => {
    const rows = [
        { name: "A", phone: "98999999999", amount: "33,34" },
        { name: "B", phone: "98888888888", amount: "33,33" },
        { name: "C", phone: "98777777777", amount: "33,33" },
    ];

    it("aceita soma fechando com o total", () => {
        expect(validateExpenseParticipantsPayload(rows, 100)).toEqual({ ok: true });
    });

    it("recusa amount vazio", () => {
        expect(
            validateExpenseParticipantsPayload(
                [{ name: "A", phone: "98999999999", amount: "" }],
                10,
            ).ok,
        ).toBe(false);
    });

    it("recusa soma diferente do total", () => {
        expect(
            validateExpenseParticipantsPayload(
                [
                    { name: "A", phone: "98999999999", amount: "10,00" },
                    { name: "B", phone: "98888888888", amount: "10,00" },
                ],
                100,
            ).ok,
        ).toBe(false);
    });
});
