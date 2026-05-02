import { useEffect, useState } from "react";
import { useParams, Link, useNavigate } from "react-router-dom";
import { AppShell } from "@/components/AppShell";
import { CopyButton } from "@/components/CopyButton";
import { ParticipantList } from "@/components/ParticipantList";
import { PaymentSummaryCard } from "@/components/DashboardCard";
import { RejectProofModal } from "@/components/RejectProofModal";
import { ModalShell } from "@/components/ModalShell";
import { StatusBadge } from "@/components/StatusBadge";
import { api } from "@/lib/api/client";
import type { Expense, Participant } from "@/lib/types";
import {
    CLOSED_EXPENSE_ORGANIZER,
    PROOF_MANAGEMENT_AUTODELETE_NOTICE,
} from "@/lib/closedExpenseCopy";
import {
    buildOrganizerExpenseShareMessage,
    buildPublicLink,
    buildWhatsAppShareUrl,
    formatBRL,
    formatDate,
    isDueDateBeforeToday,
} from "@/lib/format";
import { ArrowLeft, MessageCircle, Trash2 } from "lucide-react";

export default function ExpenseDetail() {
    const { id = "" } = useParams();
    const nav = useNavigate();
    const [exp, setExp] = useState<Expense | null | undefined>(undefined);
    const [rejectFor, setRejectFor] = useState<Participant | null>(null);
    const [proofFor, setProofFor] = useState<Participant | null>(null);
    const [proofPreview, setProofPreview] = useState<{
        url: string;
        mime: string;
    } | null>(null);
    const [proofLoading, setProofLoading] = useState(false);
    const [proofError, setProofError] = useState<string | null>(null);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const refresh = () => api.getExpense(id).then(setExp);
    useEffect(() => { refresh(); /* eslint-disable-next-line */ }, [id]);

    useEffect(() => {
        if (!proofFor) {
            setProofPreview((prev) => {
                if (prev) URL.revokeObjectURL(prev.url);
                return null;
            });
            setProofError(null);
            setProofLoading(false);
            return;
        }

        let cancelled = false;
        let objectUrl: string | null = null;

        setProofPreview((prev) => {
            if (prev) URL.revokeObjectURL(prev.url);
            return null;
        });
        setProofError(null);
        setProofLoading(true);

        api.fetchChargeProofForView(proofFor.id)
            .then((blob) => {
                const url = URL.createObjectURL(blob);
                if (cancelled) {
                    URL.revokeObjectURL(url);
                    return;
                }
                objectUrl = url;
                setProofPreview({
                    url,
                    mime: blob.type || "",
                });
            })
            .catch((e) => {
                if (!cancelled) {
                    setProofError(
                        e instanceof Error
                            ? e.message
                            : "Não foi possível carregar o comprovante.",
                    );
                }
            })
            .finally(() => {
                if (!cancelled) setProofLoading(false);
            });

        return () => {
            cancelled = true;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        };
    }, [proofFor]);

    if (exp === undefined) return <AppShell><div className="p-8">Carregando…</div></AppShell>;
    if (!exp) return (
        <AppShell>
            <div className="p-8 max-w-xl mx-auto text-center">
                <h1 className="font-display text-3xl uppercase mb-2">Cobrança não encontrada</h1>
                <Link to="/dashboard" className="underline font-bold">Voltar para o painel</Link>
            </div>
        </AppShell>
    );

    const paid = exp.participants.filter((p) => p.status === "validated").reduce((s, p) => s + p.amount, 0);
    const proofs = exp.participants.filter((p) => p.status === "proof_sent").length;
    const pending = exp.totalAmount - paid;
    const allPaid = exp.participants.length > 0 && exp.participants.every((p) => p.status === "validated");
    const isClosed = exp.status === "closed";
    const canDelete =
        !isClosed &&
        exp.participants.length > 0 &&
        exp.participants.every((p) => p.status === "pending");

    const onApprove = async (p: Participant) => {
        await api.validateCharge(exp.id, p.id);
        refresh();
    };
    const onReject = async (reason: string) => {
        if (!rejectFor) return;
        await api.rejectCharge(exp.id, rejectFor.id, reason);
        refresh();
    };

    const publicLink = buildPublicLink(exp.publicHash);
    const whatsappShareHref = isClosed
        ? ""
        : buildWhatsAppShareUrl(
              buildOrganizerExpenseShareMessage(exp.title, publicLink),
          );

    const onDeleteExpense = async () => {
        setDeleting(true);
        try {
            await api.deleteExpense(exp.id);
            nav("/dashboard");
        } finally {
            setDeleting(false);
            setConfirmDelete(false);
        }
    };

    return (
        <AppShell>
            <div className="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-8">
                <div>
                    <Link to="/dashboard" className="inline-flex items-center gap-1.5 text-sm font-bold mb-3">
                        <ArrowLeft className="size-4" /> Voltar
                    </Link>
                    <div className="border-4 border-foreground bg-card rounded-2xl brutal-shadow p-5 sm:p-6 flex flex-col gap-4">
                        <div className="flex flex-wrap items-start gap-3 justify-between">
                            <div className="min-w-0">
                                <h1 className="font-display text-3xl sm:text-4xl uppercase">{exp.title}</h1>
                                {exp.description && <p className="text-muted-foreground mt-1">{exp.description}</p>}
                                <p className="text-sm text-muted-foreground mt-1">Vence em {formatDate(exp.dueDate)}</p>
                                {isClosed && (
                                    <div
                                        role="status"
                                        className="mt-3 rounded-xl border-4 border-emerald-700/40 bg-emerald-50 px-3 py-3 text-sm font-medium text-emerald-950 dark:border-emerald-500/35 dark:bg-emerald-950/30 dark:text-emerald-50"
                                    >
                                        <strong className="font-black uppercase text-xs tracking-wide block mb-1">
                                            {CLOSED_EXPENSE_ORGANIZER.title}
                                        </strong>
                                        {CLOSED_EXPENSE_ORGANIZER.lines.map((line) => (
                                            <p key={line} className="leading-snug">
                                                {line}
                                            </p>
                                        ))}
                                    </div>
                                )}
                                {(exp.status === "open" || exp.status === undefined) &&
                                    isDueDateBeforeToday(exp.dueDate) && (
                                        <div
                                            role="status"
                                            className="mt-3 rounded-xl border-4 border-amber-600/45 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-950 dark:border-amber-500/40 dark:bg-amber-950/35 dark:text-amber-50"
                                        >
                                            <strong className="font-black uppercase text-xs tracking-wide">
                                                Vencimento (informativo)
                                            </strong>
                                            — neste MVP a data não bloqueia envio de comprovante nem validação; combine com os participantes se precisar.
                                        </div>
                                    )}
                            </div>
                            <div className="flex items-center gap-2 flex-wrap">
                                <StatusBadge status={allPaid ? "validated" : "pending"} />
                                <span className="font-display text-2xl tabular-nums">{formatBRL(exp.totalAmount)}</span>
                                {canDelete && (
                                    <button
                                        type="button"
                                        title="Excluir esta cobrança"
                                        onClick={() => setConfirmDelete(true)}
                                        className="border-4 border-foreground bg-status-rejected text-status-rejected-fg p-2 rounded-lg brutal-press brutal-press-sm"
                                        aria-label="Excluir cobrança"
                                    >
                                        <Trash2 className="size-5" />
                                    </button>
                                )}
                            </div>
                        </div>

                        {!isClosed ? (
                            <>
                                <div className="rounded-xl border-2 border-dashed border-foreground/35 bg-muted/40 px-4 py-3 text-sm leading-snug">
                                    {PROOF_MANAGEMENT_AUTODELETE_NOTICE}
                                </div>
                                <div className="flex items-center gap-2 flex-wrap p-3 bg-background border-4 border-foreground rounded-xl">
                                    <span className="text-xs font-bold uppercase tracking-widest text-muted-foreground">Link público</span>
                                    <code className="text-sm break-all flex-1 min-w-0">{publicLink}</code>
                                    <CopyButton variant="ghost" value={publicLink} label="Copiar" />
                                    <a
                                        href={whatsappShareHref}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1.5 border-4 border-foreground bg-arcade-cyan px-3 py-2 rounded-lg text-xs font-black uppercase brutal-press brutal-press-sm shrink-0"
                                    >
                                        <MessageCircle className="size-4" />
                                        WhatsApp
                                    </a>
                                </div>
                            </>
                        ) : null}
                    </div>
                </div>

                <PaymentSummaryCard total={exp.totalAmount} paid={paid} pending={pending} proofs={proofs} />

                <section>
                    <h2 className="font-display text-2xl uppercase mb-4">Participantes ({exp.participants.length})</h2>
                    <ParticipantList
                        participants={exp.participants}
                        onApprove={isClosed ? undefined : onApprove}
                        onReject={isClosed ? undefined : (p) => setRejectFor(p)}
                        onViewProof={isClosed ? undefined : (p) => setProofFor(p)}
                    />
                </section>
            </div>

            <ModalShell open={confirmDelete} title="Excluir cobrança?" onClose={() => !deleting && setConfirmDelete(false)}>
                <p className="text-sm mb-4">
                    Esta ação não pode ser desfeita. Só é permitido quando nenhum participante enviou comprovante ou teve pagamento confirmado.
                </p>
                <div className="flex gap-3 justify-end flex-wrap">
                    <button
                        type="button"
                        className="border-4 border-foreground bg-card px-4 py-2 rounded-xl font-bold"
                        disabled={deleting}
                        onClick={() => setConfirmDelete(false)}
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        className="border-4 border-foreground bg-status-rejected text-status-rejected-fg px-4 py-2 rounded-xl font-black uppercase"
                        disabled={deleting}
                        onClick={onDeleteExpense}
                    >
                        {deleting ? "Excluindo…" : "Excluir"}
                    </button>
                </div>
            </ModalShell>

            <RejectProofModal
                open={!!rejectFor}
                participantName={rejectFor?.name}
                onClose={() => setRejectFor(null)}
                onConfirm={onReject}
            />

            <ModalShell open={!!proofFor} title="Comprovante" onClose={() => setProofFor(null)}>
                <div className="flex flex-col items-stretch gap-4 w-full min-h-[180px]">
                    {proofLoading && (
                        <p className="text-center text-muted-foreground py-8">
                            Carregando comprovante…
                        </p>
                    )}
                    {proofError && (
                        <p className="text-center text-sm font-medium text-status-rejected px-2">
                            {proofError}
                        </p>
                    )}
                    {proofPreview &&
                        !proofLoading &&
                        (proofPreview.mime === "application/pdf" ? (
                            <iframe
                                title="Comprovante PDF"
                                src={proofPreview.url}
                                className="w-full min-h-[70vh] border-4 border-foreground rounded-xl bg-background"
                            />
                        ) : !proofPreview.mime ||
                          proofPreview.mime.startsWith("image/") ||
                          proofPreview.mime === "image/svg+xml" ? (
                            <img
                                src={proofPreview.url}
                                alt="Comprovante"
                                className="max-w-full border-4 border-foreground rounded-xl mx-auto"
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground text-center px-2">
                                Este tipo de arquivo não pode ser exibido aqui.
                                O organizador pode usar o download pela API se
                                precisar do arquivo original.
                            </p>
                        ))}
                </div>
            </ModalShell>
        </AppShell>
    );
}
