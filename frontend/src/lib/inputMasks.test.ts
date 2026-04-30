import { describe, it, expect } from "vitest";
import { digitsOnly, formatBrazilPhoneDisplay } from "./inputMasks";

describe("formatBrazilPhoneDisplay", () => {
    it("formats progressive typing", () => {
        expect(formatBrazilPhoneDisplay("9")).toBe("(9");
        expect(formatBrazilPhoneDisplay("98")).toBe("(98");
        expect(formatBrazilPhoneDisplay("989")).toBe("(98) 9");
        expect(formatBrazilPhoneDisplay("9897013")).toBe("(98) 9701-3");
        expect(formatBrazilPhoneDisplay("98970130666")).toBe("(98) 97013-0666");
    });

    it("formats 10-digit Brazilian numbers", () => {
        expect(formatBrazilPhoneDisplay("1133334444")).toBe("(11) 3333-4444");
    });

    it("strips non-digits and caps at 11", () => {
        expect(formatBrazilPhoneDisplay("(98) 97013-0666")).toBe(
            "(98) 97013-0666",
        );
        expect(formatBrazilPhoneDisplay("989701306661234")).toBe(
            "(98) 97013-0666",
        );
    });
});

describe("digitsOnly", () => {
    it("extracts digits for API payload", () => {
        expect(digitsOnly("(98) 97013-0666")).toBe("98970130666");
    });
});
