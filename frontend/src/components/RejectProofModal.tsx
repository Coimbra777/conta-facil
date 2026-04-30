import { useState } from "react";
import { ModalShell } from "./ModalShell";

interface Props {
    open: boolean;
    participantName?: string;
    onClose: () => void;
    onConfirm: (reason: string) => Promise<void> | void;
}

export function RejectProofModal({ open, participantName, onClose, onConfirm }: Props) {
    const [reason, setReason] = useState("");
    const [submitting, setSubmitting] = useState(false);

    const submit = async () => {
        if (!reason.trim()) return;
        setSubmitting(true);
        try {
            await onConfirm(reason.trim());
            setReason("");
            onClose();
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <ModalShell open={open} title="Rejeitar comprovante" onClose={onClose}>
            <div className="flex flex-col gap-4">
                {participantName && (
                    <p className="text-sm text-muted-foreground">
                        Você está rejeitando o pagamento de <span className="font-bold text-foreground">{participantName}</span>.
                        Conte o motivo para que ela(e) possa reenviar.
                    </p>
                )}
                <textarea
                    value={reason}
                    onChange={(e) => setReason(e.target.value.slice(0, 2000))}
                    rows={4}
                    placeholder="Ex: Comprovante ilegível, valor diferente, etc."
                    className="w-full border-4 border-foreground rounded-xl p-3 font-medium bg-background focus:outline-none focus:ring-4 focus:ring-accent"
                />
                <div className="text-xs text-muted-foreground text-right">{reason.length}/2000</div>
                <div className="flex gap-3">
                    <button
                        onClick={onClose}
                        className="flex-1 border-4 border-foreground bg-card px-4 py-3 rounded-xl font-bold brutal-press brutal-press-sm"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={submit}
                        disabled={!reason.trim() || submitting}
                        className="flex-1 border-4 border-foreground bg-status-rejected text-status-rejected-fg px-4 py-3 rounded-xl font-black uppercase brutal-press brutal-press-sm disabled:opacity-50"
                    >
                        {submitting ? "..." : "Rejeitar"}
                    </button>
                </div>
            </div>
        </ModalShell>
    );
}
