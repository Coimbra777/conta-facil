import { useRef, useState } from "react";
import { Upload, FileCheck2, X } from "lucide-react";
import { PROOF_UPLOAD_AUTODELETE_NOTICE } from "@/lib/closedExpenseCopy";

interface Props {
    onSubmit: (file: File) => Promise<void> | void;
    submitting?: boolean;
}

export function ProofUpload({ onSubmit, submitting }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [dragOver, setDragOver] = useState(false);

    const pick = () => inputRef.current?.click();

    const onFile = (f: File | null) => {
        if (!f) return;
        // Alinhado ao backend: SubmitPublicProofRequest `max:5120` (~5MB em KB).
        if (f.size > 5 * 1024 * 1024) {
            alert("Arquivo grande demais (máx. 5MB).");
            return;
        }
        setFile(f);
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="rounded-xl border-2 border-dashed border-foreground/35 bg-muted/40 px-4 py-3 text-sm leading-snug text-foreground">
                {PROOF_UPLOAD_AUTODELETE_NOTICE}
            </div>
            <div
                onClick={pick}
                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                    onFile(e.dataTransfer.files?.[0] ?? null);
                }}
                className={`border-4 border-dashed rounded-2xl p-6 sm:p-8 text-center cursor-pointer transition-colors ${
                    dragOver ? "border-accent bg-accent/10" : "border-foreground bg-card"
                }`}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/*,application/pdf"
                    className="hidden"
                    onChange={(e) => onFile(e.target.files?.[0] ?? null)}
                />
                {file ? (
                    <div className="flex items-center justify-center gap-3 font-bold">
                        <FileCheck2 className="size-6 text-accent" />
                        <span className="truncate max-w-[60vw]">{file.name}</span>
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); setFile(null); }}
                            className="ml-2 border-2 border-foreground rounded-full p-1 bg-card brutal-press brutal-press-sm"
                            aria-label="Remover arquivo"
                        >
                            <X className="size-3" />
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-2">
                        <div className="size-12 rounded-xl border-4 border-foreground bg-arcade-cyan flex items-center justify-center brutal-shadow-sm">
                            <Upload className="size-5" />
                        </div>
                        <p className="font-bold mt-2">Toque para selecionar o comprovante</p>
                        <p className="text-sm text-muted-foreground">Imagem ou PDF · até 5MB</p>
                    </div>
                )}
            </div>

            <button
                type="button"
                disabled={!file || submitting}
                onClick={() => file && onSubmit(file)}
                className="w-full bg-foreground text-background font-black uppercase tracking-wider text-lg py-4 rounded-xl border-4 border-foreground brutal-press brutal-press-md disabled:opacity-50 disabled:active:translate-x-0 disabled:active:translate-y-0"
            >
                {submitting ? "Enviando..." : "Enviar comprovante"}
            </button>
        </div>
    );
}
