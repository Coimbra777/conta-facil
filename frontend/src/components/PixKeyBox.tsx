import { CopyButton } from "./CopyButton";
import { PIX_KEY_LABEL, type PixKeyType } from "@/lib/types";

interface Props {
    pixKey: string;
    pixKeyType: PixKeyType;
    receiverName: string;
}

export function PixKeyBox({ pixKey, pixKeyType, receiverName }: Props) {
    return (
        <div className="border-4 border-foreground rounded-2xl bg-card p-5 sm:p-6 brutal-shadow-sm flex flex-col gap-4">
            <div className="flex items-center justify-between gap-3">
                <span className="text-xs font-bold uppercase tracking-widest text-muted-foreground">
                    Chave Pix · {PIX_KEY_LABEL[pixKeyType]}
                </span>
                <span className="size-2 rounded-full bg-accent animate-pulse-dot" />
            </div>

            <div className="font-display text-xl sm:text-2xl break-all leading-tight">
                {pixKey}
            </div>

            <div className="text-sm">
                <span className="text-muted-foreground">Recebedor: </span>
                <span className="font-bold">{receiverName}</span>
            </div>

            <CopyButton
                value={pixKey}
                label="Copiar chave Pix"
                className="w-full text-base py-4"
            />
        </div>
    );
}
