import { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useAuth } from "@/lib/auth";

interface Props { mode: "login" | "register" }

export default function AuthPage({ mode }: Props) {
    const { login, register, loginDemo } = useAuth();
    const nav = useNavigate();
    const [params] = useSearchParams();
    const redirect = params.get("redirect") ?? "/dashboard";

    const [name, setName] = useState("");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [confirm, setConfirm] = useState("");
    const [err, setErr] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const isRegister = mode === "register";

    const submit = async (e: React.FormEvent) => {
        e.preventDefault();
        setErr(null);
        if (isRegister && password !== confirm) {
            setErr("As senhas não conferem.");
            return;
        }
        setLoading(true);
        try {
            if (isRegister)
                await register(
                    name.trim(),
                    email.trim(),
                    password,
                    confirm,
                );
            else await login(email.trim(), password);
            nav(redirect);
        } catch (e: unknown) {
            setErr(
                e instanceof Error
                    ? e.message
                    : "Não foi possível continuar. Tente novamente.",
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-dvh bg-background flex flex-col">
            <header className="max-w-6xl mx-auto w-full px-5 sm:px-8 py-5">
                <Link to="/" className="font-display text-xl uppercase flex items-center gap-2">
                    <span className="size-6 rounded-full bg-accent border-2 border-foreground" /> ContaCerta
                </Link>
            </header>

            <main className="flex-1 grid place-items-center px-5 py-10">
                <div className="w-full max-w-md bg-card border-4 border-foreground rounded-3xl brutal-shadow-lg p-6 sm:p-8">
                    <h1 className="font-display text-3xl uppercase mb-1">{isRegister ? "Criar conta" : "Entrar"}</h1>
                    <p className="text-muted-foreground mb-6 text-sm">
                        Entre para gerenciar suas cobranças compartilhadas.
                    </p>

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        {isRegister && (
                            <Field label="Nome">
                                <input
                                    required
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    className="brutal-input"
                                    placeholder="Seu nome"
                                />
                            </Field>
                        )}
                        <Field label="E-mail">
                            <input
                                required
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="brutal-input"
                                placeholder="voce@email.com"
                            />
                        </Field>
                        <Field label="Senha">
                            <input
                                required
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="brutal-input"
                                placeholder="••••••••"
                                minLength={6}
                            />
                        </Field>
                        {isRegister && (
                            <Field label="Confirmar senha">
                                <input
                                    required
                                    type="password"
                                    value={confirm}
                                    onChange={(e) => setConfirm(e.target.value)}
                                    className="brutal-input"
                                    placeholder="••••••••"
                                    minLength={6}
                                />
                            </Field>
                        )}

                        {err && (
                            <div className="border-4 border-foreground bg-status-rejected text-status-rejected-fg px-3 py-2 rounded-lg text-sm font-bold">
                                {err}
                            </div>
                        )}

                        <button
                            disabled={loading}
                            type="submit"
                            className="bg-accent text-accent-foreground border-4 border-foreground py-3 rounded-xl font-black uppercase tracking-wider brutal-press brutal-press-md disabled:opacity-50"
                        >
                            {loading ? "..." : isRegister ? "Criar conta" : "Entrar"}
                        </button>
                    </form>

                    <div className="mt-6 pt-4 border-t-4 border-dashed border-muted-foreground/30">
                        <button
                            type="button"
                            disabled={loading}
                            title="Usa dados fictícios só neste navegador"
                            onClick={async () => {
                                setErr(null);
                                setLoading(true);
                                try {
                                    await loginDemo();
                                    nav(redirect);
                                } catch (e: unknown) {
                                    const msg =
                                        e instanceof Error
                                            ? e.message
                                            : "Não foi possível iniciar o modo demo.";
                                    setErr(msg);
                                } finally {
                                    setLoading(false);
                                }
                            }}
                            className="w-full border-4 border-foreground bg-muted/80 py-3 rounded-xl font-bold uppercase tracking-wider brutal-press brutal-press-md disabled:opacity-50 text-sm"
                        >
                            Entrar em modo demonstração
                        </button>
                    </div>

                    <p className="text-sm text-muted-foreground mt-6 text-center">
                        {isRegister ? (
                            <>Já tem conta? <Link to="/login" className="font-bold text-foreground underline">Entrar</Link></>
                        ) : (
                            <>Novo por aqui? <Link to="/cadastro" className="font-bold text-foreground underline">Criar conta</Link></>
                        )}
                    </p>
                </div>
            </main>

            <style>{`
                .brutal-input {
                    width: 100%;
                    border: 4px solid hsl(var(--foreground));
                    background: hsl(var(--background));
                    border-radius: 0.75rem;
                    padding: 0.75rem 1rem;
                    font-weight: 500;
                    font-size: 1rem;
                    outline: none;
                }
                .brutal-input:focus { box-shadow: 4px 4px 0 0 hsl(var(--accent)); }
            `}</style>
        </div>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-xs font-bold uppercase tracking-widest">{label}</span>
            {children}
        </label>
    );
}
