import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import PublicExpense from "./PublicExpense";
import { api, isPublicExpenseUsingMock } from "@/lib/api/client";
import type { Expense } from "@/lib/types";
import { DEMO_PRESENTATION_PUBLIC_HASH } from "@/lib/api/mockStore";

const IDENTIFY_SNIPPET = /Não encontramos esses dados nesta cobrança/i;

function renderRoute(hashPath: string) {
    return render(
        <MemoryRouter initialEntries={[hashPath]}>
            <Routes>
                <Route path="/p/:hash" element={<PublicExpense />} />
            </Routes>
        </MemoryRouter>,
    );
}

function baseExpense(overrides: Partial<Expense> = {}): Expense {
    return {
        id: "t1",
        publicHash: "other-hash",
        title: "Teste",
        totalAmount: 100,
        pixKeyType: "email",
        pixKey: "x@y.com",
        pixReceiverName: "Org",
        organizerName: "Org",
        participants: [
            {
                id: "p1",
                name: "Alice",
                phone: "11999999999",
                amount: 100,
                status: "pending",
            },
        ],
        createdAt: new Date().toISOString(),
        ...overrides,
    };
}

describe("PublicExpense", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        localStorage.clear();
    });

    it("footer renders short and long copy (both in DOM for responsive CSS)", () => {
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(baseExpense());
        renderRoute("/p/other-hash");
        return waitFor(() => {
            expect(
                screen.getByText(/ContaCerta Pix não movimenta seu dinheiro/i),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/acompanhar os comprovantes/i),
            ).toBeInTheDocument();
        });
    });

    it("back link points to home", async () => {
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(baseExpense());
        renderRoute("/p/other-hash");
        await waitFor(() =>
            expect(
                screen.getByRole("link", { name: /Voltar para o início/i }),
            ).toHaveAttribute("href", "/"),
        );
    });

    it("phone field shows Brazilian mask", async () => {
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(baseExpense());
        renderRoute("/p/other-hash");
        await waitFor(() =>
            expect(screen.getByPlaceholderText("(98) 97013-0666")).toBeInTheDocument(),
        );
        const tel = screen.getByPlaceholderText("(98) 97013-0666");
        fireEvent.change(tel, { target: { value: "98970130666" } });
        expect(tel).toHaveValue("(98) 97013-0666");
    });

    it("does not render the repeated organizer fallback copy", async () => {
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(
            baseExpense({ organizerName: "" }),
        );
        renderRoute("/p/other-hash");
        await waitFor(() =>
            expect(
                screen.getByText(/Organizado pelo responsável da cobrança/i),
            ).toBeInTheDocument(),
        );
        expect(
            screen.queryByText(/Organizado por Organizador/i),
        ).not.toBeInTheDocument();
    });

    it("identifyParticipant receives digits-only phone", async () => {
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(
            baseExpense({ publicHash: DEMO_PRESENTATION_PUBLIC_HASH }),
        );
        const spy = vi
            .spyOn(api, "identifyParticipant")
            .mockResolvedValue(null);
        renderRoute(`/p/${DEMO_PRESENTATION_PUBLIC_HASH}`);
        await waitFor(() =>
            expect(screen.getByText(/Quem é você/i)).toBeInTheDocument(),
        );
        fireEvent.change(screen.getByPlaceholderText("Ex.: Marina Reis"), {
            target: { value: "Gabriel" },
        });
        fireEvent.change(screen.getByPlaceholderText("(98) 97013-0666"), {
            target: { value: "98970130666" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Ver meu pagamento/i }));
        await waitFor(() => expect(spy).toHaveBeenCalled());
        expect(spy).toHaveBeenCalledWith(
            DEMO_PRESENTATION_PUBLIC_HASH,
            "Gabriel",
            "98970130666",
            null,
        );
    });

    it("demo hash shows continue-demo button when participant not found", async () => {
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(
            baseExpense({ publicHash: DEMO_PRESENTATION_PUBLIC_HASH }),
        );
        vi.spyOn(api, "identifyParticipant").mockResolvedValue(null);
        renderRoute(`/p/${DEMO_PRESENTATION_PUBLIC_HASH}`);
        await waitFor(() =>
            expect(screen.getByText(/Quem é você/i)).toBeInTheDocument(),
        );
        fireEvent.change(screen.getByPlaceholderText("Ex.: Marina Reis"), {
            target: { value: "Gabriel" },
        });
        fireEvent.change(screen.getByPlaceholderText("(98) 97013-0666"), {
            target: { value: "98970130666" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Ver meu pagamento/i }));
        await waitFor(() =>
            expect(
                screen.getByRole("button", { name: /Continuar no modo demonstração/i }),
            ).toBeInTheDocument(),
        );
    });

    it("non-mock public hash does not show continue-demo button", async () => {
        expect(isPublicExpenseUsingMock("real-coordinator-abc")).toBe(false);
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(
            baseExpense({ publicHash: "real-coordinator-abc" }),
        );
        vi.spyOn(api, "identifyParticipant").mockResolvedValue(null);
        renderRoute("/p/real-coordinator-abc");
        await waitFor(() =>
            expect(screen.getByText(/Quem é você/i)).toBeInTheDocument(),
        );
        fireEvent.change(screen.getByPlaceholderText("Ex.: Marina Reis"), {
            target: { value: "Gabriel" },
        });
        fireEvent.change(screen.getByPlaceholderText("(98) 97013-0666"), {
            target: { value: "98970130666" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Ver meu pagamento/i }));
        await waitFor(() =>
            expect(screen.getByText(IDENTIFY_SNIPPET)).toBeInTheDocument(),
        );
        expect(
            screen.queryByRole("button", { name: /Continuar no modo demonstração/i }),
        ).toBeNull();
    });

    it("when expense is closed, hides identification form and shows finalized copy", async () => {
        vi.spyOn(api, "getPublicExpense").mockResolvedValue(
            baseExpense({
                status: "closed",
                participantsTotalCount: 2,
                validatedChargesCount: 2,
                openChargesCount: 0,
            }),
        );
        renderRoute("/p/other-hash");
        await waitFor(() =>
            expect(screen.queryByText(/Quem é você/i)).not.toBeInTheDocument(),
        );
        const titles = screen.getAllByText(/^Cobrança finalizada$/i);
        expect(titles.length).toBeGreaterThan(0);
        expect(
            screen.getByText(/Não é mais necessário enviar comprovante/i),
        ).toBeInTheDocument();
    });
});
