import { useCallback, useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { AppShell } from "@/components/AppShell";
import { DashboardCard } from "@/components/DashboardCard";
import { EmptyState } from "@/components/EmptyState";
import { StatusBadge } from "@/components/StatusBadge";
import { CopyButton } from "@/components/CopyButton";
import { api } from "@/lib/api/client";
import type { ApiStatus, Expense } from "@/lib/types";
import { buildPublicLink, formatBRL, formatDate } from "@/lib/format";
import {
    CircleDollarSign,
    Clock,
    ListChecks,
    PiggyBank,
    Plus,
    Receipt,
} from "lucide-react";

function sortDashboardExpenses(list: Expense[]): Expense[] {
    return [...list].sort((a, b) => {
        const dueMs = (iso?: string) => {
            if (!iso) return Number.POSITIVE_INFINITY;
            const t = new Date(iso).setHours(0, 0, 0, 0);
            return Number.isFinite(t) ? t : Number.POSITIVE_INFINITY;
        };
        const da = dueMs(a.dueDate);
        const db = dueMs(b.dueDate);
        if (da !== db) return da - db;

        const pct = (e: Expense) => {
            const n = e.participants.length;
            if (!n) return 0;
            return (
                e.participants.filter((p) => p.status === "validated").length /
                n
            );
        };
        const pd = pct(b) - pct(a);
        if (pd !== 0) return pd;

        return (
            new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
        );
    });
}

function isDueToday(iso?: string): boolean {
    if (!iso) return false;
    const d = new Date(iso);
    const t = new Date();
    return (
        d.getFullYear() === t.getFullYear() &&
        d.getMonth() === t.getMonth() &&
        d.getDate() === t.getDate()
    );
}

function paidSum(exp: Expense): number {
    return exp.participants
        .filter((p) => p.status === "validated")
        .reduce((s, p) => s + p.amount, 0);
}

function dashboardCardTone(
    pct: number,
    allValidated: boolean,
): "yellow" | "green" | "cyan" {
    if (allValidated) return "green";
    if (pct >= 70) return "cyan";
    return "yellow";
}

function dashboardCardLabel(
    pct: number,
    allValidated: boolean,
): "Pago" | "Quase pago" | "Pendente" {
    if (allValidated) return "Pago";
    if (pct >= 70) return "Quase pago";
    return "Pendente";
}

function DashboardSkeleton() {
    return (
        <div className="animate-pulse space-y-8">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div
                        key={i}
                        className="h-24 rounded-xl border-4 border-foreground bg-muted"
                    />
                ))}
            </div>
            <ul className="grid sm:grid-cols-2 gap-5">
                {Array.from({ length: 4 }).map((_, i) => (
                    <li
                        key={i}
                        className="border-4 border-foreground bg-card rounded-2xl p-5 flex flex-col gap-4"
                    >
                        <div className="h-7 bg-muted rounded w-3/4" />
                        <div className="h-4 bg-muted rounded w-1/2" />
                        <div className="h-10 bg-muted rounded w-full" />
                        <div className="h-3 bg-muted rounded w-full" />
                        <div className="h-10 bg-muted rounded w-full" />
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function Dashboard() {
    const [expenses, setExpenses] = useState<Expense[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setLoadError(null);
        try {
            const list = await api.listExpenses();
            setExpenses(list);
        } catch {
            setLoadError("Erro ao carregar suas cobranças.");
            setExpenses([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const sorted = useMemo(() => sortDashboardExpenses(expenses), [expenses]);

    const stats = useMemo(() => {
        const list = expenses;
        const totalExpected = list.reduce((s, e) => s + e.totalAmount, 0);
        const totalConfirmed = list.reduce((s, e) => s + paidSum(e), 0);
        const pending = list.reduce(
            (s, e) =>
                s +
                e.participants.filter((p) => p.status !== "validated").length,
            0,
        );
        const active = list.length;
        return { active, totalExpected, totalConfirmed, pending };
    }, [expenses]);

    return (
        <AppShell>
            <div className="max-w-6xl mx-auto px-4 sm:px-6 py-8 pb-28">
                <div className="flex items-end justify-between flex-wrap gap-4 mb-8">
                    <div>
                        <h1 className="font-display text-3xl sm:text-5xl uppercase tracking-tight">
                            Minhas cobranças
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Acompanhe quem pagou e quem ainda falta.
                        </p>
                    </div>
                    <Link
                        to="/cobrancas/nova"
                        className="hidden sm:inline-flex items-center gap-2 bg-accent text-accent-foreground border-4 border-foreground px-5 py-3 rounded-xl font-black uppercase brutal-press brutal-press-md"
                    >
                        <Plus className="size-5" /> Nova cobrança
                    </Link>
                </div>

                {loading ? (
                    <DashboardSkeleton />
                ) : loadError ? (
                    <div className="border-4 border-foreground bg-card rounded-2xl brutal-shadow-sm p-8 text-center max-w-md mx-auto">
                        <p className="font-bold text-lg mb-4">{loadError}</p>
                        <button
                            type="button"
                            onClick={() => load()}
                            className="bg-accent text-accent-foreground border-4 border-foreground px-6 py-3 rounded-xl font-black uppercase brutal-press brutal-press-md"
                        >
                            Tentar novamente
                        </button>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
                            <DashboardCard
                                tone="cyan"
                                icon={<ListChecks className="size-4" />}
                                label="Cobranças ativas"
                                value={stats.active}
                            />
                            <DashboardCard
                                tone="yellow"
                                icon={<PiggyBank className="size-4" />}
                                label="Total esperado"
                                value={formatBRL(stats.totalExpected)}
                            />
                            <DashboardCard
                                tone="green"
                                icon={<CircleDollarSign className="size-4" />}
                                label="Total confirmado"
                                value={formatBRL(stats.totalConfirmed)}
                            />
                            <DashboardCard
                                tone="pink"
                                icon={<Clock className="size-4" />}
                                label="Pagamentos pendentes"
                                value={stats.pending}
                            />
                        </div>

                        {expenses.length === 0 ? (
                            <EmptyState
                                icon={<Receipt className="size-6" />}
                                title="Você ainda não criou nenhuma cobrança."
                                description="Crie uma cobrança e compartilhe o link para começar."
                                action={
                                    <Link
                                        to="/cobrancas/nova"
                                        className="inline-flex items-center gap-2 bg-accent text-accent-foreground border-4 border-foreground px-5 py-3 rounded-xl font-black uppercase brutal-press brutal-press-md"
                                    >
                                        <Plus className="size-5" /> Criar
                                        cobrança
                                    </Link>
                                }
                            />
                        ) : (
                            <ul className="grid sm:grid-cols-2 gap-5">
                                {sorted.map((exp) => {
                                    const paid = exp.participants.filter(
                                        (p) => p.status === "validated",
                                    ).length;
                                    const pct =
                                        exp.participants.length === 0
                                            ? 0
                                            : Math.round(
                                                  (paid /
                                                      exp.participants.length) *
                                                      100,
                                              );
                                    const allValidated =
                                        paid === exp.participants.length &&
                                        exp.participants.length > 0;
                                    const overall: ApiStatus = allValidated
                                        ? "validated"
                                        : "pending";
                                    const remaining = Math.max(
                                        0,
                                        exp.totalAmount - paidSum(exp),
                                    );
                                    const dueToday = isDueToday(exp.dueDate);
                                    const cardTone = dashboardCardTone(
                                        pct,
                                        allValidated,
                                    );
                                    const cardLabel = dashboardCardLabel(
                                        pct,
                                        allValidated,
                                    );

                                    return (
                                        <li
                                            key={exp.id}
                                            className="border-4 border-foreground bg-card rounded-2xl brutal-shadow-sm p-5 flex flex-col gap-4 transition-transform hover:-translate-y-0.5"
                                        >
                                            <div className="flex items-start justify-between gap-3 flex-wrap">
                                                <div className="min-w-0">
                                                    <h3 className="font-display text-xl uppercase truncate">
                                                        {exp.title}
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        {
                                                            exp.participants
                                                                .length
                                                        }{" "}
                                                        participantes · vence{" "}
                                                        {formatDate(
                                                            exp.dueDate,
                                                        )}
                                                        {dueToday ? (
                                                            <span className="ml-1 font-bold text-foreground">
                                                                · Vence hoje
                                                            </span>
                                                        ) : null}
                                                    </p>
                                                </div>
                                                <div className="flex flex-col items-end gap-1 shrink-0">
                                                    <span
                                                        className={`text-xs font-black uppercase px-2 py-1 rounded-md border-2 border-foreground ${
                                                            cardTone ===
                                                            "green"
                                                                ? "bg-arcade-green"
                                                                : cardTone ===
                                                                    "cyan"
                                                                  ? "bg-arcade-cyan"
                                                                  : "bg-arcade-yellow"
                                                        }`}
                                                    >
                                                        {cardLabel}
                                                    </span>
                                                    <StatusBadge
                                                        status={overall}
                                                    />
                                                </div>
                                            </div>

                                            <div className="font-display text-3xl tabular-nums">
                                                {formatBRL(exp.totalAmount)}
                                            </div>

                                            {!allValidated &&
                                            remaining > 0.009 ? (
                                                <p className="text-sm font-bold text-muted-foreground">
                                                    Faltam{" "}
                                                    {formatBRL(remaining)}{" "}
                                                    para concluir
                                                </p>
                                            ) : null}

                                            <div>
                                                <div className="flex justify-between text-xs font-bold mb-1">
                                                    <span>
                                                        {paid}/
                                                        {
                                                            exp.participants
                                                                .length
                                                        }{" "}
                                                        pagaram
                                                    </span>
                                                    <span>{pct}%</span>
                                                </div>
                                                <div className="h-3 w-full border-2 border-foreground bg-background rounded-full overflow-hidden">
                                                    <div
                                                        className="h-full bg-arcade-green transition-[width] duration-300"
                                                        style={{
                                                            width: `${pct}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2 mt-1">
                                                <Link
                                                    to={`/cobrancas/${exp.id}`}
                                                    className="flex-1 text-center bg-foreground text-background border-4 border-foreground px-4 py-2 rounded-lg font-bold brutal-press brutal-press-sm"
                                                >
                                                    Ver detalhes
                                                </Link>
                                                <CopyButton
                                                    variant="ghost"
                                                    value={buildPublicLink(
                                                        exp.publicHash,
                                                    )}
                                                    label="Copiar link"
                                                />
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </>
                )}
            </div>

            <Link
                to="/cobrancas/nova"
                className="sm:hidden fixed bottom-5 right-5 left-5 z-40 flex items-center justify-center gap-2 bg-accent text-accent-foreground border-4 border-foreground py-4 rounded-xl font-black uppercase brutal-press brutal-press-md shadow-lg"
            >
                <Plus className="size-5" /> Nova cobrança
            </Link>
        </AppShell>
    );
}
