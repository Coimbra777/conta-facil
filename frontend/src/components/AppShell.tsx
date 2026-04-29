import { Link, NavLink, useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { LogOut, Plus } from "lucide-react";

export function AppShell({ children }: { children: React.ReactNode }) {
    const { user, logout, isDemo } = useAuth();
    const nav = useNavigate();

    return (
        <div className="min-h-dvh bg-background flex flex-col">
            <header className="sticky top-0 z-30 bg-background/90 backdrop-blur border-b-4 border-foreground">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
                    <Link
                        to="/"
                        className="font-display text-xl sm:text-2xl uppercase tracking-tight flex items-center gap-2"
                    >
                        <span className="size-6 rounded-full bg-accent border-2 border-foreground" />
                        ContaCerta
                        {isDemo && (
                            <span className="text-[10px] sm:text-xs font-black uppercase px-2 py-0.5 rounded-md bg-arcade-yellow border-2 border-foreground whitespace-nowrap">
                                Demo
                            </span>
                        )}
                    </Link>

                    <nav className="hidden sm:flex items-center gap-1">
                        <NavLink
                            to="/dashboard"
                            className={({ isActive }) =>
                                `px-3 py-1.5 rounded-lg font-bold text-sm ${isActive ? "bg-arcade-yellow border-2 border-foreground" : "hover:bg-muted"}`
                            }
                        >
                            Minhas cobranças
                        </NavLink>
                    </nav>

                    <div className="flex items-center gap-2">
                        <Link
                            to="/cobrancas/nova"
                            className="hidden sm:inline-flex items-center gap-1.5 bg-accent text-accent-foreground border-4 border-foreground px-3 py-1.5 rounded-lg font-bold text-sm brutal-press brutal-press-sm"
                        >
                            <Plus className="size-4" /> Nova
                        </Link>
                        {user && (
                            <button
                                onClick={async () => {
                                    await logout();
                                    nav("/");
                                }}
                                title="Sair"
                                className="border-4 border-foreground bg-card p-2 rounded-lg brutal-press brutal-press-sm"
                                aria-label="Sair"
                            >
                                <LogOut className="size-4" />
                            </button>
                        )}
                    </div>
                </div>
            </header>

            {isDemo && (
                <div
                    role="status"
                    className="border-b-4 border-foreground bg-arcade-yellow px-4 py-3 text-center font-bold text-sm"
                >
                    Modo demonstração — os dados exibidos não são reais e não são gravados no servidor.
                </div>
            )}

            <main className="flex-1">{children}</main>

            <footer className="border-t-4 border-foreground bg-foreground text-background mt-12">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 py-6 text-sm flex flex-wrap items-center justify-between gap-3">
                    <span className="font-bold">ContaCerta Pix</span>
                    <span className="opacity-70">
                        Cobranças compartilhadas, sem climão.
                    </span>
                </div>
            </footer>
        </div>
    );
}
