import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import Dashboard from "./Dashboard";
import { AuthProvider } from "@/lib/auth";
import { api } from "@/lib/api/client";
import type { Expense } from "@/lib/types";
import { CLOSED_EXPENSE_SHARE_DISABLED_HINT } from "@/lib/closedExpenseCopy";

function renderDashboard() {
    return render(
        <MemoryRouter initialEntries={["/dashboard"]}>
            <AuthProvider>
                <Routes>
                    <Route path="/dashboard" element={<Dashboard />} />
                </Routes>
            </AuthProvider>
        </MemoryRouter>,
    );
}

function expenseRow(overrides: Partial<Expense> = {}): Expense {
    return {
        id: "e1",
        publicHash: "phash",
        title: "Churrasco",
        totalAmount: 100,
        pixKeyType: "email",
        pixKey: "a@b.co",
        pixReceiverName: "",
        organizerName: "Org",
        participants: [
            {
                id: "c1",
                name: "A",
                phone: "11999999999",
                amount: 100,
                status: "validated",
            },
        ],
        createdAt: new Date().toISOString(),
        ...overrides,
    };
}

describe("Dashboard", () => {
    beforeEach(() => {
        vi.spyOn(api, "me").mockResolvedValue({
            id: "u1",
            name: "Tester",
            email: "t@test.com",
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("hides share actions when expense is closed", async () => {
        vi.spyOn(api, "listExpenses").mockResolvedValue({
            expenses: [expenseRow({ status: "closed" })],
            pagination: {
                currentPage: 1,
                lastPage: 1,
                perPage: 15,
                total: 1,
            },
        });
        renderDashboard();
        await waitFor(() =>
            expect(
                screen.getByRole("heading", { name: /Minhas cobranças/i }),
            ).toBeInTheDocument(),
        );
        expect(screen.queryByRole("button", { name: /Copiar link/i })).toBeNull();
        expect(screen.queryByText(/WhatsApp/i)).toBeNull();
        expect(
            screen.getByText(CLOSED_EXPENSE_SHARE_DISABLED_HINT),
        ).toBeInTheDocument();
    });
});
