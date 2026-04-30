import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import NewExpense from "./NewExpense";
import { AuthProvider } from "@/lib/auth";
import { api } from "@/lib/api/client";
import { GENERIC_BRAZIL_PHONE_PLACEHOLDER } from "@/lib/inputMasks";

function renderPage() {
    return render(
        <MemoryRouter initialEntries={["/cobrancas/nova"]}>
            <AuthProvider>
                <Routes>
                    <Route path="/cobrancas/nova" element={<NewExpense />} />
                </Routes>
            </AuthProvider>
        </MemoryRouter>,
    );
}

describe("NewExpense summary", () => {
    beforeEach(() => {
        vi.spyOn(api, "me").mockResolvedValue(null);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("shows missing amount copy when difference is positive", async () => {
        renderPage();

        expect(await screen.findByText(/Nova cobrança/i)).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText(/Valor total/i), {
            target: { value: "100,00" },
        });

        await waitFor(() =>
            expect(
                screen.getByText(/Faltam R\$ 100,00 para fechar o total da cobrança\./i),
            ).toBeInTheDocument(),
        );
    });

    it("shows surplus copy when difference is negative", async () => {
        renderPage();

        expect(await screen.findByText(/Nova cobrança/i)).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText(/Título/i), {
            target: { value: "Churrasco" },
        });
        fireEvent.change(screen.getByLabelText(/Valor total/i), {
            target: { value: "100,00" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));
        fireEvent.change(screen.getByPlaceholderText(/Sua chave Pix/i), {
            target: { value: "pix@teste.com" },
        });
        fireEvent.change(screen.getByLabelText(/Nome do recebedor/i), {
            target: { value: "ContaCerta" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        const amounts = await screen.findAllByLabelText(/Valor \(R\$\)/i);
        fireEvent.change(amounts[0] as HTMLInputElement, {
            target: { value: "70,00" },
        });
        fireEvent.change(amounts[1] as HTMLInputElement, {
            target: { value: "50,00" },
        });

        await waitFor(() =>
            expect(
                screen.getByText(/Há R\$ 20,00 excedente\. Esse valor ficará em caixa\./i),
            ).toBeInTheDocument(),
        );
    });

    it("shows balanced copy when difference is zero", async () => {
        renderPage();

        expect(await screen.findByText(/Nova cobrança/i)).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText(/Título/i), {
            target: { value: "Churrasco" },
        });
        fireEvent.change(screen.getByLabelText(/Valor total/i), {
            target: { value: "100,00" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));
        fireEvent.change(screen.getByPlaceholderText(/Sua chave Pix/i), {
            target: { value: "pix@teste.com" },
        });
        fireEvent.change(screen.getByLabelText(/Nome do recebedor/i), {
            target: { value: "ContaCerta" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        const amountInputs = screen.getAllByLabelText(/Valor \(R\$\)/i);
        fireEvent.change(amountInputs[0] as HTMLInputElement, {
            target: { value: "50,00" },
        });
        fireEvent.change(amountInputs[1] as HTMLInputElement, {
            target: { value: "50,00" },
        });

        await waitFor(() =>
            expect(
                screen.getByText(/Os valores estão balanceados\./i),
            ).toBeInTheDocument(),
        );
    });

    it("uses the generic placeholder in participant phone fields", async () => {
        renderPage();

        expect(await screen.findByText(/Nova cobrança/i)).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText(/Título/i), {
            target: { value: "Churrasco" },
        });
        fireEvent.change(screen.getByLabelText(/Valor total/i), {
            target: { value: "100,00" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));
        fireEvent.change(screen.getByPlaceholderText(/Sua chave Pix/i), {
            target: { value: "pix@teste.com" },
        });
        fireEvent.change(screen.getByLabelText(/Nome do recebedor/i), {
            target: { value: "ContaCerta" },
        });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        const phoneInputs = await screen.findAllByPlaceholderText(
            GENERIC_BRAZIL_PHONE_PLACEHOLDER,
        );
        expect(phoneInputs.length).toBeGreaterThan(0);
    });
});
