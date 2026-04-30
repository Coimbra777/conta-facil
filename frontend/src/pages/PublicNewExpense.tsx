/**
 * Fluxo completo de criação de cobrança sem cadastro (UI).
 * Em standby no produto: a rota SPA usa {@link ./PublicCreateExpenseStandby.tsx}; o backend retorna 410 em POST /api/public/expenses.
 * Mantido para reativação futura sem reimplementar formulário e estado.
 */
import { useState } from "react";
import { Link } from "react-router-dom";
import { ApiClientError, api } from "@/lib/api/client";
import { CopyButton } from "@/components/CopyButton";
import {
    buildPublicLink,
    buildPublicManageLink,
    parseManageTokenFromManageUrl,
} from "@/lib/format";
import { CurrencyBrInput } from "@/components/CurrencyBrInput";
import {
    digitsOnly,
    formatBrazilPhoneDisplay,
    GENERIC_BRAZIL_PHONE_PLACEHOLDER,
    parseMoneyInput,
} from "@/lib/inputMasks";
import { setPublicManageToken } from "@/lib/publicManageToken";
import { ArrowLeft, Plus } from "lucide-react";

type DraftRow = { name: string; phone: string };

function emptyRow(): DraftRow {
    return { name: "", phone: "" };
}

export default function PublicNewExpense() {
    const [step, setStep] = useState<"form" | "done">("form");
    const [ownerName, setOwnerName] = useState("");
    const [ownerPhone, setOwnerPhone] = useState("");
    const [description, setDescription] = useState("");
    const [amountStr, setAmountStr] = useState("");
    const [pixKey, setPixKey] = useState("");
    const [dueDate, setDueDate] = useState(
        () => new Date().toISOString().slice(0, 10),
    );
    const [participants, setParticipants] = useState<DraftRow[]>([
        emptyRow(),
        emptyRow(),
    ]);
    const [includeSelf, setIncludeSelf] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [created, setCreated] = useState<{
        publicHash: string;
        participantUrl: string;
        manageUrl: string;
        manageToken: string;
    } | null>(null);

    const ownerDigits = digitsOnly(ownerPhone);

    const canSubmit =
        ownerName.trim().length > 0 &&
        ownerDigits.length >= 10 &&
        description.trim().length > 0 &&
        pixKey.trim().length > 0 &&
        parseMoneyInput(amountStr) >= 1 &&
        participants.some(
            (p) =>
                p.name.trim().length > 0 &&
                digitsOnly(p.phone).length >= 10,
        );

    const submit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        if (!canSubmit) return;
        const amt = parseMoneyInput(amountStr);
        const plist = participants
            .filter(
                (p) =>
                    p.name.trim().length > 0 &&
                    digitsOnly(p.phone).length >= 10,
            )
            .map((p) => ({
                name: p.name.trim(),
                phone: digitsOnly(p.phone),
            }));
        if (plist.length < 1) {
            setError("Informe ao menos um participante com nome e telefone.");
            return;
        }

        setSubmitting(true);
        try {
            const out = await api.createPublicExpense({
                ownerName: ownerName.trim(),
                ownerPhone: ownerDigits,
                description: description.trim(),
                amount: amt,
                pixKey: pixKey.trim(),
                dueDate,
                participants: plist,
                includeOwnerAsParticipant: includeSelf,
            });
            const token =
                out.manageToken ||
                parseManageTokenFromManageUrl(out.manageUrl) ||
                "";
            if (token) setPublicManageToken(out.publicHash, token);
            setCreated({
                publicHash: out.publicHash,
                participantUrl: out.participantUrl,
                manageUrl: out.manageUrl,
                manageToken: out.manageToken,
            });
            setStep("done");
        } catch (err) {
            const msg =
                err instanceof ApiClientError
                    ? err.message
                    : "Não foi possível criar a cobrança. Tente novamente.";
            setError(msg);
        } finally {
            setSubmitting(false);
        }
    };

    if (step === "done" && created) {
        const localParticipantLink = buildPublicLink(created.publicHash);

        return (
            <div className="min-h-dvh bg-background text-foreground">
                <header className="border-b-4 border-foreground px-5 py-4 flex items-center gap-4">
                    <Link
                        to="/"
                        className="inline-flex items-center gap-2 font-bold text-sm underline-offset-4 hover:underline"
                    >
                        <ArrowLeft className="size-4" /> Início
                    </Link>
                </header>
                <main className="max-w-lg mx-auto px-5 py-10 flex flex-col gap-6">
                    <div className="border-4 border-foreground bg-arcade-green rounded-3xl brutal-shadow-lg p-6">
                        <h1 className="font-display text-3xl uppercase leading-tight">
                            Cobrança criada (sem conta)
                        </h1>
                        <p className="mt-3 font-medium text-sm leading-snug opacity-90">
                            Envie o <strong>link para participantes</strong> para
                            quem vai pagar. Guarde o{" "}
                            <strong>link de gerenciamento</strong> em lugar seguro
                            para validar comprovantes — não há recuperação automática
                            neste MVP se você perder esse link.
                        </p>
                    </div>

                    <section className="border-4 border-foreground bg-card rounded-2xl brutal-shadow p-5 flex flex-col gap-3">
                        <h2 className="font-display text-lg uppercase">
                            Link para participantes
                        </h2>
                        <p className="text-xs text-muted-foreground leading-snug">
                            Sem token administrativo; pode ir para grupo ou stories.
                        </p>
                        <div className="break-all text-sm font-medium">
                            {created.participantUrl || localParticipantLink}
                        </div>
                        <CopyButton
                            value={created.participantUrl || localParticipantLink}
                            label="Copiar link para participantes"
                            className="w-full py-4 text-base"
                        />
                    </section>

                    <section className="border-4 border-dashed border-foreground/60 bg-muted/40 rounded-2xl p-5 flex flex-col gap-3">
                        <h2 className="font-display text-lg uppercase">
                            Link de gerenciamento
                        </h2>
                        <p className="text-xs font-bold text-muted-foreground leading-snug">
                            Permite aprovar ou rejeitar comprovantes. Trate como
                            senha; não publique junto do link dos participantes.
                        </p>
                        {(created.manageUrl ||
                            (created.manageToken
                                ? buildPublicManageLink(
                                      created.publicHash,
                                      created.manageToken,
                                  )
                                : "")) && (
                            <>
                                <div className="break-all text-sm font-medium">
                                    {created.manageUrl ||
                                        buildPublicManageLink(
                                            created.publicHash,
                                            created.manageToken,
                                        )}
                                </div>
                                <CopyButton
                                    value={
                                        created.manageUrl ||
                                        buildPublicManageLink(
                                            created.publicHash,
                                            created.manageToken,
                                        )
                                    }
                                    label="Copiar link de gerenciamento"
                                    variant="ghost"
                                    className="w-full py-4 text-base border-4 border-foreground"
                                />
                            </>
                        )}
                    </section>

                    <Link
                        to={`/p/${created.publicHash}`}
                        className="inline-flex justify-center items-center gap-2 bg-foreground text-background border-4 border-foreground px-5 py-4 rounded-xl font-black uppercase brutal-press brutal-press-md text-center"
                    >
                        Abrir página da cobrança (organizador)
                    </Link>
                </main>
            </div>
        );
    }

    return (
        <div className="min-h-dvh bg-background text-foreground">
            <header className="border-b-4 border-foreground px-5 py-4 flex items-center justify-between gap-4">
                <Link
                    to="/"
                    className="inline-flex items-center gap-2 font-bold text-sm underline-offset-4 hover:underline"
                >
                    <ArrowLeft className="size-4" /> Início
                </Link>
                <Link
                    to="/cadastro"
                    className="text-sm font-bold underline-offset-4 hover:underline"
                >
                    Criar conta completa
                </Link>
            </header>

            <main className="max-w-xl mx-auto px-5 py-8 flex flex-col gap-8">
                <div>
                    <h1 className="font-display text-4xl uppercase leading-tight">
                        Cobrança sem cadastro
                    </h1>
                    <p className="mt-3 text-muted-foreground font-medium leading-snug">
                        Ideal para testar rápido: você recebe dois links (participantes
                        e gestão). Para histórico e painel completo,{" "}
                        <Link to="/cadastro" className="underline font-bold">
                            crie uma conta
                        </Link>
                        .
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="border-4 border-foreground bg-card rounded-2xl brutal-shadow p-6 flex flex-col gap-5"
                >
                    <label className="flex flex-col gap-1.5">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Seu nome (organizador)
                        </span>
                        <input
                            className="brutal-input"
                            value={ownerName}
                            onChange={(e) => setOwnerName(e.target.value)}
                            required
                        />
                    </label>
                    <label className="flex flex-col gap-1.5">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Seu WhatsApp (só números)
                        </span>
                        <input
                            className="brutal-input"
                            inputMode="tel"
                            value={ownerPhone}
                            onChange={(e) =>
                                setOwnerPhone(
                                    formatBrazilPhoneDisplay(e.target.value),
                                )
                            }
                            placeholder={GENERIC_BRAZIL_PHONE_PLACEHOLDER}
                            required
                        />
                    </label>
                    <label className="flex flex-col gap-1.5">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Descrição
                        </span>
                        <input
                            className="brutal-input"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="Ex.: Churras de sábado"
                            required
                        />
                    </label>
                    <label className="flex flex-col gap-1.5">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Valor total (R$)
                        </span>
                        <CurrencyBrInput
                            value={amountStr}
                            onValueChange={setAmountStr}
                        />
                    </label>
                    <label className="flex flex-col gap-1.5">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Chave Pix
                        </span>
                        <input
                            className="brutal-input"
                            value={pixKey}
                            onChange={(e) => setPixKey(e.target.value)}
                            required
                        />
                    </label>
                    <label className="flex flex-col gap-1.5">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Vencimento (informático — não bloqueia fluxos no MVP)
                        </span>
                        <input
                            type="date"
                            className="brutal-input"
                            value={dueDate}
                            min={new Date().toISOString().slice(0, 10)}
                            onChange={(e) => setDueDate(e.target.value)}
                            required
                        />
                    </label>

                    <div className="flex flex-col gap-3 border-t-4 border-dashed border-foreground/20 pt-5">
                        <span className="text-xs font-bold uppercase tracking-widest">
                            Participantes
                        </span>
                        <label className="flex items-start gap-3 text-sm font-medium cursor-pointer">
                            <input
                                type="checkbox"
                                className="mt-1 size-4"
                                checked={includeSelf}
                                onChange={(e) =>
                                    setIncludeSelf(e.target.checked)
                                }
                            />
                            <span>
                                Incluir-me na divisão (usa nome e telefone acima;
                                rateio igual entre todos).
                            </span>
                        </label>
                        {participants.map((row, i) => (
                            <div
                                key={i}
                                className="grid sm:grid-cols-2 gap-3 items-end"
                            >
                                <label className="flex flex-col gap-1">
                                    <span className="text-[10px] font-bold uppercase tracking-wider opacity-70">
                                        Nome
                                    </span>
                                    <input
                                        className="brutal-input"
                                        value={row.name}
                                        onChange={(e) =>
                                            setParticipants((prev) =>
                                                prev.map((r, j) =>
                                                    j === i
                                                        ? {
                                                              ...r,
                                                              name: e.target
                                                                  .value,
                                                          }
                                                        : r,
                                                ),
                                            )
                                        }
                                    />
                                </label>
                                <label className="flex flex-col gap-1">
                                    <span className="text-[10px] font-bold uppercase tracking-wider opacity-70">
                                        Telefone
                                    </span>
                                    <input
                                        className="brutal-input"
                                        inputMode="tel"
                                        value={row.phone}
                                        onChange={(e) =>
                                            setParticipants((prev) =>
                                                prev.map((r, j) =>
                                                    j === i
                                                        ? {
                                                              ...r,
                                                              phone:
                                                                  formatBrazilPhoneDisplay(
                                                                      e.target
                                                                          .value,
                                                                  ),
                                                          }
                                                        : r,
                                                ),
                                            )
                                        }
                                        placeholder={
                                            GENERIC_BRAZIL_PHONE_PLACEHOLDER
                                        }
                                    />
                                </label>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={() =>
                                setParticipants((p) => [...p, emptyRow()])
                            }
                            className="inline-flex items-center gap-2 self-start font-bold border-4 border-foreground bg-muted px-4 py-2 rounded-xl brutal-press brutal-press-sm"
                        >
                            <Plus className="size-4" /> Linha de participante
                        </button>
                    </div>

                    {error && (
                        <div
                            role="alert"
                            className="rounded-xl border-4 border-status-rejected bg-status-rejected/15 px-4 py-3 text-sm font-medium text-foreground"
                        >
                            {error}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={!canSubmit || submitting}
                        className="bg-accent text-accent-foreground border-4 border-foreground py-4 rounded-xl font-black uppercase tracking-wider brutal-press brutal-press-md disabled:opacity-50 min-h-[52px]"
                    >
                        {submitting ? "Criando…" : "Gerar links"}
                    </button>
                </form>

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
            </main>
        </div>
    );
}
