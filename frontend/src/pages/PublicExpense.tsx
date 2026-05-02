import { useEffect, useState } from "react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
import { api, isPublicExpenseUsingMock } from "@/lib/api/client";
import {
    CLOSED_EXPENSE_ORGANIZER,
    CLOSED_EXPENSE_PUBLIC_PARTICIPANT,
    PROOF_MANAGEMENT_AUTODELETE_NOTICE,
} from "@/lib/closedExpenseCopy";
import { mockApi } from "@/lib/api/mockStore";
import type { Expense, Participant } from "@/lib/types";
import { PixKeyBox } from "@/components/PixKeyBox";
import { ProofUpload } from "@/components/ProofUpload";
import { StatusBadge } from "@/components/StatusBadge";
import { CopyButton } from "@/components/CopyButton";
import {
    formatBRL,
    initials,
    buildPublicLink,
    buildPublicManageLink,
    formatDate,
    isDueDateBeforeToday,
} from "@/lib/format";
import {
    BRAZIL_PHONE_ERROR_MESSAGE,
    digitsOnly,
    formatBrazilPhoneDisplay,
    GENERIC_BRAZIL_PHONE_PLACEHOLDER,
    isValidBrazilPhone,
} from "@/lib/inputMasks";
import {
    getPublicManageToken,
    setPublicManageToken,
} from "@/lib/publicManageToken";
import { CheckCircle2, Clock, Smartphone, AlertTriangle } from "lucide-react";

const IDENTIFY_ERROR_MAIN =
    "Não encontramos esses dados nesta cobrança. Confira o nome e telefone ou fale com o organizador.";

function organizerCopy(name: string, canManage = false): string {
    const trimmed = name.trim();

    if (canManage && trimmed) {
        return `Organizado por ${trimmed}`;
    }

    return "Organizado pelo responsável da cobrança";
}

export type PublicExpenseProps = {
    /** Usado em `/demo`: mesma UI do participante sem depender da rota `/p/:hash`. */
    embeddedDemoHash?: string;
};

export default function PublicExpense({
    embeddedDemoHash,
}: PublicExpenseProps = {}) {
    const { hash: routeHash = "" } = useParams();
    const hash = embeddedDemoHash ?? routeHash;
    const embedInDemoPage = Boolean(embeddedDemoHash);
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const [exp, setExp] = useState<Expense | null | undefined>(undefined);
    const [name, setName] = useState("");
    const [phone, setPhone] = useState("");
    const [participant, setParticipant] = useState<Participant | null>(null);
    const [identifying, setIdentifying] = useState(false);
    const [identifyError, setIdentifyError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const phoneDigits = digitsOnly(phone);
    const phoneValid = isValidBrazilPhone(phone);
    /** Botão principal: só exige campos preenchidos (validação de formato na submissão). */
    const canSubmitIdentify =
        name.trim().length > 0 && phoneDigits.length > 0 && !identifying;
    const showDemoBypass =
        Boolean(identifyError) && isPublicExpenseUsingMock(hash);

    useEffect(() => {
        if (typeof window !== "undefined") {
            const h = window.location.hash;
            if (h.startsWith("#manage=")) {
                const raw = h.slice("#manage=".length);
                const token = decodeURIComponent(raw.replace(/\+/g, "%20"));
                if (token) setPublicManageToken(hash, token);
                window.history.replaceState(
                    null,
                    "",
                    window.location.pathname + window.location.search,
                );
            }
        }

        const legacyQuery = searchParams.get("manage");
        if (legacyQuery) {
            setPublicManageToken(hash, legacyQuery);
            navigate(`/p/${hash}`, { replace: true });
            return;
        }

        const token = getPublicManageToken(hash);
        api.getPublicExpense(hash, token).then(setExp);
    }, [hash, searchParams, navigate]);

    useEffect(() => {
        if (exp?.status === "closed") setParticipant(null);
    }, [exp?.status]);

    const isClosed = exp?.status === "closed";

    const identify = async (e: React.FormEvent) => {
        e.preventDefault();
        setIdentifyError(null);
        if (!name.trim()) return;
        if (!phoneValid) {
            setIdentifyError(BRAZIL_PHONE_ERROR_MESSAGE);
            return;
        }
        setIdentifying(true);
        try {
            const result = await api.identifyParticipant(
                hash,
                name.trim(),
                phoneDigits,
                getPublicManageToken(hash),
            );
            if (!result) {
                setIdentifyError(IDENTIFY_ERROR_MAIN);
                return;
            }
            setParticipant(result.participant);
            setExp(result.expense);
        } finally {
            setIdentifying(false);
        }
    };

    const continueDemo = async () => {
        if (!isPublicExpenseUsingMock(hash)) return;
        if (!name.trim() || !phoneValid) {
            setIdentifyError(BRAZIL_PHONE_ERROR_MESSAGE);
            return;
        }
        setIdentifyError(null);
        setIdentifying(true);
        try {
            const result = await mockApi.continuePublicDemoIdentification(
                hash,
                name.trim(),
                phoneDigits,
            );
            if (!result) {
                setIdentifyError(
                    "Não foi possível continuar no modo demonstração. Tente novamente.",
                );
                return;
            }
            setParticipant(result.participant);
            setExp(result.expense);
        } finally {
            setIdentifying(false);
        }
    };

    const sendProof = async (file: File) => {
        if (!participant) return;
        setSubmitting(true);
        try {
            const updated = await api.submitProof(hash, participant, file);
            if (updated) setParticipant(updated);
        } finally {
            setSubmitting(false);
        }
    };

    if (exp === undefined) {
        return (
            <PublicShell fillViewport={!embedInDemoPage}>
                <div className="p-8 text-center">Carregando…</div>
            </PublicShell>
        );
    }
    if (!exp) {
        return (
            <PublicShell fillViewport={!embedInDemoPage}>
                <div className="p-8 text-center">
                    <h1 className="font-display text-3xl uppercase mb-2">
                        Link inválido
                    </h1>
                    <p className="text-muted-foreground">
                        Confira com o organizador se o link está correto.
                    </p>
                    <BackToHomeLink className="mt-6 mx-auto" />
                </div>
            </PublicShell>
        );
    }

    return (
        <PublicShell fillViewport={!embedInDemoPage}>
            <header className="px-5 sm:px-6 pt-6 sm:pt-8 pb-6 border-b-4 border-foreground bg-arcade-yellow">
                <div className="max-w-md mx-auto flex flex-col gap-4">
                    <BackToHomeLink />
                    <div className="flex flex-col gap-1">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Cobrança compartilhada
                        </span>
                        <h1 className="font-display text-3xl sm:text-4xl uppercase leading-tight">
                            {exp.title}
                        </h1>
                        <p className="text-sm font-bold mt-2">
                            {organizerCopy(exp.organizerName, exp.canManage)}
                        </p>
                        {exp.description && (
                            <p className="text-sm mt-1 opacity-80">
                                {exp.description}
                            </p>
                        )}
                        {typeof exp.participantsTotalCount === "number" &&
                            exp.participantsTotalCount > 0 &&
                            !participant && (
                                <p className="text-sm font-bold mt-2 tabular-nums">
                                    {exp.validatedChargesCount ?? 0} pago(s)
                                    {" · "}
                                    {exp.openChargesCount ?? 0} em aberto
                                    {" · "}
                                    {exp.participantsTotalCount} participante(s)
                                </p>
                            )}
                    </div>
                </div>
            </header>

            {(exp.status === "open" || exp.status === undefined) &&
                isDueDateBeforeToday(exp.dueDate) && (
                <div className="px-5 sm:px-6 pt-4 max-w-md mx-auto w-full">
                    <div
                        role="status"
                        className="rounded-xl border-4 border-amber-600/50 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950 dark:border-amber-500/40 dark:bg-amber-950/30 dark:text-amber-50"
                    >
                            <span className="font-black uppercase text-xs tracking-widest block mb-1">
                                Vencimento (informativo)
                            </span>
                            Esta cobrança tinha vencimento em{" "}
                            <strong>{formatDate(exp.dueDate)}</strong>. No MVP o
                            prazo não bloqueia pagamento nem envio de comprovante;
                            combine com o organizador se precisar de mais tempo.
                        </div>
                    </div>
                )}

            {exp.canManage ? (
                <div className="px-5 sm:px-6 pt-4 max-w-md mx-auto w-full">
                    {isClosed ? (
                        <div
                            role="status"
                            className="rounded-xl border-4 border-emerald-700/40 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950 dark:border-emerald-500/35 dark:bg-emerald-950/30 dark:text-emerald-50"
                        >
                            <strong className="font-black uppercase text-xs tracking-widest block mb-1">
                                {CLOSED_EXPENSE_ORGANIZER.title}
                            </strong>
                            {CLOSED_EXPENSE_ORGANIZER.lines.map((line) => (
                                <p key={line} className="leading-snug">
                                    {line}
                                </p>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border-4 border-dashed border-foreground/40 bg-muted/50 px-4 py-3 text-sm flex flex-col gap-3">
                            <p className="font-bold uppercase text-xs tracking-widest text-muted-foreground">
                                Modo organizador
                            </p>
                            <p className="leading-snug">
                                Compartilhe o{" "}
                                <strong>link para participantes</strong> com quem vai
                                pagar (ele não contém o token de gestão). Guarde o{" "}
                                <strong>link de gerenciamento</strong> em lugar
                                seguro: com ele você aprova ou rejeita comprovantes.
                                Se perder esse link, não há recuperação automática
                                neste MVP.
                            </p>
                            <p className="rounded-xl border-2 border-dashed border-foreground/30 bg-background/80 px-3 py-2 leading-snug">
                                {PROOF_MANAGEMENT_AUTODELETE_NOTICE}
                            </p>
                            <div className="flex flex-col sm:flex-row gap-2 flex-wrap">
                                <CopyButton
                                    value={buildPublicLink(hash)}
                                    label="Copiar link para participantes"
                                    className="flex-1 text-sm py-3"
                                />
                                {getPublicManageToken(hash) ? (
                                    <CopyButton
                                        value={buildPublicManageLink(
                                            hash,
                                            getPublicManageToken(hash)!,
                                        )}
                                        label="Copiar link de gerenciamento"
                                        variant="ghost"
                                        className="flex-1 text-sm py-3"
                                    />
                                ) : null}
                            </div>
                        </div>
                    )}
                </div>
            ) : null}

            <main className="px-5 sm:px-6 py-6 max-w-md mx-auto flex flex-col gap-5">
                {isClosed ? (
                    !exp.canManage ? (
                        <div
                            role="status"
                            className="border-4 border-foreground bg-card rounded-2xl brutal-shadow p-5"
                        >
                            <strong className="font-display text-xl uppercase block mb-2">
                                {CLOSED_EXPENSE_PUBLIC_PARTICIPANT.title}
                            </strong>
                            {CLOSED_EXPENSE_PUBLIC_PARTICIPANT.lines.map((line) => (
                                <p
                                    key={line}
                                    className="text-sm text-muted-foreground leading-snug"
                                >
                                    {line}
                                </p>
                            ))}
                        </div>
                    ) : null
                ) : !participant ? (
                    <form
                        onSubmit={identify}
                        className="border-4 border-foreground bg-card rounded-2xl brutal-shadow p-5 flex flex-col gap-4"
                    >
                        <h2 className="font-display text-xl uppercase">
                            Quem é você?
                        </h2>
                        <p className="text-xs font-bold uppercase tracking-widest text-muted-foreground -mt-2 leading-snug">
                            Valor total da cobrança:{" "}
                            <span className="text-foreground font-black">
                                {formatBRL(exp.totalAmount)}
                            </span>
                            {exp.dueDate ? (
                                <>
                                    {" "}
                                    · vencimento {formatDate(exp.dueDate)}
                                </>
                            ) : null}
                        </p>
                        <p className="text-sm text-muted-foreground leading-snug">
                            Digite o mesmo nome e telefone que o organizador usou na
                            lista desta cobrança.
                        </p>
                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-bold uppercase tracking-widest">
                                Seu nome
                            </span>
                            <input
                                className="public-input"
                                value={name}
                                autoComplete="name"
                                onChange={(e) => {
                                    setName(e.target.value);
                                    setIdentifyError(null);
                                }}
                                placeholder="Ex.: Marina Reis"
                            />
                        </label>
                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-bold uppercase tracking-widest">
                                Telefone (WhatsApp)
                            </span>
                            <input
                                className="public-input"
                                inputMode="tel"
                                autoComplete="tel"
                                value={phone}
                                onChange={(e) => {
                                    setPhone(
                                        formatBrazilPhoneDisplay(
                                            e.target.value,
                                        ),
                                    );
                                    setIdentifyError(null);
                                }}
                                onBlur={() => {
                                    if (phoneDigits.length > 0 && !phoneValid) {
                                        setIdentifyError(
                                            BRAZIL_PHONE_ERROR_MESSAGE,
                                        );
                                    }
                                }}
                                placeholder={GENERIC_BRAZIL_PHONE_PLACEHOLDER}
                            />
                        </label>
                        {identifyError && (
                            <div
                                role="alert"
                                className="rounded-xl border-2 border-amber-600/45 bg-amber-50 px-3 py-3 text-sm text-amber-950 dark:border-amber-500/40 dark:bg-amber-950/35 dark:text-amber-50"
                            >
                                <p className="font-medium leading-snug">
                                    {identifyError}
                                </p>
                                {showDemoBypass && (
                                    <p className="mt-2 font-medium leading-snug opacity-95">
                                        Como esta é uma demonstração, você
                                        também pode continuar para conhecer o
                                        fluxo.
                                    </p>
                                )}
                            </div>
                        )}
                        {showDemoBypass && (
                            <div className="rounded-xl border-2 border-dashed border-muted-foreground/35 bg-muted/40 px-3 py-3 text-sm">
                                <p className="font-medium text-foreground leading-snug">
                                    Esta é uma cobrança de demonstração. Você
                                    pode continuar para visualizar como funciona
                                    o pagamento.
                                </p>
                                <button
                                    type="button"
                                    disabled={
                                        identifying ||
                                        !name.trim() ||
                                        !phoneValid
                                    }
                                    onClick={continueDemo}
                                    className="mt-3 w-full border-4 border-foreground bg-background py-3.5 rounded-xl font-bold uppercase tracking-wide brutal-press brutal-press-md disabled:opacity-50 min-h-[48px]"
                                >
                                    {identifying
                                        ? "Carregando…"
                                        : "Continuar no modo demonstração"}
                                </button>
                            </div>
                        )}
                        <button
                            type="submit"
                            disabled={!canSubmitIdentify}
                            className="bg-accent text-accent-foreground border-4 border-foreground py-4 rounded-xl font-black uppercase tracking-wider brutal-press brutal-press-md disabled:opacity-50 min-h-[52px]"
                        >
                            {identifying ? "Verificando…" : "Ver meu pagamento"}
                        </button>
                    </form>
                ) : (
                    <>
                        <div className="border-4 border-foreground bg-card rounded-2xl brutal-shadow p-5 flex flex-col gap-3">
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-3 min-w-0">
                                    <div className="size-12 rounded-full border-4 border-foreground bg-arcade-cyan flex items-center justify-center font-black">
                                        {initials(participant.name)}
                                    </div>
                                    <div className="min-w-0">
                                        <div className="font-bold truncate">
                                            {participant.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Seu valor
                                        </div>
                                    </div>
                                </div>
                                <StatusBadge status={participant.status} />
                            </div>
                            <div className="font-display text-5xl tabular-nums leading-none">
                                {formatBRL(participant.amount)}
                            </div>
                        </div>

                        {participant.status === "validated" && (
                            <Banner
                                tone="green"
                                icon={<CheckCircle2 className="size-5" />}
                                title="Pagamento confirmado"
                            >
                                Obrigado! O organizador confirmou seu pagamento.
                            </Banner>
                        )}

                        {participant.status === "proof_sent" && (
                            <Banner
                                tone="cyan"
                                icon={<Clock className="size-5" />}
                                title="Comprovante recebido"
                            >
                                Recebemos seu comprovante. Aguarde a confirmação
                                do organizador.
                            </Banner>
                        )}

                        {participant.status === "rejected" && (
                            <Banner
                                tone="red"
                                icon={<AlertTriangle className="size-5" />}
                                title="Comprovante rejeitado"
                            >
                                {participant.rejectionReason ??
                                    "Confira o motivo e envie um novo comprovante."}
                            </Banner>
                        )}

                        {(participant.status === "pending" ||
                            participant.status === "rejected") && (
                            <>
                                <PixKeyBox
                                    pixKey={exp.pixKey}
                                    pixKeyType={exp.pixKeyType}
                                    receiverName={exp.pixReceiverName}
                                />

                                <ol className="border-4 border-foreground bg-card rounded-2xl p-5 flex flex-col gap-3 brutal-shadow-sm text-sm">
                                    <Step
                                        n={1}
                                        text="Copie a chave Pix acima"
                                    />
                                    <Step
                                        n={2}
                                        text="Abra o app do seu banco"
                                    />
                                    <Step
                                        n={3}
                                        text="Faça o pagamento do valor exato"
                                    />
                                    <Step
                                        n={4}
                                        text="Volte aqui e envie seu comprovante"
                                    />
                                </ol>

                                <div className="border-4 border-foreground bg-card rounded-2xl p-5 brutal-shadow flex flex-col gap-3">
                                    <div className="flex items-center gap-2">
                                        <Smartphone className="size-5" />
                                        <h3 className="font-display text-lg uppercase">
                                            Enviar comprovante
                                        </h3>
                                    </div>
                                    <ProofUpload
                                        onSubmit={sendProof}
                                        submitting={submitting}
                                    />
                                </div>
                            </>
                        )}
                    </>
                )}
            </main>

            <style>{`
                .public-input {
                    width: 100%;
                    border: 4px solid hsl(var(--foreground));
                    background: hsl(var(--background));
                    border-radius: 0.75rem;
                    padding: 0.85rem 1rem;
                    font-size: 1rem;
                    font-weight: 500;
                    outline: none;
                }
                .public-input:focus { box-shadow: 4px 4px 0 0 hsl(var(--accent)); }
            `}</style>
        </PublicShell>
    );
}

function BackToHomeLink({ className = "" }: { className?: string }) {
    return (
        <Link
            to="/"
            className={`flex w-fit items-center gap-1.5 text-sm font-bold text-foreground underline-offset-4 hover:underline min-h-11 py-2 -mx-1 px-1 rounded-lg ${className}`}
        >
            ← Voltar para o início
        </Link>
    );
}

function PublicShell({
    children,
    fillViewport = true,
}: {
    children: React.ReactNode;
    /** Quando false, uso embutido em `/demo` sem forçar altura da viewport inteira. */
    fillViewport?: boolean;
}) {
    return (
        <div
            className={`bg-background flex flex-col ${fillViewport ? "min-h-dvh" : "min-h-0 flex-1"}`}
        >
            <div className="border-b-4 border-foreground bg-foreground text-background">
                <div className="max-w-md mx-auto px-5 py-3 flex items-center gap-2">
                    <span className="size-5 rounded-full bg-accent border-2 border-background" />
                    <span className="font-display uppercase">
                        ContaCerta Pix
                    </span>
                </div>
            </div>
            <div className="flex-1 flex flex-col">{children}</div>
            <footer className="text-center text-xs text-muted-foreground px-4 py-5 border-t border-border/50 leading-relaxed">
                <p className="sm:hidden">
                    O pagamento é feito diretamente via Pix para o organizador.
                    O ContaCerta Pix não movimenta seu dinheiro.
                </p>
                <p className="hidden sm:block max-w-xl mx-auto">
                    Você paga direto para o organizador via Pix. O ContaCerta
                    Pix apenas ajuda a organizar a cobrança e acompanhar os
                    comprovantes.
                </p>
            </footer>
        </div>
    );
}

function Banner({
    tone,
    icon,
    title,
    children,
}: {
    tone: "green" | "cyan" | "red";
    icon: React.ReactNode;
    title: string;
    children: React.ReactNode;
}) {
    const bg =
        tone === "green"
            ? "bg-arcade-green"
            : tone === "cyan"
              ? "bg-arcade-cyan"
              : "bg-status-rejected";
    const fg = tone === "red" ? "text-status-rejected-fg" : "text-foreground";
    return (
        <div
            className={`border-4 border-foreground rounded-2xl p-5 brutal-shadow-sm ${bg} ${fg}`}
        >
            <div className="flex items-center gap-2 font-display text-lg uppercase mb-1">
                {icon}
                {title}
            </div>
            <p className="text-sm font-medium opacity-90">{children}</p>
        </div>
    );
}

function Step({ n, text }: { n: number; text: string }) {
    return (
        <li className="flex items-center gap-3">
            <span className="size-7 rounded-full border-2 border-foreground bg-arcade-pink text-primary-foreground font-black grid place-items-center text-xs">
                {n}
            </span>
            <span className="font-medium">{text}</span>
        </li>
    );
}
