import type { Participant } from "@/lib/types";
import { StatusBadge } from "./StatusBadge";
import { formatBRL, formatDateTime, initials } from "@/lib/format";
import { Check, Eye, X } from "lucide-react";

interface Props {
    participants: Participant[];
    onApprove?: (p: Participant) => void;
    onReject?: (p: Participant) => void;
    onViewProof?: (p: Participant) => void;
}

export function ParticipantList({
    participants,
    onApprove,
    onReject,
    onViewProof,
}: Props) {
    if (participants.length === 0) {
        return (
            <div className="border-4 border-dashed border-foreground rounded-2xl p-8 text-center text-muted-foreground">
                Nenhum participante ainda.
            </div>
        );
    }

    return (
        <ul className="flex flex-col gap-3">
            {participants.map((p) => (
                <li
                    key={p.id}
                    className="border-4 border-foreground rounded-2xl bg-card p-4 sm:p-5 brutal-shadow-sm flex flex-col gap-4"
                >
                    <div className="flex items-start gap-4">
                        <div className="size-12 rounded-full border-4 border-foreground bg-arcade-yellow flex items-center justify-center font-black shrink-0">
                            {initials(p.name)}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex flex-wrap items-center gap-2 justify-between">
                                <div className="min-w-0">
                                    <div className="font-bold truncate">
                                        {p.name}
                                    </div>
                                    <div className="text-sm text-muted-foreground truncate">
                                        {p.phone}
                                    </div>
                                </div>
                                <StatusBadge status={p.status} />
                            </div>
                            <div className="mt-2 flex items-center gap-4 flex-wrap">
                                <span className="font-display text-xl tabular-nums">
                                    {formatBRL(p.amount)}
                                </span>
                                {p.proofSentAt && (
                                    <span className="text-xs text-muted-foreground">
                                        Comprovante:{" "}
                                        {formatDateTime(p.proofSentAt)}
                                    </span>
                                )}
                            </div>
                            {p.rejectionReason && (
                                <div className="mt-2 text-sm border-l-4 border-status-rejected pl-3 text-foreground">
                                    <span className="font-bold">Motivo:</span>{" "}
                                    {p.rejectionReason}
                                </div>
                            )}
                        </div>
                    </div>

                    {(p.status === "proof_sent" ||
                        (p.status === "validated" && p.proofUrl) ||
                        p.status === "rejected") && (
                        <div className="flex flex-wrap gap-2 pt-2 border-t-2 border-dashed border-foreground/20">
                            {p.proofUrl && onViewProof && (
                                <button
                                    onClick={() => onViewProof(p)}
                                    className="inline-flex items-center gap-1.5 text-sm font-bold border-2 border-foreground bg-card px-3 py-1.5 rounded-lg brutal-press brutal-press-sm"
                                >
                                    <Eye className="size-4" /> Ver comprovante
                                </button>
                            )}
                            {p.status === "proof_sent" && onApprove && (
                                <button
                                    onClick={() => onApprove(p)}
                                    className="inline-flex items-center gap-1.5 text-sm font-bold border-2 border-foreground bg-status-paid text-status-paid-fg px-3 py-1.5 rounded-lg brutal-press brutal-press-sm"
                                >
                                    <Check className="size-4" /> Aprovar
                                </button>
                            )}
                            {p.status === "proof_sent" && onReject && (
                                <button
                                    onClick={() => onReject(p)}
                                    className="inline-flex items-center gap-1.5 text-sm font-bold border-2 border-foreground bg-status-rejected text-status-rejected-fg px-3 py-1.5 rounded-lg brutal-press brutal-press-sm"
                                >
                                    <X className="size-4" /> Rejeitar
                                </button>
                            )}
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}
