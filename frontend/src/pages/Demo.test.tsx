import { describe, it, expect } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import Demo from "./Demo";

describe("Demo page", () => {
    it("exibe aviso de demonstração, prévia dos participantes e fluxo público mockado", async () => {
        render(
            <MemoryRouter initialEntries={["/demo"]}>
                <Routes>
                    <Route path="/demo" element={<Demo />} />
                </Routes>
            </MemoryRouter>,
        );

        expect(
            screen.getByText(/Esta é uma demonstração do sistema/i),
        ).toBeInTheDocument();
        expect(
            screen.getByRole("link", { name: /^criar conta$/i }),
        ).toHaveAttribute("href", "/cadastro");
        expect(screen.getByRole("link", { name: /^entrar$/i })).toHaveAttribute(
            "href",
            "/login",
        );

        expect(screen.getByText(/João Pedro/i)).toBeInTheDocument();

        await waitFor(
            () => {
                expect(
                    screen.getByRole("heading", { name: /Quem é você/i }),
                ).toBeInTheDocument();
            },
            { timeout: 4000 },
        );
    });
});
