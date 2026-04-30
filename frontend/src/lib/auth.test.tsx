import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from "@testing-library/react";
import {
    MemoryRouter,
    Route,
    Routes,
    useLocation,
} from "react-router-dom";
import AuthPage from "@/pages/Auth";
import Landing from "@/pages/Landing";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { AuthProvider, useAuth } from "@/lib/auth";
import { api } from "@/lib/api/client";
import { mockApi } from "@/lib/api/mockStore";
import type { User } from "@/lib/types";

const AUTH_STORAGE_KEY = "contacerta:auth:v1";
const DEMO_STORAGE_KEY = "contacerta:demo:v1";

function TestSessionProbe() {
    const { user, loading, isDemo, loginDemo, logout } = useAuth();

    if (loading) {
        return <div>auth-loading</div>;
    }

    return (
        <div>
            <div>{user ? `user:${user.email}` : "guest"}</div>
            <div>{isDemo ? "demo-on" : "demo-off"}</div>
            <button type="button" onClick={() => void loginDemo()}>
                Entrar demo
            </button>
            <button type="button" onClick={() => void logout()}>
                Sair
            </button>
        </div>
    );
}

function TestLocationSearch() {
    const location = useLocation();
    return <div>{location.search}</div>;
}

function renderAuthApp(initialEntry: string) {
    return render(
        <MemoryRouter initialEntries={[initialEntry]}>
            <AuthProvider>
                <Routes>
                    <Route path="/" element={<Landing />} />
                    <Route path="/login" element={<AuthPage mode="login" />} />
                    <Route
                        path="/cadastro"
                        element={<AuthPage mode="register" />}
                    />
                    <Route path="/dashboard" element={<div>Dashboard</div>} />
                    <Route
                        path="/cobrancas/nova"
                        element={
                            <ProtectedRoute>
                                <div>Nova cobrança</div>
                            </ProtectedRoute>
                        }
                    />
                    <Route path="/status" element={<TestSessionProbe />} />
                </Routes>
            </AuthProvider>
        </MemoryRouter>,
    );
}

function realUser(): User {
    return {
        id: "u-1",
        name: "Ana",
        email: "ana@contacerta.dev",
    };
}

function landingNav() {
    return within(screen.getByRole("navigation"));
}

function landingHero() {
    return within(screen.getByRole("banner"));
}

describe("auth flow", () => {
    beforeEach(() => {
        localStorage.clear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("login salva sessão e redireciona para redirect interno", async () => {
        vi.spyOn(api, "login").mockResolvedValue({
            token: "real-token",
            user: realUser(),
        });

        renderAuthApp("/login?redirect=/cobrancas/nova");

        fireEvent.change(await screen.findByLabelText(/e-mail/i), {
            target: { value: "ana@contacerta.dev" },
        });
        fireEvent.change(screen.getByLabelText(/^senha$/i), {
            target: { value: "secret123" },
        });
        fireEvent.click(screen.getByRole("button", { name: /^entrar$/i }));

        await waitFor(() => {
            expect(screen.getByText("Nova cobrança")).toBeInTheDocument();
        });
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBe("real-token");
    });

    it("cadastro salva sessão e redireciona automaticamente", async () => {
        vi.spyOn(api, "register").mockResolvedValue({
            token: "fresh-token",
            user: realUser(),
        });

        renderAuthApp("/cadastro?redirect=/dashboard");

        fireEvent.change(await screen.findByLabelText(/nome/i), {
            target: { value: "Ana" },
        });
        fireEvent.change(screen.getByLabelText(/e-mail/i), {
            target: { value: "ana@contacerta.dev" },
        });
        fireEvent.change(screen.getByLabelText(/^senha$/i), {
            target: { value: "secret123" },
        });
        fireEvent.change(screen.getByLabelText(/confirmar senha/i), {
            target: { value: "secret123" },
        });
        fireEvent.click(
            screen.getByRole("button", { name: /criar conta/i }),
        );

        await waitFor(() => {
            expect(screen.getByText("Dashboard")).toBeInTheDocument();
        });
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBe("fresh-token");
    });

    it("refresh com token válido restaura usuário", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        vi.spyOn(api, "me").mockResolvedValue(realUser());

        renderAuthApp("/status");

        await waitFor(() => {
            expect(
                screen.getByText(`user:${realUser().email}`),
            ).toBeInTheDocument();
        });
        expect(api.me).toHaveBeenCalledTimes(1);
    });

    it("refresh sem token não autentica", async () => {
        const meSpy = vi.spyOn(api, "me").mockResolvedValue(realUser());

        renderAuthApp("/status");

        await waitFor(() => {
            expect(screen.getByText("guest")).toBeInTheDocument();
        });
        expect(meSpy).not.toHaveBeenCalled();
    });

    it("visitante deslogado mostra CTAs corretos no header e no hero", async () => {
        renderAuthApp("/");

        await waitFor(() => {
            expect(
                landingNav().getByRole("link", { name: /^entrar$/i }),
            ).toBeInTheDocument();
        });
        expect(
            landingNav().getByRole("link", { name: /criar conta/i }),
        ).toBeInTheDocument();
        expect(
            landingNav().queryByRole("link", { name: /ver demonstração/i }),
        ).not.toBeInTheDocument();
        expect(
            landingNav().queryByRole("button", { name: /^sair$/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /criar conta grátis/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /^entrar$/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /ver demonstração/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().queryByRole("button", { name: /^sair$/i }),
        ).not.toBeInTheDocument();
    });

    it("usuário logado mostra CTAs corretos no header e no hero", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        vi.spyOn(api, "me").mockResolvedValue(realUser());

        renderAuthApp("/");

        await waitFor(() => {
            expect(
                landingNav().getByRole("link", { name: /minhas cobranças/i }),
            ).toBeInTheDocument();
        });
        expect(
            landingNav().getByRole("button", { name: /^sair$/i }),
        ).toBeInTheDocument();
        expect(
            landingNav().queryByRole("link", { name: /^entrar$/i }),
        ).not.toBeInTheDocument();
        expect(
            landingNav().queryByRole("link", { name: /criar conta/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /ir para minhas cobranças/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /criar nova cobrança/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().queryByRole("button", { name: /^sair$/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("link", { name: /ver demonstração/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("link", { name: /^entrar$/i }),
        ).not.toBeInTheDocument();
    });

    it("landing não mostra CTAs incorretos durante loading", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");

        vi.spyOn(api, "me").mockImplementation(
            () => new Promise(() => undefined),
        );

        renderAuthApp("/");

        expect(
            landingNav().queryByRole("link", { name: /^entrar$/i }),
        ).not.toBeInTheDocument();
        expect(
            landingNav().queryByRole("link", { name: /criar conta/i }),
        ).not.toBeInTheDocument();
        expect(
            landingNav().queryByRole("link", { name: /minhas cobranças/i }),
        ).not.toBeInTheDocument();
        expect(
            landingNav().queryByRole("button", { name: /^sair$/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("link", { name: /criar conta grátis/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("link", { name: /^entrar$/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("link", { name: /ver demonstração/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("link", { name: /ir para minhas cobranças/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("link", { name: /criar nova cobrança/i }),
        ).not.toBeInTheDocument();
        expect(
            landingHero().queryByRole("button", { name: /^sair$/i }),
        ).not.toBeInTheDocument();
    });

    it("login redireciona quando já existe sessão", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        vi.spyOn(api, "me").mockResolvedValue(realUser());

        renderAuthApp("/login?redirect=/dashboard");

        await waitFor(() => {
            expect(screen.getByText("Dashboard")).toBeInTheDocument();
        });
        expect(
            screen.queryByRole("button", { name: /^entrar$/i }),
        ).not.toBeInTheDocument();
    });

    it("cadastro redireciona quando já existe sessão", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        vi.spyOn(api, "me").mockResolvedValue(realUser());

        renderAuthApp("/cadastro?redirect=/dashboard");

        await waitFor(() => {
            expect(screen.getByText("Dashboard")).toBeInTheDocument();
        });
        expect(
            screen.queryByRole("button", { name: /criar conta/i }),
        ).not.toBeInTheDocument();
    });

    it("ProtectedRoute aguarda loading antes de decidir", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");

        let resolveMe: ((user: User | null) => void) | undefined;
        vi.spyOn(api, "me").mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveMe = resolve;
                }),
        );

        render(
            <MemoryRouter initialEntries={["/cobrancas/nova"]}>
                <AuthProvider>
                    <Routes>
                        <Route
                            path="/login"
                            element={<div>Tela de login</div>}
                        />
                        <Route
                            path="/cobrancas/nova"
                            element={
                                <ProtectedRoute>
                                    <div>Nova cobrança</div>
                                </ProtectedRoute>
                            }
                        />
                    </Routes>
                </AuthProvider>
            </MemoryRouter>,
        );

        expect(screen.getByText(/carregando/i)).toBeInTheDocument();
        expect(screen.queryByText("Tela de login")).not.toBeInTheDocument();

        resolveMe?.(realUser());

        await waitFor(() => {
            expect(screen.getByText("Nova cobrança")).toBeInTheDocument();
        });
    });

    it("ProtectedRoute redireciona preservando pathname e search", async () => {
        render(
            <MemoryRouter initialEntries={["/cobrancas/nova?origem=landing"]}>
                <AuthProvider>
                    <Routes>
                        <Route path="/login" element={<TestLocationSearch />} />
                        <Route
                            path="/cobrancas/nova"
                            element={
                                <ProtectedRoute>
                                    <div>Nova cobrança</div>
                                </ProtectedRoute>
                            }
                        />
                    </Routes>
                </AuthProvider>
            </MemoryRouter>,
        );

        await waitFor(() => {
            expect(
                screen.getByText(
                    "?redirect=%2Fcobrancas%2Fnova%3Forigem%3Dlanding",
                ),
            ).toBeInTheDocument();
        });
    });

    it("logout limpa sessão local", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        vi.spyOn(api, "me").mockResolvedValue(realUser());
        vi.spyOn(api, "logout").mockImplementation(async () => {
            localStorage.removeItem(AUTH_STORAGE_KEY);
            return true;
        });

        renderAuthApp("/status");

        await waitFor(() => {
            expect(
                screen.getByText(`user:${realUser().email}`),
            ).toBeInTheDocument();
        });

        fireEvent.click(screen.getByRole("button", { name: /^sair$/i }));

        await waitFor(() => {
            expect(screen.getByText("guest")).toBeInTheDocument();
        });
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBeNull();
    });

    it("bloqueia redirect externo e usa dashboard como fallback", async () => {
        vi.spyOn(api, "login").mockResolvedValue({
            token: "real-token",
            user: realUser(),
        });

        renderAuthApp("/login?redirect=https://evil.example");

        fireEvent.change(await screen.findByLabelText(/e-mail/i), {
            target: { value: "ana@contacerta.dev" },
        });
        fireEvent.change(screen.getByLabelText(/^senha$/i), {
            target: { value: "secret123" },
        });
        fireEvent.click(screen.getByRole("button", { name: /^entrar$/i }));

        await waitFor(() => {
            expect(screen.getByText("Dashboard")).toBeInTheDocument();
        });
    });

    it("entrar em demo salva flag demo e limpa sessão real existente", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        vi.spyOn(api, "me").mockResolvedValue(realUser());
        vi.spyOn(mockApi, "enterDemo").mockResolvedValue({
            id: "demo",
            name: "Visitante",
            email: "demo@contacerta.local",
        });

        renderAuthApp("/status");

        await waitFor(() => {
            expect(
                screen.getByText(`user:${realUser().email}`),
            ).toBeInTheDocument();
        });

        fireEvent.click(
            screen.getByRole("button", { name: /entrar demo/i }),
        );

        await waitFor(() => {
            expect(
                screen.getByText("user:demo@contacerta.local"),
            ).toBeInTheDocument();
        });
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBeNull();
        expect(localStorage.getItem(DEMO_STORAGE_KEY)).toBe("1");
    });

    it("sair da demo limpa token e flag demo", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        localStorage.setItem(DEMO_STORAGE_KEY, "1");
        vi.spyOn(api, "me").mockResolvedValue({
            id: "demo",
            name: "Visitante",
            email: "demo@contacerta.local",
        });
        vi.spyOn(mockApi, "logout").mockResolvedValue(true);

        renderAuthApp("/status");

        await waitFor(() => {
            expect(
                screen.getByText("user:demo@contacerta.local"),
            ).toBeInTheDocument();
        });

        fireEvent.click(screen.getByRole("button", { name: /^sair$/i }));

        await waitFor(() => {
            expect(screen.getByText("guest")).toBeInTheDocument();
        });
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBeNull();
        expect(localStorage.getItem(DEMO_STORAGE_KEY)).toBeNull();
    });

    it("login real remove flag demo antes de salvar a sessão", async () => {
        localStorage.setItem(DEMO_STORAGE_KEY, "1");
        vi.spyOn(api, "me").mockResolvedValue(null);
        vi.spyOn(mockApi, "logout").mockResolvedValue(true);
        vi.spyOn(api, "login").mockResolvedValue({
            token: "real-token",
            user: realUser(),
        });

        renderAuthApp("/login?redirect=/dashboard");

        fireEvent.change(await screen.findByLabelText(/e-mail/i), {
            target: { value: "ana@contacerta.dev" },
        });
        fireEvent.change(screen.getByLabelText(/^senha$/i), {
            target: { value: "secret123" },
        });
        fireEvent.click(screen.getByRole("button", { name: /^entrar$/i }));

        await waitFor(() => {
            expect(screen.getByText("Dashboard")).toBeInTheDocument();
        });
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBe("real-token");
        expect(localStorage.getItem(DEMO_STORAGE_KEY)).toBeNull();
    });

    it("usuário demo ao acessar a landing encerra a sessão e volta aos CTAs de visitante", async () => {
        localStorage.setItem(AUTH_STORAGE_KEY, "persisted-token");
        localStorage.setItem(DEMO_STORAGE_KEY, "1");
        vi.spyOn(api, "me").mockResolvedValue({
            id: "demo",
            name: "Visitante",
            email: "demo@contacerta.local",
        });
        const logoutSpy = vi
            .spyOn(mockApi, "logout")
            .mockResolvedValue(true);

        renderAuthApp("/");

        await waitFor(() => {
            expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBeNull();
            expect(localStorage.getItem(DEMO_STORAGE_KEY)).toBeNull();
        });
        expect(logoutSpy).toHaveBeenCalledTimes(1);
        expect(
            landingNav().getByRole("link", { name: /^entrar$/i }),
        ).toBeInTheDocument();
        expect(
            landingNav().getByRole("link", { name: /criar conta/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /criar conta grátis/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /^entrar$/i }),
        ).toBeInTheDocument();
        expect(
            landingHero().getByRole("link", { name: /ver demonstração/i }),
        ).toBeInTheDocument();
    });
});
