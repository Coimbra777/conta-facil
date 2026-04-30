import { Link } from "react-router-dom";
import { StatusBadge } from "@/components/StatusBadge";
import {
    DEMO_PRESENTATION_PUBLIC_HASH,
    getDemoPresentationSeedExpense,
} from "@/lib/api/mockStore";
import { formatBRL, formatDate } from "@/lib/format";
import PublicExpense from "./PublicExpense";

export default function Demo() {
    const sample = getDemoPresentationSeedExpense();

    return (
        <div className="min-h-dvh bg-background flex flex-col text-foreground">
            <div className="border-b-4 border-foreground bg-arcade-cyan px-5 py-5">
                <div className="max-w-lg mx-auto flex flex-col gap-3">
                    <span className="inline-flex w-fit bg-card border-4 border-foreground px-2 py-1 text-xs font-black uppercase tracking-widest brutal-shadow-sm">
                        Demonstração · exemplo
                    </span>
                    <p className="font-display text-2xl sm:text-3xl uppercase leading-tight">
                        Esta é uma demonstração do sistema.
                    </p>
                    <p className="text-sm font-medium leading-snug text-foreground/90">
                        Crie uma conta para criar suas próprias cobranças.
                    </p>
                    <div className="flex flex-wrap gap-3 pt-1">
                        <Link
                            to="/cadastro"
                            className="inline-flex items-center justify-center bg-accent text-accent-foreground font-black uppercase tracking-wider text-sm px-5 py-3 border-4 border-foreground rounded-xl brutal-press brutal-press-md"
                        >
                            Criar conta
                        </Link>
                        <Link
                            to="/login"
                            className="inline-flex items-center justify-center bg-card text-foreground font-bold text-sm px-5 py-3 border-4 border-foreground rounded-xl brutal-press brutal-press-md"
                        >
                            Entrar
                        </Link>
                        <Link
                            to="/"
                            className="inline-flex items-center justify-center text-sm font-bold underline underline-offset-4 py-3 px-1"
                        >
                            Voltar ao início
                        </Link>
                    </div>
                </div>
            </div>

            <section
                aria-labelledby="demo-participants-heading"
                className="max-w-lg mx-auto w-full px-5 py-6 border-b-4 border-dashed border-muted-foreground/25"
            >
                <h2
                    id="demo-participants-heading"
                    className="font-display text-xl uppercase mb-1"
                >
                    Cobrança de exemplo
                </h2>
                <p className="text-sm text-muted-foreground leading-snug mb-4">
                    Abaixo você vê como o organizador enxerga os participantes e
                    os status. Depois, experimente o fluxo de participante com
                    nome e telefone de alguém da lista (dados fictícios).
                </p>
                <div className="rounded-2xl border-4 border-foreground bg-card brutal-shadow-sm p-4 mb-4">
                    <div className="font-bold text-lg">{sample.title}</div>
                    {sample.description ? (
                        <p className="text-sm text-muted-foreground mt-1">
                            {sample.description}
                        </p>
                    ) : null}
                    <dl className="mt-3 grid gap-1 text-sm font-medium tabular-nums">
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">Total</dt>
                            <dd>{formatBRL(sample.totalAmount)}</dd>
                        </div>
                        {sample.dueDate ? (
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Vencimento
                                </dt>
                                <dd>{formatDate(sample.dueDate)}</dd>
                            </div>
                        ) : null}
                    </dl>
                </div>
                <ul className="flex flex-col gap-2">
                    {sample.participants.map((p) => (
                        <li
                            key={p.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border-4 border-foreground bg-background px-4 py-3"
                        >
                            <div className="min-w-0">
                                <div className="font-bold truncate">{p.name}</div>
                                <div className="text-xs text-muted-foreground tabular-nums">
                                    {formatBRL(p.amount)}
                                    {p.phone ? ` · ${p.phone}` : null}
                                </div>
                            </div>
                            <div className="flex flex-col items-end gap-1 shrink-0">
                                <StatusBadge status={p.status} />
                                {p.status === "validated" && p.hasProof ? (
                                    <span
                                        className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground"
                                        title="Na conta real, o organizador abre o arquivo aqui."
                                    >
                                        Ver comprovante (simulado na demo)
                                    </span>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            </section>

            <div className="flex-1 flex flex-col min-h-0">
                <PublicExpense embeddedDemoHash={DEMO_PRESENTATION_PUBLIC_HASH} />
            </div>
        </div>
    );
}
