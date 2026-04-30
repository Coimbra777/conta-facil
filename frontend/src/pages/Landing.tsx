import { useEffect } from "react";
import { Link } from "react-router-dom";
import { ArrowRight, CheckCircle2, ClipboardList, MessageSquareText, PiggyBank, Plane, Users2, PartyPopper, Trophy, Home } from "lucide-react";
import { useAuth } from "@/lib/auth";

export default function Landing() {
    const { user, loading, logout, isDemo } = useAuth();
    const isLoggedIn = Boolean(user) && !isDemo;

    useEffect(() => {
        if (!loading && isDemo) {
            void logout();
        }
    }, [isDemo, loading, logout]);

    return (
        <div className="min-h-dvh bg-background text-foreground">
            {/* NAV */}
            <nav className="max-w-7xl mx-auto px-5 sm:px-8 py-5 flex items-center justify-between">
                <Link to="/" className="font-display text-2xl uppercase tracking-tight flex items-center gap-2">
                    <span className="size-7 rounded-full bg-accent border-4 border-foreground" />
                    ContaCerta
                </Link>
                <div className="flex items-center gap-3">
                    {loading ? (
                        <span className="text-sm font-bold text-muted-foreground">Carregando...</span>
                    ) : isLoggedIn ? (
                        <>
                            <Link to="/dashboard" className="font-bold hidden sm:inline">Minhas cobranças</Link>
                            <button
                                type="button"
                                onClick={() => void logout()}
                                className="bg-foreground text-background border-4 border-foreground px-4 py-2 rounded-lg font-bold brutal-press brutal-press-sm"
                            >
                                Sair
                            </button>
                        </>
                    ) : (
                        <>
                            <Link to="/login" className="font-bold hidden sm:inline">Entrar</Link>
                            <Link
                                to="/cadastro"
                                className="bg-foreground text-background border-4 border-foreground px-4 py-2 rounded-lg font-bold brutal-press brutal-press-sm"
                            >
                                Criar conta
                            </Link>
                        </>
                    )}
                </div>
            </nav>

            {/* HERO */}
            <header className="max-w-7xl mx-auto px-5 sm:px-8 pt-10 sm:pt-16 pb-20 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div className="flex flex-col items-start">
                    <span className="inline-flex bg-arcade-cyan border-4 border-foreground brutal-shadow-sm px-3 py-1 font-bold uppercase text-xs sm:text-sm mb-8 -rotate-2">
                        Chega de “depois eu te pago”
                    </span>

                    <h1 className="font-display text-5xl sm:text-6xl lg:text-7xl tracking-tight leading-[0.95] mb-6 uppercase">
                        Organize cobranças <br />
                        em grupo por <span className="text-arcade-pink">Pix</span> sem climão.
                    </h1>

                    <p className="text-lg sm:text-xl font-medium max-w-[46ch] leading-snug text-muted-foreground mb-10">
                        {isLoggedIn
                            ? "Você está logado. Continue gerenciando suas cobranças."
                            : "Cadastre-se, crie uma cobrança compartilhada, envie o link para os participantes e acompanhe quem já pagou. Sem planilhas. Sem cobrança no WhatsApp."}
                    </p>

                    <div className="flex flex-wrap gap-4 w-full sm:w-auto">
                        {isLoggedIn ? (
                            <>
                                <Link
                                    to="/dashboard"
                                    className="bg-accent text-accent-foreground font-black uppercase tracking-wider text-base sm:text-lg px-6 sm:px-8 py-4 border-4 border-foreground rounded-xl brutal-press brutal-press-md text-center w-full sm:w-auto inline-flex items-center justify-center gap-2"
                                >
                                    Ir para minhas cobranças <ArrowRight className="size-5" />
                                </Link>
                                <Link
                                    to="/cobrancas/nova"
                                    className="bg-card text-foreground font-bold text-base sm:text-lg px-6 sm:px-8 py-4 border-4 border-foreground rounded-xl brutal-press brutal-press-md text-center w-full sm:w-auto"
                                >
                                    Criar nova cobrança
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => void logout()}
                                    className="bg-card text-foreground font-bold text-base sm:text-lg px-6 sm:px-8 py-4 border-4 border-foreground rounded-xl brutal-press brutal-press-md text-center w-full sm:w-auto"
                                >
                                    Sair
                                </button>
                            </>
                        ) : (
                            <>
                                <Link
                                    to="/cadastro"
                                    className="bg-accent text-accent-foreground font-black uppercase tracking-wider text-base sm:text-lg px-6 sm:px-8 py-4 border-4 border-foreground rounded-xl brutal-press brutal-press-md text-center w-full sm:w-auto inline-flex items-center justify-center gap-2"
                                >
                                    Criar conta grátis <ArrowRight className="size-5" />
                                </Link>
                                <Link
                                    to="/login?redirect=/cobrancas/nova"
                                    className="bg-card text-foreground font-bold text-base sm:text-lg px-6 sm:px-8 py-4 border-4 border-foreground rounded-xl brutal-press brutal-press-md text-center w-full sm:w-auto"
                                >
                                    Entrar
                                </Link>
                            </>
                        )}
                        <Link
                            to="/demo"
                            className="bg-card text-foreground font-bold text-base sm:text-lg px-6 sm:px-8 py-4 border-4 border-foreground rounded-xl brutal-press brutal-press-md text-center w-full sm:w-auto"
                        >
                            Ver demonstração
                        </Link>
                    </div>
                </div>

                {/* HERO MOCK */}
                <div className="relative w-full max-w-md mx-auto lg:ml-auto">
                    <div className="absolute inset-0 bg-arcade-pink/20 rounded-full blur-3xl scale-90 -z-10" />
                    <div className="bg-card border-4 border-foreground rounded-3xl brutal-shadow-xl p-6 sm:p-8 rotate-2 flex flex-col gap-5">
                        <div className="flex justify-between items-start">
                            <div>
                                <div className="text-xs font-bold uppercase tracking-widest text-muted-foreground">Cobrança ativa</div>
                                <h3 className="font-display text-2xl sm:text-3xl uppercase leading-tight mt-1">Churrasco<br />de domingo</h3>
                            </div>
                            <div className="size-12 bg-arcade-pink border-4 border-foreground rounded-full flex items-center justify-center brutal-shadow-sm text-2xl">🥩</div>
                        </div>

                        <div className="flex flex-col gap-3 mt-2">
                            <div className="flex justify-between font-display text-xl">
                                <span>R$ 350</span>
                                <span className="text-muted-foreground">/ R$ 500</span>
                            </div>
                            <div className="h-7 w-full bg-background border-4 border-foreground rounded-full overflow-hidden">
                                <div className="h-full w-[70%] bg-arcade-green border-r-4 border-foreground" />
                            </div>
                        </div>

                        <div className="flex items-center gap-3 mt-1">
                            <div className="flex -space-x-3">
                                <div className="size-10 rounded-full border-4 border-foreground bg-arcade-yellow flex items-center justify-center font-black text-xs">LM</div>
                                <div className="size-10 rounded-full border-4 border-foreground bg-arcade-cyan flex items-center justify-center font-black text-xs">BR</div>
                                <div className="size-10 rounded-full border-4 border-foreground bg-arcade-pink text-primary-foreground flex items-center justify-center font-black text-xs">JP</div>
                                <div className="size-10 rounded-full border-4 border-foreground bg-card flex items-center justify-center font-black text-xs">+4</div>
                            </div>
                            <div className="text-sm font-bold leading-tight">5 já pagaram <br /><span className="text-muted-foreground font-medium">2 pendentes</span></div>
                        </div>

                        <button className="mt-2 w-full bg-foreground text-background font-black text-lg py-4 rounded-xl border-4 border-foreground uppercase tracking-wider">
                            Pagar R$ 76,90
                        </button>
                    </div>

                    <div className="absolute -bottom-6 -left-6 bg-card border-4 border-foreground brutal-shadow rounded-2xl px-4 py-2 -rotate-6 flex items-center gap-2">
                        <span className="size-2 rounded-full bg-arcade-green animate-pulse-dot" />
                        <span className="font-bold uppercase text-xs">Marcos pagou agora</span>
                    </div>
                </div>
            </header>

            {/* HOW IT WORKS */}
            <section className="bg-foreground text-background py-24">
                <div className="max-w-7xl mx-auto px-5 sm:px-8">
                    <div className="text-center mb-16">
                        <h2 className="font-display text-4xl sm:text-6xl uppercase tracking-tight mb-3">
                            Em 3 passos
                        </h2>
                        <p className="text-background/70 max-w-xl mx-auto text-lg">Sem planilhas, sem confusão, sem precisar baixar app.</p>
                    </div>

                    <div className="grid md:grid-cols-3 gap-8 md:gap-10">
                        <Step n={1} color="bg-arcade-pink" icon={<ClipboardList className="size-7" />} title="Crie a cobrança" desc="Com sua conta, informe o valor total e o motivo. Adicione os participantes ou divida automaticamente." />
                        <Step n={2} color="bg-arcade-cyan" icon={<MessageSquareText className="size-7" />} title="Mande o link" desc="Compartilhe o link único no WhatsApp. Cada um vê seu valor e o seu Pix." className="md:mt-12" />
                        <Step n={3} color="bg-arcade-green" icon={<CheckCircle2 className="size-7" />} title="Acompanhe os pagamentos" desc="Receba comprovantes, aprove e veja quem ainda está pendente. Tudo num lugar só." className="md:mt-24" />
                    </div>
                </div>
            </section>

            {/* BENEFITS */}
            <section className="max-w-7xl mx-auto px-5 sm:px-8 py-20">
                <h2 className="font-display text-3xl sm:text-5xl uppercase tracking-tight mb-10">Por que ContaCerta</h2>
                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <Benefit title="Tudo num link só" desc="Um link compartilhado. Sem grupos. Sem áudio cobrando." />
                    <Benefit title="Visual de quem pagou" desc="Você vê na hora quem confirmou e quem ainda deve." />
                    <Benefit title="Comprovantes organizados" desc="Receba comprovantes anexados a cada participante." />
                    <Benefit title="Sua chave Pix, do seu jeito" desc="CPF, e-mail, telefone, aleatória ou copia e cola." />
                    <Benefit title="Funciona no celular" desc="Quem paga não precisa instalar nada. Tudo no navegador." />
                    <Benefit title="Grátis pra começar" desc="Abra sua conta e monte a primeira cobrança em poucos minutos." />
                </div>
            </section>

            {/* USE CASES */}
            <section className="bg-arcade-yellow border-y-4 border-foreground py-20">
                <div className="max-w-7xl mx-auto px-5 sm:px-8">
                    <h2 className="font-display text-3xl sm:text-5xl uppercase tracking-tight mb-10 text-foreground">Pra que tipo de rolê</h2>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <UseCase icon={<PartyPopper className="size-6" />} label="Churrasco" />
                        <UseCase icon={<Plane className="size-6" />} label="Viagem" />
                        <UseCase icon={<Home className="size-6" />} label="República" />
                        <UseCase icon={<PiggyBank className="size-6" />} label="Eventos" />
                        <UseCase icon={<Users2 className="size-6" />} label="Grupo de amigos" />
                        <UseCase icon={<Trophy className="size-6" />} label="Times e comunidades" />
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="max-w-3xl mx-auto px-5 sm:px-8 py-24 text-center">
                <h2 className="font-display text-4xl sm:text-6xl uppercase tracking-tight mb-6">
                    Bora rachar do jeito certo?
                </h2>
                <p className="text-lg text-muted-foreground mb-10">
                    {isLoggedIn
                        ? "Seu painel está pronto para continuar acompanhando pagamentos e criar novas cobranças."
                        : "Cadastre-se para criar cobranças, gerenciar participantes e validar comprovantes no painel."}
                </p>
                <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                    {isLoggedIn ? (
                        <>
                            <Link
                                to="/dashboard"
                                className="inline-flex items-center justify-center gap-2 bg-accent text-accent-foreground font-black uppercase text-lg px-10 py-5 border-4 border-foreground rounded-xl brutal-press brutal-press-lg w-full sm:w-auto"
                            >
                                Ir para minhas cobranças <ArrowRight className="size-5" />
                            </Link>
                            <Link
                                to="/cobrancas/nova"
                                className="inline-flex items-center justify-center gap-2 bg-card text-foreground font-bold text-lg px-10 py-5 border-4 border-foreground rounded-xl brutal-press brutal-press-lg w-full sm:w-auto"
                            >
                                Criar nova cobrança
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link
                                to="/cadastro"
                                className="inline-flex items-center justify-center gap-2 bg-accent text-accent-foreground font-black uppercase text-lg px-10 py-5 border-4 border-foreground rounded-xl brutal-press brutal-press-lg w-full sm:w-auto"
                            >
                                Criar conta <ArrowRight className="size-5" />
                            </Link>
                            <Link
                                to="/login?redirect=/cobrancas/nova"
                                className="inline-flex items-center justify-center gap-2 bg-card text-foreground font-bold text-lg px-10 py-5 border-4 border-foreground rounded-xl brutal-press brutal-press-lg w-full sm:w-auto"
                            >
                                Entrar
                            </Link>
                        </>
                    )}
                </div>
            </section>
        </div>
    );
}

function Step({ n, color, icon, title, desc, className = "" }: { n: number; color: string; icon: React.ReactNode; title: string; desc: string; className?: string }) {
    return (
        <div className={`${color} text-foreground border-4 border-foreground rounded-3xl p-8 brutal-shadow-lg relative ${className}`}>
            <div className="absolute -top-8 -right-4 font-display text-[7rem] leading-none opacity-20 select-none">{n}</div>
            <div className="size-14 bg-card border-4 border-foreground rounded-2xl brutal-shadow-sm flex items-center justify-center mb-6">{icon}</div>
            <h3 className="font-display text-2xl uppercase mb-3">{title}</h3>
            <p className="font-medium text-foreground/80 leading-relaxed">{desc}</p>
        </div>
    );
}

function Benefit({ title, desc }: { title: string; desc: string }) {
    return (
        <div className="border-4 border-foreground bg-card rounded-2xl p-6 brutal-shadow-sm">
            <h3 className="font-display text-xl uppercase mb-2">{title}</h3>
            <p className="text-muted-foreground">{desc}</p>
        </div>
    );
}

function UseCase({ icon, label }: { icon: React.ReactNode; label: string }) {
    return (
        <div className="bg-card border-4 border-foreground rounded-2xl p-4 flex flex-col items-center gap-2 brutal-shadow-sm text-center">
            <div className="size-10 rounded-xl border-4 border-foreground bg-arcade-pink text-primary-foreground flex items-center justify-center">{icon}</div>
            <span className="font-bold text-sm">{label}</span>
        </div>
    );
}
