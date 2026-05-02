import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import NewExpense from "./NewExpense";
import { AuthProvider } from "@/lib/auth";
import { ApiClientError, api } from "@/lib/api/client";
import { GENERIC_BRAZIL_PHONE_PLACEHOLDER } from "@/lib/inputMasks";

function renderPage() {
    return render(
        <MemoryRouter initialEntries={["/cobrancas/nova"]}>
            <AuthProvider>
                <Routes>
                    <Route path="/cobrancas/nova" element={<NewExpense />} />
                    <Route
                        path="/cobrancas/:id/sucesso"
                        element={<div>Tela de sucesso</div>}
                    />
                </Routes>
            </AuthProvider>
        </MemoryRouter>,
    );
}

function formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

function futureDate(): string {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    return formatDate(date);
}

function pastDate(): string {
    const date = new Date();
    date.setDate(date.getDate() - 1);
    return formatDate(date);
}

async function fillStep1(overrides?: {
    title?: string;
    totalAmount?: string;
    dueDate?: string;
}) {
    expect(await screen.findByText(/Nova cobrança/i)).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText(/Título/i), {
        target: { value: overrides?.title ?? "Churrasco" },
    });
    fireEvent.change(screen.getByLabelText(/Valor total/i), {
        target: { value: overrides?.totalAmount ?? "100,00" },
    });
    fireEvent.change(screen.getByLabelText(/Data de vencimento/i), {
        target: { value: overrides?.dueDate ?? futureDate() },
    });
}

async function goToParticipantsStep(options?: {
    title?: string;
    totalAmount?: string;
    dueDate?: string;
    pixKey?: string;
}) {
    await fillStep1(options);
    fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));
    fireEvent.change(screen.getByPlaceholderText(/Sua chave Pix/i), {
        target: { value: options?.pixKey ?? "pix@teste.com" },
    });
    fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));
}

async function fillParticipants(values: {
    names?: string[];
    phones?: string[];
    amounts?: string[];
}) {
    const names = values.names ?? [];
    const phones = values.phones ?? [];
    const amounts = values.amounts ?? [];

    names.forEach((name, index) => {
        fireEvent.change(screen.getByLabelText(new RegExp(`Nome ${index + 1}`, "i")), {
            target: { value: name },
        });
    });

    if (phones.length > 0) {
        const phoneInputs = await screen.findAllByPlaceholderText(
            GENERIC_BRAZIL_PHONE_PLACEHOLDER,
        );
        phones.forEach((phone, index) => {
            fireEvent.change(phoneInputs[index] as HTMLInputElement, {
                target: { value: phone },
            });
        });
    }

    if (amounts.length > 0) {
        const amountInputs = screen.getAllByLabelText(/Valor \(R\$\)/i);
        amounts.forEach((amount, index) => {
            fireEvent.change(amountInputs[index] as HTMLInputElement, {
                target: { value: amount },
            });
        });
    }
}

describe("NewExpense validation and errors", () => {
    beforeEach(() => {
        vi.spyOn(api, "me").mockResolvedValue(null);
        vi.spyOn(window, "alert").mockImplementation(() => {});
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
        await goToParticipantsStep();
        await fillParticipants({
            amounts: ["70,00", "50,00"],
        });

        await waitFor(() =>
            expect(
                screen.getByText(/Há R\$ 20,00 excedente\. Esse valor ficará em caixa\./i),
            ).toBeInTheDocument(),
        );
    });

    it("shows balanced copy when difference is zero", async () => {
        renderPage();
        await goToParticipantsStep();
        await fillParticipants({
            amounts: ["50,00", "50,00"],
        });

        await waitFor(() =>
            expect(
                screen.getByText(/Os valores estão balanceados\./i),
            ).toBeInTheDocument(),
        );
    });

    it("uses the generic placeholder in participant phone fields", async () => {
        renderPage();
        await goToParticipantsStep();

        const phoneInputs = await screen.findAllByPlaceholderText(
            GENERIC_BRAZIL_PHONE_PLACEHOLDER,
        );
        expect(phoneInputs.length).toBeGreaterThan(0);
    });

    it("does not call the API when due_date is in the past", async () => {
        const createExpenseSpy = vi.spyOn(api, "createExpense");
        renderPage();

        await fillStep1({ dueDate: pastDate() });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        expect(
            screen.getAllByText(
                /Informe uma data de vencimento igual ou posterior a hoje\./i,
            ).length,
        ).toBeGreaterThan(0);
        expect(createExpenseSpy).not.toHaveBeenCalled();
    });

    it("shows the due_date error below the field in PT-BR", async () => {
        renderPage();

        await fillStep1({ dueDate: pastDate() });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        expect(
            screen.getAllByText(
                /Informe uma data de vencimento igual ou posterior a hoje\./i,
            ).length,
        ).toBeGreaterThan(0);
    });

    it("does not use alert for local validation errors", async () => {
        renderPage();

        await fillStep1({ dueDate: pastDate() });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        expect(window.alert).not.toHaveBeenCalled();
    });

    it("shows an error for an empty description before leaving step 1", async () => {
        renderPage();

        await fillStep1({ title: "" });
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        expect(screen.getAllByText(/Informe a descrição da cobrança\./i).length).toBeGreaterThan(0);
    });

    it("shows an error for an empty pix key before leaving step 2", async () => {
        renderPage();

        await fillStep1();
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));
        fireEvent.click(screen.getByRole("button", { name: /Continuar/i }));

        expect(screen.getAllByText(/Informe a chave Pix\./i).length).toBeGreaterThan(0);
    });

    it("shows an error for a participant without name", async () => {
        const createExpenseSpy = vi.spyOn(api, "createExpense");
        renderPage();

        await goToParticipantsStep();
        await fillParticipants({
            phones: ["11999999999", "11988888888"],
            amounts: ["50,00", "50,00"],
        });

        fireEvent.click(
            screen.getByRole("button", { name: /Criar cobrança e gerar link/i }),
        );

        expect(
            screen.getAllByText(/Informe o nome do participante\./i)[0],
        ).toBeInTheDocument();
        expect(createExpenseSpy).not.toHaveBeenCalled();
    });

    it("shows an error for a participant without phone", async () => {
        const createExpenseSpy = vi.spyOn(api, "createExpense");
        renderPage();

        await goToParticipantsStep();
        await fillParticipants({
            names: ["Ana", "Beto"],
            amounts: ["50,00", "50,00"],
        });

        fireEvent.click(
            screen.getByRole("button", { name: /Criar cobrança e gerar link/i }),
        );

        expect(
            screen.getAllByText(/Informe o telefone do participante\./i)[0],
        ).toBeInTheDocument();
        expect(createExpenseSpy).not.toHaveBeenCalled();
    });

    it("shows an error for an invalid phone", async () => {
        const createExpenseSpy = vi.spyOn(api, "createExpense");
        renderPage();

        await goToParticipantsStep();
        await fillParticipants({
            names: ["Ana", "Beto"],
            phones: ["1193334444", "11988888888"],
            amounts: ["50,00", "50,00"],
        });

        fireEvent.click(
            screen.getByRole("button", { name: /Criar cobrança e gerar link/i }),
        );

        expect(screen.getAllByText(/Telefone inválido\. Use um número com DDD\./i).length).toBeGreaterThan(0);
        expect(createExpenseSpy).not.toHaveBeenCalled();
    });

    it("shows an error when participant sum is below the total", async () => {
        const createExpenseSpy = vi.spyOn(api, "createExpense");
        renderPage();

        await goToParticipantsStep();
        await fillParticipants({
            names: ["Ana", "Beto"],
            phones: ["11999999999", "11988888888"],
            amounts: ["40,00", "40,00"],
        });

        fireEvent.click(
            screen.getByRole("button", { name: /Criar cobrança e gerar link/i }),
        );

        expect(
            screen.getAllByText(/Faltam R\$ 20,00 para fechar o total da cobrança\./i)
                .length,
        ).toBeGreaterThan(0);
        expect(createExpenseSpy).not.toHaveBeenCalled();
    });

    it("allows creating an expense when participant sum exceeds the total", async () => {
        vi.spyOn(api, "createExpense").mockResolvedValue({
            id: "exp-1",
            publicHash: "hash-1",
            title: "Churrasco",
            totalAmount: 100,
            pixKeyType: "email",
            pixKey: "pix@teste.com",
            pixReceiverName: "ContaCerta",
            organizerName: "ContaCerta",
            createdAt: new Date().toISOString(),
            participants: [],
        } as never);

        renderPage();
        await goToParticipantsStep();
        await fillParticipants({
            names: ["Ana", "Beto"],
            phones: ["11999999999", "11988888888"],
            amounts: ["70,00", "50,00"],
        });

        fireEvent.click(
            screen.getByRole("button", { name: /Criar cobrança e gerar link/i }),
        );

        await waitFor(() => expect(api.createExpense).toHaveBeenCalledTimes(1));
        expect(screen.getByText(/Tela de sucesso/i)).toBeInTheDocument();
        expect(window.alert).not.toHaveBeenCalled();
    });

    it("maps backend errors.due_date to the correct field", async () => {
        vi.spyOn(api, "createExpense").mockRejectedValue(
            new ApiClientError(
                "Informe uma data de vencimento igual ou posterior a hoje.",
                {
                    code: "VALIDATION_ERROR",
                    status: 422,
                    errors: {
                        due_date: [
                            "Informe uma data de vencimento igual ou posterior a hoje.",
                        ],
                    },
                },
            ),
        );

        renderPage();
        await goToParticipantsStep();
        await fillParticipants({
            names: ["Ana", "Beto"],
            phones: ["11999999999", "11988888888"],
            amounts: ["50,00", "50,00"],
        });

        fireEvent.click(
            screen.getByRole("button", { name: /Criar cobrança e gerar link/i }),
        );

        expect(
            (await screen.findAllByText(
                /Informe uma data de vencimento igual ou posterior a hoje\./i,
            )).length,
        ).toBeGreaterThan(0);
        expect(screen.getByLabelText(/Data de vencimento/i)).toBeInTheDocument();
    });

    it("maps backend errors.participants.0.phone to the correct participant", async () => {
        vi.spyOn(api, "createExpense").mockRejectedValue(
            new ApiClientError("Telefone inválido. Use um número com DDD.", {
                code: "VALIDATION_ERROR",
                status: 422,
                errors: {
                    "participants.0.phone": [
                        "Telefone inválido. Use um número com DDD.",
                    ],
                },
            }),
        );

        renderPage();
        await goToParticipantsStep();
        await fillParticipants({
            names: ["Ana", "Beto"],
            phones: ["11999999999", "11988888888"],
            amounts: ["50,00", "50,00"],
        });

        fireEvent.click(
            screen.getByRole("button", { name: /Criar cobrança e gerar link/i }),
        );

        const phoneInputs = await screen.findAllByPlaceholderText(
            GENERIC_BRAZIL_PHONE_PLACEHOLDER,
        );
        expect(phoneInputs[0]).toHaveAttribute("aria-invalid", "true");
        expect(screen.getAllByText(/Telefone inválido\. Use um número com DDD\./i).length).toBeGreaterThan(0);
    });
});
