import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import ExpenseDetail from "./ExpenseDetail";
import { AuthProvider } from "@/lib/auth";
import { api } from "@/lib/api/client";
import type { Expense } from "@/lib/types";

function renderRoute(path = "/cobrancas/e1") {
    return render(
        <MemoryRouter initialEntries={[path]}>
            <AuthProvider>
                <Routes>
                    <Route path="/cobrancas/:id" element={<ExpenseDetail />} />
                </Routes>
            </AuthProvider>
        </MemoryRouter>,
    );
}

function buildExpense(overrides: Partial<Expense> = {}): Expense {
    return {
        id: "e1",
        publicHash: "hash-1",
        title: "Churrasco",
        description: "Detalhe da cobrança",
        totalAmount: 100,
        dueDate: "2026-05-10",
        pixKeyType: "email",
        pixKey: "pix@example.com",
        pixReceiverName: "Org",
        organizerName: "Org",
        participants: [
            {
                id: "c1",
                name: "Alice",
                phone: "11999999999",
                amount: 100,
                status: "validated",
                hasProof: true,
                proofSentAt: new Date().toISOString(),
            },
        ],
        createdAt: new Date().toISOString(),
        ...overrides,
    };
}

describe("ExpenseDetail", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        localStorage.clear();
    });

    it("shows the proof deletion notice while the expense is open", async () => {
        vi.spyOn(api, "getExpense").mockResolvedValue(buildExpense());

        renderRoute();

        expect(
            await screen.findByText(
                /Ao finalizar a cobrança, os comprovantes enviados serão excluídos automaticamente\./i,
            ),
        ).toBeInTheDocument();
    });

    it("hides proof view actions when the expense is closed", async () => {
        vi.spyOn(api, "getExpense").mockResolvedValue(
            buildExpense({ status: "closed" }),
        );

        renderRoute();

        await waitFor(() =>
            expect(
                screen.getByRole("heading", { name: /Participantes \(1\)/i }),
            ).toBeInTheDocument(),
        );

        expect(
            screen.getByText(/Comprovantes excluídos após a finalização da cobrança\./i),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole("button", { name: /Ver comprovante/i }),
        ).toBeNull();
    });
});
