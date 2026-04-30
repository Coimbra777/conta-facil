import { describe, it, expect } from "vitest";
import {
    digitsOnly,
    formatBrazilPhoneDisplay,
    GENERIC_BRAZIL_PHONE_PLACEHOLDER,
} from "./inputMasks";

describe("formatBrazilPhoneDisplay", () => {
    it("formats progressive typing", () => {
        expect(formatBrazilPhoneDisplay("9")).toBe("(9");
        expect(formatBrazilPhoneDisplay("11")).toBe("(11");
        expect(formatBrazilPhoneDisplay("119")).toBe("(11) 9");
        expect(formatBrazilPhoneDisplay("1199999")).toBe("(11) 9999-9");
        expect(formatBrazilPhoneDisplay("11999999999")).toBe(
            GENERIC_BRAZIL_PHONE_PLACEHOLDER,
        );
    });

    it("formats 10-digit Brazilian numbers", () => {
        expect(formatBrazilPhoneDisplay("1133334444")).toBe("(11) 3333-4444");
    });

    it("strips non-digits and caps at 11", () => {
        expect(formatBrazilPhoneDisplay(GENERIC_BRAZIL_PHONE_PLACEHOLDER)).toBe(
            GENERIC_BRAZIL_PHONE_PLACEHOLDER,
        );
        expect(formatBrazilPhoneDisplay("119999999991234")).toBe(
            GENERIC_BRAZIL_PHONE_PLACEHOLDER,
        );
    });
});

describe("digitsOnly", () => {
    it("extracts digits for API payload", () => {
        expect(digitsOnly(GENERIC_BRAZIL_PHONE_PLACEHOLDER)).toBe("11999999999");
    });
});
