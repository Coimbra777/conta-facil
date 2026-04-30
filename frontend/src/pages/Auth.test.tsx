import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import AuthPage from "./Auth";
import { AuthProvider } from "@/lib/auth";
import * as client from "@/lib/api/client";

function renderLogin(initial = "/login") {
    return render(
        <MemoryRouter initialEntries={[initial]}>
            <AuthProvider>
                <Routes>
                    <Route path="/login" element={<AuthPage mode="login" />} />
                    <Route path="/cadastro" element={<AuthPage mode="register" />} />
                </Routes>
            </AuthProvider>
        </MemoryRouter>,
    );
}

describe("AuthPage login UX", () => {
    beforeEach(() => {
        vi.spyOn(client.api, "me").mockResolvedValue(null);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("exibe CTA de cadastro quando ACCOUNT_NOT_FOUND", async () => {
        vi.spyOn(client.api, "login").mockRejectedValue(
            new client.ApiClientError(
                "Não encontramos uma conta com este e-mail.",
                { code: "ACCOUNT_NOT_FOUND", status: 422 },
            ),
        );
        renderLogin();
        fireEvent.change(await screen.findByLabelText(/e-mail/i), {
            target: { value: "ghost@test.com" },
        });
        fireEvent.change(screen.getByLabelText(/^senha$/i), {
            target: { value: "secret12" },
        });
        fireEvent.click(screen.getByRole("button", { name: /^entrar$/i }));

        await waitFor(() => {
            expect(
                screen.getByRole("button", { name: /criar conta agora/i }),
            ).toBeInTheDocument();
        });
        expect(
            screen.queryByText(/E-mail ou senha inválidos/i),
        ).not.toBeInTheDocument();
    });

    it("ao clicar no CTA, vai para cadastro com e-mail preenchido", async () => {
        vi.spyOn(client.api, "login").mockRejectedValue(
            new client.ApiClientError(
                "Não encontramos uma conta com este e-mail.",
                { code: "ACCOUNT_NOT_FOUND", status: 422 },
            ),
        );
        renderLogin();
        fireEvent.change(await screen.findByLabelText(/e-mail/i), {
            target: { value: "novo@test.com" },
        });
        fireEvent.change(screen.getByLabelText(/^senha$/i), {
            target: { value: "secret12" },
        });
        fireEvent.click(screen.getByRole("button", { name: /^entrar$/i }));

        await waitFor(() =>
            screen.getByRole("button", { name: /criar conta agora/i }),
        );
        fireEvent.click(screen.getByRole("button", { name: /criar conta agora/i }));

        await waitFor(() => {
            expect(screen.getByLabelText(/nome/i)).toBeInTheDocument();
        });
        expect(
            (screen.getByLabelText(/e-mail/i) as HTMLInputElement).value,
        ).toBe("novo@test.com");
    });

    it("senha errada mostra mensagem genérica sem CTA de cadastro", async () => {
        vi.spyOn(client.api, "login").mockRejectedValue(
            new client.ApiClientError("E-mail ou senha inválidos.", {
                code: "INVALID_CREDENTIALS",
                status: 401,
            }),
        );
        renderLogin();
        fireEvent.change(await screen.findByLabelText(/e-mail/i), {
            target: { value: "john@test.com" },
        });
        fireEvent.change(screen.getByLabelText(/^senha$/i), {
            target: { value: "wrong-pass" },
        });
        fireEvent.click(screen.getByRole("button", { name: /^entrar$/i }));

        await waitFor(() => {
            expect(screen.getByText(/E-mail ou senha inválidos/i)).toBeInTheDocument();
        });
        expect(
            screen.queryByRole("button", { name: /criar conta agora/i }),
        ).not.toBeInTheDocument();
    });
});
