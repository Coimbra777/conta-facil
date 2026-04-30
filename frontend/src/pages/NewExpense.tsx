import { useMemo, useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { AppShell } from "@/components/AppShell";
import { api } from "@/lib/api/client";
import { useAuth } from "@/lib/auth";
import { PIX_KEY_LABEL, type PixKeyType } from "@/lib/types";
import { formatBRL } from "@/lib/format";
import {
    digitsOnly,
    formatBrazilPhoneDisplay,
    parseMoneyInput,
} from "@/lib/inputMasks";
import {
    buildParticipantsPayloadForApi,
    decimalToBrAmountInput,
    splitTotalEquallyInReais,
    validateExpenseParticipantsPayload,
} from "@/lib/splitAmountEqually";
import { CurrencyBrInput } from "@/components/CurrencyBrInput";
import { Plus, Trash2, Wand2 } from "lucide-react";

interface DraftParticipant {
    id: string;
    name: string;
    phone: string;
    amount: string; // string to allow controlled input
}

const newP = (): DraftParticipant => ({
    id: Math.random().toString(36).slice(2),
    name: "",
    phone: "",
    amount: "",
});

const OWNER_ROW_PREFIX = "owner-self";

function differenceMessage(diff: number): string {
    if (Math.abs(diff) < 0.001) return "Os valores estão balanceados.";
    if (diff > 0) {
        return `Faltam ${formatBRL(diff)} para fechar o total da cobrança.`;
    }

    return `Há ${formatBRL(Math.abs(diff))} excedente. Esse valor ficará em caixa.`;
}

export default function NewExpense() {
    const nav = useNavigate();
    const { user } = useAuth();
    const [step, setStep] = useState(1);

    // Etapa 1
    const [title, setTitle] = useState("");
    const [description, setDescription] = useState("");
    const [totalAmount, setTotalAmount] = useState("");
    const [dueDate, setDueDate] = useState("");

    // Etapa 2
    const [pixKeyType, setPixKeyType] = useState<PixKeyType>("email");
    const [pixKey, setPixKey] = useState("");
    const [pixReceiverName, setPixReceiverName] = useState("");

    // Etapa 3
    const [participants, setParticipants] = useState<DraftParticipant[]>([newP(), newP()]);
    const [submitting, setSubmitting] = useState(false);
    const [includeSelf, setIncludeSelf] = useState(false);

    useEffect(() => {
        if (includeSelf && user?.name) {
            setParticipants((rows) => {
                if (rows.some((r) => r.id.startsWith(OWNER_ROW_PREFIX)))
                    return rows;
                const ownerPhone = user.phone
                    ? formatBrazilPhoneDisplay(user.phone)
                    : "";
                return [
                    {
                        id: `${OWNER_ROW_PREFIX}-${Math.random().toString(36).slice(2)}`,
                        name: user.name,
                        phone: ownerPhone,
                        amount: "",
                    },
                    ...rows,
                ];
            });
        } else if (!includeSelf) {
            setParticipants((rows) =>
                rows.filter((r) => !r.id.startsWith(OWNER_ROW_PREFIX)),
            );
        }
    }, [includeSelf, user?.id, user?.name, user?.phone]);

    const totalNum = parseMoneyInput(totalAmount);
    const totalCents = Math.round(totalNum * 100);
    const distributedCents = useMemo(
        () =>
            participants.reduce(
                (s, p) => s + Math.round(parseMoneyInput(p.amount) * 100),
                0,
            ),
        [participants],
    );
    const distributed = distributedCents / 100;
    const diff = (totalCents - distributedCents) / 100;

    const splitEqually = () => {
        if (totalNum <= 0 || participants.length === 0) return;
        const amounts = splitTotalEquallyInReais(totalNum, participants.length);
        setParticipants((arr) =>
            arr.map((p, i) => ({
                ...p,
                amount: decimalToBrAmountInput(amounts[i] ?? 0),
            })),
        );
    };

    const updateP = (id: string, patch: Partial<DraftParticipant>) =>
        setParticipants((arr) => arr.map((p) => (p.id === id ? { ...p, ...patch } : p)));
    const removeP = (id: string) => {
        if (id.startsWith(OWNER_ROW_PREFIX)) setIncludeSelf(false);
        setParticipants((arr) => arr.filter((p) => p.id !== id));
    };

    const canStep1 = title.trim().length >= 2 && totalNum > 0;
    const canStep2 = pixKey.trim().length > 0 && pixReceiverName.trim().length > 0;
    const canStep3 =
        participants.length > 0 &&
        Math.abs(totalCents - distributedCents) <= 2 &&
        validateExpenseParticipantsPayload(participants, totalNum).ok;

    const submit = async () => {
        if (!canStep1 || !canStep2) return;
        const validation = validateExpenseParticipantsPayload(
            participants,
            totalNum,
        );
        if (validation.ok === false) {
            alert(validation.message);
            return;
        }
        setSubmitting(true);
        try {
            const exp = await api.createExpense({
                title: title.trim(),
                description: description.trim() || undefined,
                totalAmount: totalNum,
                dueDate: dueDate ? new Date(dueDate).toISOString() : undefined,
                pixKeyType,
                pixKey: pixKey.trim(),
                pixReceiverName: pixReceiverName.trim(),
                participants: buildParticipantsPayloadForApi(participants),
            });
            nav(`/cobrancas/${exp.id}/sucesso`);
        } catch (e: unknown) {
            alert(
                e instanceof Error
                    ? e.message
                    : "Não foi possível criar a cobrança.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AppShell>
            <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 grid lg:grid-cols-[1fr_320px] gap-8">
                <div>
                    <h1 className="font-display text-3xl sm:text-4xl uppercase mb-1">Nova cobrança</h1>
                    <p className="text-muted-foreground mb-6">Preencha em 3 etapas e gere o link compartilhável.</p>

                    <Stepper step={step} />

                    <div className="mt-6 border-4 border-foreground bg-card rounded-2xl brutal-shadow-sm p-5 sm:p-6">
                        {step === 1 && (
                            <div className="flex flex-col gap-4">
                                <Field label="Título">
                                    <input className="brutal-input" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ex: Churrasco de domingo" />
                                </Field>
                                <Field label="Descrição (opcional)">
                                    <textarea className="brutal-input" rows={3} value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Detalhes do que está sendo cobrado." />
                                </Field>
                                <div className="grid sm:grid-cols-2 gap-4">
                                    <Field label="Valor total (R$)">
                                        <CurrencyBrInput
                                            value={totalAmount}
                                            onValueChange={setTotalAmount}
                                        />
                                    </Field>
                                    <Field label="Data limite (opcional)">
                                        <input className="brutal-input" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
                                    </Field>
                                </div>
                            </div>
                        )}

                        {step === 2 && (
                            <div className="flex flex-col gap-4">
                                <Field label="Tipo da chave Pix">
                                    <select className="brutal-input" value={pixKeyType} onChange={(e) => setPixKeyType(e.target.value as PixKeyType)}>
                                        {(Object.keys(PIX_KEY_LABEL) as PixKeyType[]).map((k) => (
                                            <option key={k} value={k}>{PIX_KEY_LABEL[k]}</option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Chave Pix">
                                    <input className="brutal-input" value={pixKey} onChange={(e) => setPixKey(e.target.value)} placeholder="Sua chave Pix" />
                                </Field>
                                <Field label="Nome do recebedor">
                                    <input className="brutal-input" value={pixReceiverName} onChange={(e) => setPixReceiverName(e.target.value)} placeholder="Quem vai aparecer no Pix" />
                                </Field>
                            </div>
                        )}

                        {step === 3 && (
                            <div className="flex flex-col gap-4">
                                <label className="flex items-start gap-3 cursor-pointer font-bold text-sm border-4 border-foreground rounded-xl p-3 bg-background">
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-4"
                                        checked={includeSelf}
                                        onChange={(e) => setIncludeSelf(e.target.checked)}
                                    />
                                    <span>Incluir meu nome na divisão (uso o mesmo nome e telefone da conta; ajuste os valores para somarem ao total).</span>
                                </label>

                                <div className="flex items-center justify-between gap-3 flex-wrap">
                                    <span className="text-xs font-bold uppercase tracking-widest text-muted-foreground">Participantes</span>
                                    <button
                                        type="button"
                                        onClick={splitEqually}
                                        className="inline-flex items-center gap-1.5 text-sm font-bold border-2 border-foreground bg-arcade-yellow px-3 py-1.5 rounded-lg brutal-press brutal-press-sm"
                                    >
                                        <Wand2 className="size-4" /> Dividir igualmente
                                    </button>
                                </div>

                                <ul className="flex flex-col gap-3">
                                    {participants.map((p, i) => (
                                        <li key={p.id} className="border-4 border-foreground rounded-xl p-3 flex flex-col sm:flex-row gap-3 items-stretch sm:items-end bg-background">
                                            <div className="flex-1 grid sm:grid-cols-3 gap-3 w-full">
                                                <Field label={`Nome ${i + 1}`}>
                                                    <input className="brutal-input" value={p.name} onChange={(e) => updateP(p.id, { name: e.target.value })} placeholder="Nome" />
                                                </Field>
                                                <Field label="Telefone">
                                                    <input
                                                        className="brutal-input"
                                                        value={p.phone}
                                                        onChange={(e) =>
                                                            updateP(p.id, {
                                                                phone: formatBrazilPhoneDisplay(
                                                                    e.target.value,
                                                                ),
                                                            })
                                                        }
                                                        placeholder="(11) 98765-4321"
                                                        autoComplete="tel"
                                                    />
                                                </Field>
                                                <Field label="Valor (R$)">
                                                    <CurrencyBrInput
                                                        value={p.amount}
                                                        onValueChange={(v) =>
                                                            updateP(p.id, {
                                                                amount: v,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => removeP(p.id)}
                                                className="border-2 border-foreground bg-card p-2 rounded-lg brutal-press brutal-press-sm self-end"
                                                aria-label="Remover"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    type="button"
                                    onClick={() => setParticipants((arr) => [...arr, newP()])}
                                    className="inline-flex items-center gap-2 self-start font-bold border-4 border-foreground bg-card px-4 py-2 rounded-xl brutal-press brutal-press-sm"
                                >
                                    <Plus className="size-4" /> Adicionar participante
                                </button>
                            </div>
                        )}

                        <div className="flex items-center justify-between gap-3 mt-6 pt-5 border-t-4 border-dashed border-foreground/20">
                            <button
                                type="button"
                                disabled={step === 1}
                                onClick={() => setStep((s) => Math.max(1, s - 1))}
                                className="border-4 border-foreground bg-card px-4 py-2 rounded-xl font-bold brutal-press brutal-press-sm disabled:opacity-40"
                            >
                                Voltar
                            </button>
                            {step < 3 ? (
                                <button
                                    type="button"
                                    disabled={(step === 1 && !canStep1) || (step === 2 && !canStep2)}
                                    onClick={() => setStep((s) => s + 1)}
                                    className="bg-foreground text-background border-4 border-foreground px-5 py-2 rounded-xl font-black uppercase brutal-press brutal-press-sm disabled:opacity-50"
                                >
                                    Continuar
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    disabled={!canStep3 || submitting}
                                    onClick={submit}
                                    className="bg-accent text-accent-foreground border-4 border-foreground px-5 py-3 rounded-xl font-black uppercase brutal-press brutal-press-md disabled:opacity-50"
                                >
                                    {submitting ? "Gerando..." : "Criar cobrança e gerar link"}
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Resumo lateral */}
                <aside className="lg:sticky lg:top-24 h-max border-4 border-foreground bg-arcade-cyan rounded-2xl brutal-shadow p-5 flex flex-col gap-3">
                    <h3 className="font-display text-xl uppercase">Resumo</h3>
                    <Row label="Total" value={formatBRL(totalNum)} />
                    <Row label="Distribuído" value={formatBRL(distributed)} />
                    <Row
                        label="Diferença"
                        value={formatBRL(diff)}
                        highlight={Math.abs(diff) > 0.001 ? (diff > 0 ? "warn" : "error") : "ok"}
                    />
                    <Row label="Participantes" value={participants.length} />
                    <p
                        className="rounded-xl border-2 border-foreground/70 bg-background/70 px-3 py-3 text-sm font-medium leading-snug text-foreground"
                        role="status"
                    >
                        {differenceMessage(diff)}
                    </p>
                    <p className="text-xs font-medium text-foreground/70">
                        A soma dos valores dos participantes deve ser igual ao total. Enquanto houver diferença, não é possível concluir.
                    </p>
                </aside>
            </div>

            <style>{`
                .brutal-input {
                    width: 100%;
                    border: 4px solid hsl(var(--foreground));
                    background: hsl(var(--background));
                    border-radius: 0.75rem;
                    padding: 0.65rem 0.85rem;
                    font-weight: 500;
                    outline: none;
                }
                .brutal-input:focus { box-shadow: 4px 4px 0 0 hsl(var(--accent)); }
            `}</style>
        </AppShell>
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

function Row({ label, value, highlight }: { label: string; value: React.ReactNode; highlight?: "ok" | "warn" | "error" }) {
    const tone =
        highlight === "ok" ? "text-foreground" :
        highlight === "warn" ? "text-foreground" :
        highlight === "error" ? "text-status-rejected-fg" : "";
    const bg =
        highlight === "ok" ? "bg-status-paid" :
        highlight === "warn" ? "bg-status-pending" :
        highlight === "error" ? "bg-status-rejected" : "bg-card";
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-bold uppercase tracking-wider opacity-80">{label}</span>
            <span className={`font-display tabular-nums px-3 py-1 rounded-lg border-2 border-foreground ${bg} ${tone}`}>{value}</span>
        </div>
    );
}

function Stepper({ step }: { step: number }) {
    const steps = ["Dados", "Pix", "Participantes"];
    return (
        <ol className="flex gap-2">
            {steps.map((s, i) => {
                const n = i + 1;
                const active = n === step;
                const done = n < step;
                return (
                    <li
                        key={s}
                        className={`flex-1 border-4 border-foreground rounded-xl px-3 py-2 text-sm font-bold flex items-center gap-2 ${
                            active ? "bg-arcade-pink text-primary-foreground" : done ? "bg-arcade-green" : "bg-card"
                        }`}
                    >
                        <span className="size-6 rounded-full border-2 border-foreground bg-card text-foreground grid place-items-center text-xs font-black">{n}</span>
                        {s}
                    </li>
                );
            })}
        </ol>
    );
}
