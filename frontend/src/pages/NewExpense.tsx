import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { AppShell } from "@/components/AppShell";
import { ApiClientError, api } from "@/lib/api/client";
import { useAuth } from "@/lib/auth";
import { PIX_KEY_LABEL, type PixKeyType } from "@/lib/types";
import { formatBRL } from "@/lib/format";
import {
    BRAZIL_PHONE_ERROR_MESSAGE,
    digitsOnly,
    formatBrazilPhoneDisplay,
    GENERIC_BRAZIL_PHONE_PLACEHOLDER,
    isValidBrazilPhone,
    parseMoneyInput,
} from "@/lib/inputMasks";
import {
    buildParticipantsPayloadForApi,
    decimalToBrAmountInput,
    splitTotalEquallyInReais,
    type ParticipantDraftRowErrors,
    validateExpenseParticipantsPayload,
} from "@/lib/splitAmountEqually";
import { CurrencyBrInput } from "@/components/CurrencyBrInput";
import { Plus, Trash2, Wand2 } from "lucide-react";

interface DraftParticipant {
    id: string;
    name: string;
    phone: string;
    amount: string;
}

type FormErrors = {
    global?: string;
    title?: string;
    totalAmount?: string;
    dueDate?: string;
    pixKey?: string;
    participants?: string;
    participantErrors: Record<string, ParticipantDraftRowErrors>;
};

type ParticipantFieldName = keyof ParticipantDraftRowErrors;

const OWNER_ROW_PREFIX = "owner-self";

const newP = (): DraftParticipant => ({
    id: Math.random().toString(36).slice(2),
    name: "",
    phone: "",
    amount: "",
});

function createEmptyErrors(): FormErrors {
    return { participantErrors: {} };
}

function todayLocalDateString(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, "0");
    const day = String(today.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

function differenceMessage(diff: number): string {
    if (Math.abs(diff) < 0.001) return "Os valores estão balanceados.";
    if (diff > 0) {
        return `Faltam ${formatBRL(diff)} para fechar o total da cobrança.`;
    }

    return `Há ${formatBRL(Math.abs(diff))} excedente. Esse valor ficará em caixa.`;
}

function validateMainStep(input: {
    title: string;
    totalAmountRaw: string;
    totalAmount: number;
    dueDate: string;
}): FormErrors {
    const errors = createEmptyErrors();

    if (input.title.trim().length === 0) {
        errors.title = "Informe a descrição da cobrança.";
    }
    if (input.totalAmountRaw.trim().length === 0) {
        errors.totalAmount = "Informe o valor total da cobrança.";
    } else if (!Number.isFinite(input.totalAmount) || input.totalAmount <= 0) {
        errors.totalAmount = "O valor total deve ser maior que zero.";
    }
    if (input.dueDate.trim().length === 0) {
        errors.dueDate = "Informe a data de vencimento.";
    } else if (input.dueDate < todayLocalDateString()) {
        errors.dueDate =
            "Informe uma data de vencimento igual ou posterior a hoje.";
    }

    return errors;
}

function validatePixStep(input: { pixKey: string }): FormErrors {
    const errors = createEmptyErrors();

    if (input.pixKey.trim().length === 0) {
        errors.pixKey = "Informe a chave Pix.";
    }

    return errors;
}

function hasAnyErrors(errors: FormErrors): boolean {
    return Boolean(
        errors.global ||
            errors.title ||
            errors.totalAmount ||
            errors.dueDate ||
            errors.pixKey ||
            errors.participants ||
            Object.values(errors.participantErrors).some((participant) =>
                Boolean(participant.name || participant.phone || participant.amount),
            ),
    );
}

function mergeParticipantErrors(
    participants: DraftParticipant[],
    validationErrors: ParticipantDraftRowErrors[],
): Record<string, ParticipantDraftRowErrors> {
    const mapped: Record<string, ParticipantDraftRowErrors> = {};

    validationErrors.forEach((errors, index) => {
        const participant = participants[index];
        if (!participant) return;
        if (errors.name || errors.phone || errors.amount) {
            mapped[participant.id] = errors;
        }
    });

    return mapped;
}

function mapApiErrorsToFormErrors(
    apiError: ApiClientError,
    participants: DraftParticipant[],
): FormErrors {
    const next = createEmptyErrors();
    const rawErrors = apiError.errors ?? {};

    for (const [field, messages] of Object.entries(rawErrors)) {
        const firstMessage = messages[0];
        if (!firstMessage) continue;

        if (field === "description") next.title = firstMessage;
        else if (field === "total_amount") next.totalAmount = firstMessage;
        else if (field === "due_date") next.dueDate = firstMessage;
        else if (field === "pix_key") next.pixKey = firstMessage;
        else if (field === "participants") next.participants = firstMessage;
        else {
            const participantMatch = field.match(
                /^participants\.(\d+)\.(name|phone|amount)$/,
            );
            if (participantMatch) {
                const index = Number(participantMatch[1]);
                const fieldName = participantMatch[2] as ParticipantFieldName;
                const participant = participants[index];
                if (!participant) {
                    next.participants ??= firstMessage;
                    continue;
                }
                next.participantErrors[participant.id] = {
                    ...next.participantErrors[participant.id],
                    [fieldName]: firstMessage,
                };
            }
        }
    }

    next.global = apiError.message;

    return next;
}

function inferStepFromErrors(errors: FormErrors): number {
    if (errors.title || errors.totalAmount || errors.dueDate) return 1;
    if (errors.pixKey) return 2;
    if (errors.participants || Object.keys(errors.participantErrors).length > 0) {
        return 3;
    }
    return 1;
}

function listCurrentStepErrors(step: number, errors: FormErrors): string[] {
    const messages =
        step === 1
            ? [errors.global, errors.title, errors.totalAmount, errors.dueDate]
            : step === 2
              ? [errors.global, errors.pixKey]
              : [
                    errors.global,
                    errors.participants,
                    ...Object.values(errors.participantErrors).flatMap((item) => [
                        item.name,
                        item.phone,
                        item.amount,
                    ]),
                ];

    return [...new Set(messages.filter((value): value is string => Boolean(value)))];
}

function inputClass(hasError: boolean): string {
    return hasError
        ? "brutal-input border-status-rejected-fg bg-status-rejected/20"
        : "brutal-input";
}

export default function NewExpense() {
    const nav = useNavigate();
    const { user } = useAuth();
    const [step, setStep] = useState(1);
    const [title, setTitle] = useState("");
    const [description, setDescription] = useState("");
    const [totalAmount, setTotalAmount] = useState("");
    const [dueDate, setDueDate] = useState("");
    const [pixKeyType, setPixKeyType] = useState<PixKeyType>("email");
    const [pixKey, setPixKey] = useState("");
    const [pixReceiverName, setPixReceiverName] = useState("");
    const [participants, setParticipants] = useState<DraftParticipant[]>([newP(), newP()]);
    const [submitting, setSubmitting] = useState(false);
    const [includeSelf, setIncludeSelf] = useState(false);
    const [touchedPhones, setTouchedPhones] = useState<Record<string, boolean>>({});
    const [formErrors, setFormErrors] = useState<FormErrors>(createEmptyErrors);

    const titleRef = useRef<HTMLInputElement>(null);
    const totalAmountRef = useRef<HTMLInputElement>(null);
    const dueDateRef = useRef<HTMLInputElement>(null);
    const pixKeyRef = useRef<HTMLInputElement>(null);
    const participantRefs = useRef<
        Record<string, Partial<Record<ParticipantFieldName, HTMLInputElement | null>>>
    >({});

    useEffect(() => {
        if (includeSelf && user?.name) {
            setParticipants((rows) => {
                if (rows.some((row) => row.id.startsWith(OWNER_ROW_PREFIX))) {
                    return rows;
                }

                return [
                    {
                        id: `${OWNER_ROW_PREFIX}-${Math.random().toString(36).slice(2)}`,
                        name: user.name,
                        phone: user.phone ? formatBrazilPhoneDisplay(user.phone) : "",
                        amount: "",
                    },
                    ...rows,
                ];
            });
        } else if (!includeSelf) {
            setParticipants((rows) =>
                rows.filter((row) => !row.id.startsWith(OWNER_ROW_PREFIX)),
            );
        }
    }, [includeSelf, user?.id, user?.name, user?.phone]);

    const totalNum = parseMoneyInput(totalAmount);
    const totalCents = Math.round(totalNum * 100);
    const distributedCents = useMemo(
        () =>
            participants.reduce(
                (sum, participant) =>
                    sum + Math.round(parseMoneyInput(participant.amount) * 100),
                0,
            ),
        [participants],
    );
    const distributed = distributedCents / 100;
    const diff = (totalCents - distributedCents) / 100;
    const today = todayLocalDateString();
    const summaryErrors = listCurrentStepErrors(step, formErrors);

    const focusField = (path?: string) => {
        if (!path) return;
        if (path === "title") titleRef.current?.focus();
        else if (path === "totalAmount") totalAmountRef.current?.focus();
        else if (path === "dueDate") dueDateRef.current?.focus();
        else if (path === "pixKey") pixKeyRef.current?.focus();
        else {
            const participantMatch = path.match(/^participants\.(.+)\.(name|phone|amount)$/);
            if (!participantMatch) return;
            const participantId = participantMatch[1];
            const fieldName = participantMatch[2] as ParticipantFieldName;
            participantRefs.current[participantId]?.[fieldName]?.focus();
        }
    };

    const firstErrorPath = (errors: FormErrors): string | undefined => {
        if (errors.title) return "title";
        if (errors.totalAmount) return "totalAmount";
        if (errors.dueDate) return "dueDate";
        if (errors.pixKey) return "pixKey";

        for (const participant of participants) {
            const participantErrors = errors.participantErrors[participant.id];
            if (!participantErrors) continue;
            if (participantErrors.name) return `participants.${participant.id}.name`;
            if (participantErrors.phone) return `participants.${participant.id}.phone`;
            if (participantErrors.amount) return `participants.${participant.id}.amount`;
        }

        return undefined;
    };

    const applyErrors = (errors: FormErrors, nextStep = step) => {
        setFormErrors(errors);
        setStep(nextStep);
        requestAnimationFrame(() => focusField(firstErrorPath(errors)));
    };

    const clearFieldError = (field: keyof Omit<FormErrors, "participantErrors">) => {
        setFormErrors((current) => ({
            ...current,
            [field]: undefined,
            global: undefined,
        }));
    };

    const clearParticipantFieldError = (
        participantId: string,
        field: ParticipantFieldName,
    ) => {
        setFormErrors((current) => ({
            ...current,
            global: undefined,
            participants: undefined,
            participantErrors: {
                ...current.participantErrors,
                [participantId]: {
                    ...current.participantErrors[participantId],
                    [field]: undefined,
                },
            },
        }));
    };

    const validateParticipantsStep = (): FormErrors => {
        const validation = validateExpenseParticipantsPayload(participants, totalNum);
        if (validation.ok) {
            return createEmptyErrors();
        }

        const participantErrors = mergeParticipantErrors(
            participants,
            validation.participantErrors,
        );
        const hasParticipantFieldErrors = Object.keys(participantErrors).length > 0;

        return {
            global: validation.message,
            participants: hasParticipantFieldErrors ? undefined : validation.message,
            participantErrors,
        };
    };

    const validateCurrentStep = (currentStep: number): FormErrors => {
        if (currentStep === 1) {
            return validateMainStep({
                title,
                totalAmountRaw: totalAmount,
                totalAmount: totalNum,
                dueDate,
            });
        }
        if (currentStep === 2) {
            return validatePixStep({ pixKey });
        }

        return validateParticipantsStep();
    };

    const continueStep = () => {
        const errors = validateCurrentStep(step);
        if (hasAnyErrors(errors)) {
            applyErrors(errors, step);
            return;
        }

        setFormErrors(createEmptyErrors());
        setStep((current) => Math.min(3, current + 1));
    };

    const splitEqually = () => {
        if (totalNum <= 0 || participants.length === 0) return;
        const amounts = splitTotalEquallyInReais(totalNum, participants.length);
        setParticipants((rows) =>
            rows.map((participant, index) => ({
                ...participant,
                amount: decimalToBrAmountInput(amounts[index] ?? 0),
            })),
        );
        setFormErrors((current) => ({
            ...current,
            global: undefined,
            participants: undefined,
        }));
    };

    const updateParticipant = (
        id: string,
        patch: Partial<DraftParticipant>,
        fieldToClear?: ParticipantFieldName,
    ) => {
        setParticipants((rows) =>
            rows.map((participant) =>
                participant.id === id ? { ...participant, ...patch } : participant,
            ),
        );
        if (fieldToClear) {
            clearParticipantFieldError(id, fieldToClear);
        }
    };

    const touchPhone = (id: string) =>
        setTouchedPhones((current) => ({ ...current, [id]: true }));

    const livePhoneError = (id: string, phone: string): string | null => {
        const mappedError = formErrors.participantErrors[id]?.phone;
        if (mappedError) return mappedError;
        if (!touchedPhones[id]) return null;
        if (digitsOnly(phone).length === 0 || isValidBrazilPhone(phone)) return null;
        return BRAZIL_PHONE_ERROR_MESSAGE;
    };

    const removeParticipant = (id: string) => {
        if (id.startsWith(OWNER_ROW_PREFIX)) setIncludeSelf(false);
        setParticipants((rows) => rows.filter((participant) => participant.id !== id));
        setFormErrors((current) => {
            const nextParticipantErrors = { ...current.participantErrors };
            delete nextParticipantErrors[id];

            return {
                ...current,
                global: undefined,
                participants: undefined,
                participantErrors: nextParticipantErrors,
            };
        });
    };

    const submit = async () => {
        const step1Errors = validateCurrentStep(1);
        if (hasAnyErrors(step1Errors)) {
            applyErrors(step1Errors, 1);
            return;
        }

        const step2Errors = validateCurrentStep(2);
        if (hasAnyErrors(step2Errors)) {
            applyErrors(step2Errors, 2);
            return;
        }

        const step3Errors = validateCurrentStep(3);
        if (hasAnyErrors(step3Errors)) {
            applyErrors(step3Errors, 3);
            return;
        }

        setSubmitting(true);
        setFormErrors(createEmptyErrors());

        try {
            const expense = await api.createExpense({
                title: title.trim(),
                description: description.trim() || undefined,
                totalAmount: totalNum,
                dueDate: dueDate.trim(),
                pixKeyType,
                pixKey: pixKey.trim(),
                pixReceiverName: pixReceiverName.trim(),
                participants: buildParticipantsPayloadForApi(participants),
            });
            nav(`/cobrancas/${expense.id}/sucesso`);
        } catch (error: unknown) {
            if (error instanceof ApiClientError) {
                const mappedErrors = mapApiErrorsToFormErrors(error, participants);
                applyErrors(mappedErrors, inferStepFromErrors(mappedErrors));
            } else {
                applyErrors(
                    {
                        ...createEmptyErrors(),
                        global: "Não foi possível criar a cobrança.",
                    },
                    step,
                );
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AppShell>
            <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8 grid lg:grid-cols-[1fr_320px] gap-8">
                <div>
                    <h1 className="font-display text-3xl sm:text-4xl uppercase mb-1">
                        Nova cobrança
                    </h1>
                    <p className="text-muted-foreground mb-6">
                        Preencha em 3 etapas e gere o link compartilhável.
                    </p>

                    <Stepper step={step} />

                    <div className="mt-6 border-4 border-foreground bg-card rounded-2xl brutal-shadow-sm p-5 sm:p-6">
                        {summaryErrors.length > 0 && (
                            <section
                                className="mb-5 rounded-2xl border-4 border-status-rejected-fg bg-status-rejected/20 px-4 py-3"
                                role="alert"
                            >
                                <p className="font-black uppercase text-sm">
                                    Revise os campos destacados.
                                </p>
                                <ul className="mt-2 flex flex-col gap-1 text-sm font-medium">
                                    {summaryErrors.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {step === 1 && (
                            <div className="flex flex-col gap-4">
                                <Field label="Título" error={formErrors.title}>
                                    <input
                                        ref={titleRef}
                                        className={inputClass(Boolean(formErrors.title))}
                                        value={title}
                                        onChange={(event) => {
                                            setTitle(event.target.value);
                                            clearFieldError("title");
                                        }}
                                        placeholder="Ex: Churrasco de domingo"
                                        aria-invalid={Boolean(formErrors.title)}
                                    />
                                </Field>

                                <Field label="Descrição (opcional)">
                                    <textarea
                                        className="brutal-input"
                                        rows={3}
                                        value={description}
                                        onChange={(event) => setDescription(event.target.value)}
                                        placeholder="Detalhes do que está sendo cobrado."
                                    />
                                </Field>

                                <div className="grid sm:grid-cols-2 gap-4">
                                    <Field
                                        label="Valor total (R$)"
                                        error={formErrors.totalAmount}
                                    >
                                        <CurrencyBrInput
                                            value={totalAmount}
                                            onValueChange={(value) => {
                                                setTotalAmount(value);
                                                clearFieldError("totalAmount");
                                            }}
                                            inputRef={totalAmountRef}
                                            aria-invalid={Boolean(formErrors.totalAmount)}
                                        />
                                    </Field>

                                    <Field
                                        label="Data de vencimento"
                                        error={formErrors.dueDate}
                                    >
                                        <input
                                            ref={dueDateRef}
                                            className={inputClass(Boolean(formErrors.dueDate))}
                                            type="date"
                                            value={dueDate}
                                            min={today}
                                            onChange={(event) => {
                                                setDueDate(event.target.value);
                                                clearFieldError("dueDate");
                                            }}
                                            aria-invalid={Boolean(formErrors.dueDate)}
                                        />
                                    </Field>
                                </div>
                            </div>
                        )}

                        {step === 2 && (
                            <div className="flex flex-col gap-4">
                                <Field label="Tipo da chave Pix">
                                    <select
                                        className="brutal-input"
                                        value={pixKeyType}
                                        onChange={(event) =>
                                            setPixKeyType(event.target.value as PixKeyType)
                                        }
                                    >
                                        {(Object.keys(PIX_KEY_LABEL) as PixKeyType[]).map((key) => (
                                            <option key={key} value={key}>
                                                {PIX_KEY_LABEL[key]}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <Field label="Chave Pix" error={formErrors.pixKey}>
                                    <input
                                        ref={pixKeyRef}
                                        className={inputClass(Boolean(formErrors.pixKey))}
                                        value={pixKey}
                                        onChange={(event) => {
                                            setPixKey(event.target.value);
                                            clearFieldError("pixKey");
                                        }}
                                        placeholder="Sua chave Pix"
                                        aria-invalid={Boolean(formErrors.pixKey)}
                                    />
                                </Field>

                                <Field label="Nome do recebedor (opcional)">
                                    <input
                                        className="brutal-input"
                                        value={pixReceiverName}
                                        onChange={(event) =>
                                            setPixReceiverName(event.target.value)
                                        }
                                        placeholder="Quem vai aparecer no Pix"
                                    />
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
                                        onChange={(event) =>
                                            setIncludeSelf(event.target.checked)
                                        }
                                    />
                                    <span>
                                        Incluir meu nome na divisão (uso o mesmo nome e telefone da
                                        conta; ajuste os valores para somarem ao total).
                                    </span>
                                </label>

                                <div className="flex items-center justify-between gap-3 flex-wrap">
                                    <span className="text-xs font-bold uppercase tracking-widest text-muted-foreground">
                                        Participantes
                                    </span>
                                    <button
                                        type="button"
                                        onClick={splitEqually}
                                        className="inline-flex items-center gap-1.5 text-sm font-bold border-2 border-foreground bg-arcade-yellow px-3 py-1.5 rounded-lg brutal-press brutal-press-sm"
                                    >
                                        <Wand2 className="size-4" /> Dividir igualmente
                                    </button>
                                </div>

                                {formErrors.participants && (
                                    <p className="text-sm font-medium text-status-rejected-fg">
                                        {formErrors.participants}
                                    </p>
                                )}

                                <ul className="flex flex-col gap-3">
                                    {participants.map((participant, index) => {
                                        const participantErrors =
                                            formErrors.participantErrors[participant.id] ?? {};

                                        return (
                                            <li
                                                key={participant.id}
                                                className="border-4 border-foreground rounded-xl p-3 flex flex-col sm:flex-row gap-3 items-stretch sm:items-end bg-background"
                                            >
                                                <div className="flex-1 grid sm:grid-cols-3 gap-3 w-full">
                                                    <Field
                                                        label={`Nome ${index + 1}`}
                                                        error={participantErrors.name}
                                                    >
                                                        <input
                                                            ref={(node) => {
                                                                participantRefs.current[participant.id] = {
                                                                    ...participantRefs.current[participant.id],
                                                                    name: node,
                                                                };
                                                            }}
                                                            className={inputClass(
                                                                Boolean(participantErrors.name),
                                                            )}
                                                            value={participant.name}
                                                            onChange={(event) =>
                                                                updateParticipant(
                                                                    participant.id,
                                                                    { name: event.target.value },
                                                                    "name",
                                                                )
                                                            }
                                                            placeholder="Nome"
                                                            aria-invalid={Boolean(
                                                                participantErrors.name,
                                                            )}
                                                        />
                                                    </Field>

                                                    <Field
                                                        label="Telefone"
                                                        error={livePhoneError(
                                                            participant.id,
                                                            participant.phone,
                                                        )}
                                                    >
                                                        <input
                                                            ref={(node) => {
                                                                participantRefs.current[participant.id] = {
                                                                    ...participantRefs.current[participant.id],
                                                                    phone: node,
                                                                };
                                                            }}
                                                            className={inputClass(
                                                                Boolean(
                                                                    livePhoneError(
                                                                        participant.id,
                                                                        participant.phone,
                                                                    ),
                                                                ),
                                                            )}
                                                            value={participant.phone}
                                                            onChange={(event) =>
                                                                updateParticipant(
                                                                    participant.id,
                                                                    {
                                                                        phone: formatBrazilPhoneDisplay(
                                                                            event.target.value,
                                                                        ),
                                                                    },
                                                                    "phone",
                                                                )
                                                            }
                                                            onBlur={() => touchPhone(participant.id)}
                                                            placeholder={
                                                                GENERIC_BRAZIL_PHONE_PLACEHOLDER
                                                            }
                                                            autoComplete="tel"
                                                            aria-invalid={Boolean(
                                                                livePhoneError(
                                                                    participant.id,
                                                                    participant.phone,
                                                                ),
                                                            )}
                                                        />
                                                    </Field>

                                                    <Field
                                                        label="Valor (R$)"
                                                        error={participantErrors.amount}
                                                    >
                                                        <CurrencyBrInput
                                                            value={participant.amount}
                                                            onValueChange={(value) =>
                                                                updateParticipant(
                                                                    participant.id,
                                                                    { amount: value },
                                                                    "amount",
                                                                )
                                                            }
                                                            inputRef={(node) => {
                                                                participantRefs.current[participant.id] = {
                                                                    ...participantRefs.current[participant.id],
                                                                    amount: node,
                                                                };
                                                            }}
                                                            aria-invalid={Boolean(
                                                                participantErrors.amount,
                                                            )}
                                                        />
                                                    </Field>
                                                </div>

                                                <button
                                                    type="button"
                                                    onClick={() => removeParticipant(participant.id)}
                                                    className="border-2 border-foreground bg-card p-2 rounded-lg brutal-press brutal-press-sm self-end"
                                                    aria-label="Remover"
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>

                                <button
                                    type="button"
                                    onClick={() =>
                                        setParticipants((rows) => [...rows, newP()])
                                    }
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
                                onClick={() => setStep((current) => Math.max(1, current - 1))}
                                className="border-4 border-foreground bg-card px-4 py-2 rounded-xl font-bold brutal-press brutal-press-sm disabled:opacity-40"
                            >
                                Voltar
                            </button>

                            {step < 3 ? (
                                <button
                                    type="button"
                                    onClick={continueStep}
                                    className="bg-foreground text-background border-4 border-foreground px-5 py-2 rounded-xl font-black uppercase brutal-press brutal-press-sm"
                                >
                                    Continuar
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    disabled={submitting}
                                    onClick={submit}
                                    className="bg-accent text-accent-foreground border-4 border-foreground px-5 py-3 rounded-xl font-black uppercase brutal-press brutal-press-md disabled:opacity-50"
                                >
                                    {submitting ? "Gerando..." : "Criar cobrança e gerar link"}
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                <aside className="lg:sticky lg:top-24 h-max border-4 border-foreground bg-arcade-cyan rounded-2xl brutal-shadow p-5 flex flex-col gap-3">
                    <h3 className="font-display text-xl uppercase">Resumo</h3>
                    <Row label="Total" value={formatBRL(totalNum)} />
                    <Row label="Distribuído" value={formatBRL(distributed)} />
                    <Row
                        label="Diferença"
                        value={formatBRL(diff)}
                        highlight={Math.abs(diff) > 0.001 ? (diff > 0 ? "warn" : "ok") : "ok"}
                    />
                    <Row label="Participantes" value={participants.length} />
                    <p
                        className="rounded-xl border-2 border-foreground/70 bg-background/70 px-3 py-3 text-sm font-medium leading-snug text-foreground"
                        role="status"
                    >
                        {differenceMessage(diff)}
                    </p>
                    <p className="text-xs font-medium text-foreground/70">
                        Você só precisa bloquear quando faltar valor. Se houver
                        excedente, ele fica em caixa e a cobrança pode ser criada.
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

function Field({
    label,
    children,
    error,
}: {
    label: string;
    children: React.ReactNode;
    error?: string | null;
}) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-xs font-bold uppercase tracking-widest">
                {label}
            </span>
            {children}
            {error && (
                <span className="text-sm font-medium text-status-rejected-fg">
                    {error}
                </span>
            )}
        </label>
    );
}

function Row({
    label,
    value,
    highlight,
}: {
    label: string;
    value: React.ReactNode;
    highlight?: "ok" | "warn" | "error";
}) {
    const tone =
        highlight === "ok"
            ? "text-foreground"
            : highlight === "warn"
              ? "text-foreground"
              : highlight === "error"
                ? "text-status-rejected-fg"
                : "";
    const background =
        highlight === "ok"
            ? "bg-status-paid"
            : highlight === "warn"
              ? "bg-status-pending"
              : highlight === "error"
                ? "bg-status-rejected"
                : "bg-card";

    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-bold uppercase tracking-wider opacity-80">
                {label}
            </span>
            <span
                className={`font-display tabular-nums px-3 py-1 rounded-lg border-2 border-foreground ${background} ${tone}`}
            >
                {value}
            </span>
        </div>
    );
}

function Stepper({ step }: { step: number }) {
    const steps = ["Dados", "Pix", "Participantes"];

    return (
        <ol className="flex gap-2">
            {steps.map((label, index) => {
                const current = index + 1;
                const active = current === step;
                const done = current < step;

                return (
                    <li
                        key={label}
                        className={`flex-1 border-4 border-foreground rounded-xl px-3 py-2 text-sm font-bold flex items-center gap-2 ${
                            active
                                ? "bg-arcade-pink text-primary-foreground"
                                : done
                                  ? "bg-arcade-green"
                                  : "bg-card"
                        }`}
                    >
                        <span className="size-6 rounded-full border-2 border-foreground bg-card text-foreground grid place-items-center text-xs font-black">
                            {current}
                        </span>
                        {label}
                    </li>
                );
            })}
        </ol>
    );
}
