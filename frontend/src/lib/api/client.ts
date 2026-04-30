// Cliente HTTP: modo demo explícito usa mock local; API real em /api/v1 (Bearer) quando configurado.

import type {
    ApiStatus,
    Expense,
    LoginPayload,
    PaginationMeta,
    Participant,
    PixKeyType,
    RegisterPayload,
    User,
} from "../types";
import { mapApiErrorToUserMessage } from "./errorMessages";
import { mockApi, isDemoPresentationPublicHash } from "./mockStore";
import { buildPublicLink, buildPublicManageLink } from "../format";

const BASE_URL_RAW = import.meta.env.VITE_API_BASE_URL as string | undefined;

/**
 * Base para `/api/v1`: URL explícita (`.env`), ou em dev `http://localhost:8000`,
 * ou string vazia → mesma origem do SPA (build servido pelo Laravel em `/`).
 */
function resolveApiBaseUrl(): string {
    const raw = BASE_URL_RAW?.trim();
    if (raw) return raw.replace(/\/+$/, "");
    if (import.meta.env.DEV) return "http://localhost:8000";
    return "";
}

/** Modo demonstração explícito (UI); fluxos protegidos usam mock local sem Bearer falso. */
const DEMO_MODE_KEY = "contacerta:demo:v1";

export function isDemoMode(): boolean {
    try {
        return localStorage.getItem(DEMO_MODE_KEY) === "1";
    } catch {
        return false;
    }
}

export function setDemoMode(on: boolean): void {
    try {
        if (on) localStorage.setItem(DEMO_MODE_KEY, "1");
        else localStorage.removeItem(DEMO_MODE_KEY);
    } catch {
        /* ignore */
    }
}

/** Cobranças / sessão logada: mock só em modo demo explícito. */
function useMockForProtected(): boolean {
    return isDemoMode();
}

/** Rotas públicas /p/:hash: mock em modo demo ou nos hashes de apresentação (seed da landing). */
export function isPublicExpenseUsingMock(hash: string): boolean {
    return isDemoMode() || isDemoPresentationPublicHash(hash);
}

function usePublicMock(hash: string): boolean {
    return isPublicExpenseUsingMock(hash);
}

const TOKEN_KEY = "contacerta:auth:v1";
export const AUTH_SESSION_CLEARED_EVENT = "contacerta:auth-session-cleared";

export function getToken(): string | null {
    try {
        return localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

export function setToken(t: string | null): void {
    try {
        if (t) localStorage.setItem(TOKEN_KEY, t);
        else localStorage.removeItem(TOKEN_KEY);
    } catch {
        /* ignore */
    }
}

export function clearAuthStorage(): void {
    setToken(null);
    if (typeof window !== "undefined") {
        window.dispatchEvent(new Event(AUTH_SESSION_CLEARED_EVENT));
    }
}

export function getSafeRedirect(
    value: string | null,
    fallback = "/dashboard",
): string {
    if (!value || !value.startsWith("/") || value.startsWith("//")) {
        return fallback;
    }
    return value;
}

export class ApiClientError extends Error {
    code?: string;
    status?: number;
    errors?: Record<string, string[]>;
    constructor(
        message: string,
        opts: {
            code?: string;
            status?: number;
            errors?: Record<string, string[]>;
        } = {},
    ) {
        super(message);
        this.code = opts.code;
        this.status = opts.status;
        this.errors = opts.errors;
    }
}

function apiErr(
    rawMessage: string,
    opts: {
        code?: string;
        status?: number;
        errors?: Record<string, string[]>;
    } = {},
): ApiClientError {
    const friendly = mapApiErrorToUserMessage({
        message: rawMessage,
        ...opts,
    });
    return new ApiClientError(friendly, opts);
}

function getRealBaseUrl(): string {
    return resolveApiBaseUrl();
}

function authHeaders(json = false): HeadersInit {
    const h: Record<string, string> = {};
    if (json) h["Content-Type"] = "application/json";
    if (isDemoMode()) return h;
    const t = getToken();
    if (t) h.Authorization = `Bearer ${t}`;
    return h;
}

function normalizeUser(raw: Record<string, unknown>): User {
    return {
        id: String(raw.id ?? ""),
        name: String(raw.name ?? ""),
        email: String(raw.email ?? ""),
        phone: raw.phone != null && String(raw.phone).trim() !== ""
            ? String(raw.phone)
            : undefined,
    };
}

function guessPixKeyType(pixKey: string): PixKeyType {
    const k = pixKey.trim();
    if (k.includes("\n") || k.length > 80) return "copia_cola";
    if (/^\d{11}$/.test(k.replace(/\D/g, ""))) return "cpf";
    if (/^\d{14}$/.test(k.replace(/\D/g, ""))) return "cnpj";
    if (k.includes("@")) return "email";
    if (/^\+?\d[\d\s().-]{8,}$/.test(k)) return "phone";
    return "random";
}

/** Campos do participante na cobrança (`participant` na API). */
function chargeParticipantFields(c: Record<string, unknown>): {
    name: string;
    phone: string;
} {
    const participant = c.participant as Record<string, unknown> | undefined;
    const src = participant ?? {};
    return {
        name: String(src.name ?? ""),
        phone: String(src.phone ?? ""),
    };
}

function mapExpenseFromApi(e: Record<string, unknown>): Expense {
    const charges = (e.charges as Record<string, unknown>[] | undefined) ?? [];
    return {
        id: String(e.id ?? ""),
        publicHash: String(e.public_hash ?? ""),
        title: String(e.description ?? "Cobrança"),
        description: e.description ? String(e.description) : undefined,
        status: e.status != null ? String(e.status) : undefined,
        totalAmount: Number(e.total_amount ?? 0),
        dueDate: e.due_date ? String(e.due_date) : undefined,
        pixKeyType: guessPixKeyType(String(e.pix_key ?? "")),
        pixKey: String(e.pix_key ?? ""),
        pixReceiverName: "",
        organizerName: "",
        createdAt: e.created_at
            ? String(e.created_at)
            : new Date().toISOString(),
        averageAmountPerParticipant:
            e.average_amount_per_participant !== undefined
                ? Number(e.average_amount_per_participant)
                : undefined,
        participants: charges.map((c) => {
            const { name, phone } = chargeParticipantFields(c);
            return {
                id: String(c.id ?? ""),
                name,
                phone,
                amount: Number(c.amount ?? 0),
                status: c.status as ApiStatus,
                rejectionReason: c.rejection_reason
                    ? String(c.rejection_reason)
                    : undefined,
                proofSentAt: c.proof_uploaded_at
                    ? String(c.proof_uploaded_at)
                    : undefined,
                hasProof: Boolean(c.has_proof),
                proofUrl: undefined,
            };
        }),
    };
}

function mapPublicExpenseFromApi(e: Record<string, unknown>): Expense {
    const rows =
        (e.participants as Record<string, unknown>[] | undefined) ?? [];
    const per = Number(
        e.amount_per_participant ?? e.average_amount_per_participant ?? 0,
    );

    const participants =
        rows.length > 0
            ? rows.map((m, i) => ({
                  id: String(
                      m.charge_id ?? m.id ?? `pub-${i}-${String(m.name ?? "")}`,
                  ),
                  name: String(m.name ?? ""),
                  phone: String(m.phone ?? ""),
                  amount: Number(m.amount ?? per),
                  status: (m.charge_status ?? m.status) as ApiStatus,
                  rejectionReason: m.rejection_reason
                      ? String(m.rejection_reason)
                      : undefined,
                  proofSentAt: m.proof_uploaded_at
                      ? String(m.proof_uploaded_at)
                      : undefined,
                  hasProof: Boolean(m.has_proof),
              }))
            : [];

    const participantsTotalCountRaw = e.participants_total_count;
    const validatedRaw = e.validated_charges_count;
    const openRaw = e.open_charges_count;

    return {
        id: String(e.id ?? ""),
        publicHash: String(e.public_hash ?? ""),
        title: String(e.description ?? "Cobrança"),
        description: e.description ? String(e.description) : undefined,
        status: e.status != null ? String(e.status) : undefined,
        totalAmount: Number(e.total_amount ?? e.amount ?? 0),
        dueDate: e.due_date ? String(e.due_date) : undefined,
        pixKeyType: guessPixKeyType(String(e.pix_key ?? "")),
        pixKey: String(e.pix_key ?? ""),
        pixReceiverName: String(e.owner_name ?? ""),
        organizerName: String(e.owner_name ?? "Organizador"),
        createdAt: e.created_at
            ? String(e.created_at)
            : new Date().toISOString(),
        participants,
        participantsTotalCount:
            participantsTotalCountRaw !== undefined
                ? Number(participantsTotalCountRaw)
                : participants.length > 0
                  ? participants.length
                  : undefined,
        validatedChargesCount:
            validatedRaw !== undefined ? Number(validatedRaw) : undefined,
        openChargesCount: openRaw !== undefined ? Number(openRaw) : undefined,
        averageAmountPerParticipant:
            e.average_amount_per_participant !== undefined
                ? Number(e.average_amount_per_participant)
                : per,
        canManage: Boolean(e.can_manage ?? false),
    };
}

function manageTokenHeaders(
    manageToken?: string | null,
): Record<string, string> {
    if (
        manageToken === undefined ||
        manageToken === null ||
        manageToken === ""
    ) {
        return {};
    }
    return { "X-Manage-Token": manageToken };
}

async function readJson(res: Response): Promise<unknown> {
    try {
        return await res.json();
    } catch {
        throw new ApiClientError("Resposta inválida do servidor.", {
            status: res.status,
        });
    }
}

function throwLaravelValidation(
    json: Record<string, unknown>,
    status: number,
): never {
    const errs = json.errors as Record<string, string[]> | undefined;
    const rawMsg =
        (typeof json.message === "string" && json.message) ||
        (errs
            ? String(Object.values(errs).flat()[0] ?? "Dados inválidos.")
            : "Dados inválidos.");
    throw apiErr(rawMsg, {
        status,
        code: "VALIDATION_ERROR",
        errors: errs,
    });
}

function unwrapEnvelope<T>(json: unknown, res: Response): T {
    if (!json || typeof json !== "object") {
        throw new ApiClientError("Resposta inválida do servidor.", {
            status: res.status,
        });
    }
    const o = json as Record<string, unknown>;
    if ("success" in o && o.success === false) {
        throw apiErr(String(o.message ?? "Erro."), {
            code: typeof o.code === "string" ? o.code : undefined,
            status: res.status,
            errors: o.errors as Record<string, string[]> | undefined,
        });
    }
    if ("success" in o && o.success === true && "data" in o) {
        return o.data as T;
    }
    if (res.ok && !("success" in o)) {
        return json as T;
    }
    if (o.errors && typeof o.message === "string") {
        throwLaravelValidation(o, res.status);
    }
    throw apiErr(String(o.message ?? "Erro ao processar a solicitação."), {
        status: res.status,
    });
}

async function authRegister(
    payload: RegisterPayload,
): Promise<{ token: string; user: User }> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1/auth/register`, {
        method: "POST",
        headers: authHeaders(true),
        body: JSON.stringify({
            name: payload.name,
            email: payload.email,
            password: payload.password,
            password_confirmation: payload.passwordConfirmation,
        }),
    });
    const json = await readJson(res);
    const data = unwrapEnvelope<{ user: Record<string, unknown>; token: string }>(
        json,
        res,
    );
    const user = data.user;
    const token = data.token;
    return { token, user: normalizeUser(user) };
}

async function authLogin(
    payload: LoginPayload,
): Promise<{ token: string; user: User }> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1/auth/login`, {
        method: "POST",
        headers: authHeaders(true),
        body: JSON.stringify(payload),
    });
    const json = await readJson(res);
    const data = unwrapEnvelope<{ user: Record<string, unknown>; token: string }>(
        json,
        res,
    );
    const user = data.user;
    const token = data.token;
    return { token, user: normalizeUser(user) };
}

async function authMe(): Promise<User | null> {
    if (isDemoMode()) return null;
    if (!getToken()) return null;
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1/auth/me`, {
        headers: authHeaders(),
    });
    if (res.status === 401) {
        clearAuthStorage();
        return null;
    }
    const json = await readJson(res);
    const data = unwrapEnvelope<{ user: Record<string, unknown> }>(json, res);
    const user = data.user;
    return normalizeUser(user);
}

async function authLogout(): Promise<void> {
    const t = getToken();
    if (!t) return;
    const base = getRealBaseUrl();
    try {
        await fetch(`${base}/api/v1/auth/logout`, {
            method: "POST",
            headers: authHeaders(),
        });
    } finally {
        clearAuthStorage();
    }
}

function onUnauthorized(): ApiClientError {
    clearAuthStorage();
    return new ApiClientError("Sessão expirada. Faça login novamente.", {
        status: 401,
        code: "UNAUTHENTICATED",
    });
}

/**
 * Chamadas à API pública (sem sessão do organizador): não envia Bearer.
 * Evita vazar token em endpoints abertos e impede logout/redirect em 401
 * quando o participante usa o mesmo navegador que um usuário logado.
 */
async function publicV1Fetch<T>(
    method: string,
    path: string,
    body?: unknown,
): Promise<T> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1${path}`, {
        method,
        headers:
            body !== undefined ? { "Content-Type": "application/json" } : {},
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (res.status === 403) {
        const json = (await readJson(res)) as Record<string, unknown>;
        throw new ApiClientError(String(json.message ?? "Acesso negado."), {
            status: 403,
            code: typeof json.code === "string" ? json.code : "FORBIDDEN",
        });
    }
    const json = await readJson(res);
    return unwrapEnvelope<T>(json, res);
}

async function v1Fetch<T>(
    method: string,
    path: string,
    body?: unknown,
): Promise<T> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1${path}`, {
        method,
        headers: authHeaders(body !== undefined),
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (res.status === 401) throw onUnauthorized();
    if (res.status === 403) {
        const json = (await readJson(res)) as Record<string, unknown>;
        throw new ApiClientError(String(json.message ?? "Acesso negado."), {
            status: 403,
            code: typeof json.code === "string" ? json.code : "FORBIDDEN",
        });
    }
    const json = await readJson(res);
    if (res.status === 404 && path.includes("/public/expenses/")) {
        return null as T;
    }
    return unwrapEnvelope<T>(json, res);
}

async function v1FetchEnvelope<T>(
    method: string,
    path: string,
    body?: unknown,
): Promise<{ data: T; meta: Record<string, unknown> }> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1${path}`, {
        method,
        headers: authHeaders(body !== undefined),
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (res.status === 401) throw onUnauthorized();
    if (res.status === 403) {
        const json = (await readJson(res)) as Record<string, unknown>;
        throw new ApiClientError(String(json.message ?? "Acesso negado."), {
            status: 403,
            code: typeof json.code === "string" ? json.code : "FORBIDDEN",
        });
    }

    const json = await readJson(res);
    const data = unwrapEnvelope<T>(json, res);
    const envelope = json as { meta?: Record<string, unknown> };

    return {
        data,
        meta:
            envelope.meta && typeof envelope.meta === "object"
                ? envelope.meta
                : {},
    };
}

async function v1FetchBlob(path: string): Promise<Blob> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1${path}`, {
        headers: authHeaders(false),
    });
    if (res.status === 401) throw onUnauthorized();
    if (!res.ok) {
        let msg = `Erro ${res.status}`;
        try {
            const data = (await res.json()) as Record<string, unknown>;
            if (
                data &&
                typeof data === "object" &&
                data.success === false &&
                typeof data.message === "string"
            ) {
                msg = data.message;
            }
        } catch {
            /* corpo não JSON */
        }
        throw apiErr(msg, { status: res.status });
    }
    return res.blob();
}

export const api = {
    /** Modo apresentação: sem Bearer; dados só em mockStore. */
    enterDemo: async (): Promise<User> => {
        setDemoMode(true);
        return mockApi.enterDemo();
    },

    register: (
        payload: RegisterPayload,
    ): Promise<{ token: string | null; user: User }> =>
        useMockForProtected()
            ? mockApi
                  .register(payload.name, payload.email, payload.password)
                  .then((user) => ({ token: null, user }))
            : authRegister(payload),

    login: (
        payload: LoginPayload,
    ): Promise<{ token: string | null; user: User }> =>
        useMockForProtected()
            ? mockApi
                  .login(payload.email, payload.password)
                  .then((user) => ({ token: null, user }))
            : authLogin(payload),

    me: (): Promise<User | null> => (isDemoMode() ? mockApi.me() : authMe()),

    logout: (): Promise<boolean> =>
        useMockForProtected()
            ? mockApi.logout()
            : authLogout().then(() => true),

    listExpenses: async (
        opts: { page?: number; perPage?: number } = {},
    ): Promise<{ expenses: Expense[]; pagination: PaginationMeta }> => {
        if (useMockForProtected()) {
            const expenses = await mockApi.listExpenses();

            return {
                expenses,
                pagination: {
                    currentPage: 1,
                    lastPage: 1,
                    perPage: expenses.length,
                    total: expenses.length,
                },
            };
        }

        const params = new URLSearchParams();
        if (opts.page && opts.page > 1) params.set("page", String(opts.page));
        if (opts.perPage) params.set("per_page", String(opts.perPage));

        const path = params.size > 0 ? `/expenses?${params.toString()}` : `/expenses`;
        const { data, meta } = await v1FetchEnvelope<{
            expenses: Record<string, unknown>[];
        }>(
            "GET",
            path,
        );
        const list = data.expenses ?? [];
        const pagination =
            meta.pagination &&
            typeof meta.pagination === "object" &&
            !Array.isArray(meta.pagination)
                ? (meta.pagination as Record<string, unknown>)
                : {};

        return {
            expenses: list.map((e) => mapExpenseFromApi(e)),
            pagination: {
                currentPage: Number(pagination.current_page ?? 1),
                lastPage: Number(pagination.last_page ?? 1),
                perPage: Number(pagination.per_page ?? list.length),
                total: Number(pagination.total ?? list.length),
            },
        };
    },

    getExpense: async (id: string): Promise<Expense | null> => {
        if (useMockForProtected()) return mockApi.getExpense(id);
        try {
            const data = await v1Fetch<{ expense: Record<string, unknown> }>(
                "GET",
                `/expenses/${id}`,
            );
            return mapExpenseFromApi(data.expense);
        } catch (e) {
            if (
                e instanceof ApiClientError &&
                (e.code === "NOT_FOUND" || e.status === 404)
            )
                return null;
            throw e;
        }
    },

    createExpense: async (input: {
        title: string;
        description?: string;
        totalAmount: number;
        dueDate?: string;
        pixKeyType: PixKeyType;
        pixKey: string;
        pixReceiverName: string;
        participants: Array<{ name: string; phone: string; amount: number }>;
    }): Promise<Expense> => {
        if (useMockForProtected()) return mockApi.createExpense(input);
        const due =
            input.dueDate?.slice(0, 10) ??
            new Date().toISOString().slice(0, 10);
        const payload = {
            description: input.title,
            total_amount: input.totalAmount,
            due_date: due,
            pix_key: input.pixKey,
            pix_qr_code:
                input.pixKeyType === "copia_cola" ? input.pixKey : null,
        };
        const created = await v1Fetch<{ expense: Record<string, unknown> }>(
            "POST",
            `/expenses`,
            payload,
        );
        const expId = String(created.expense.id);
        /* Primeira inclusão: despesa ainda sem cobranças — payload fecha o total.
         * Novos envios ao mesmo endpoint só podem trazer telefones novos (ver doc/API.md). */
        await v1Fetch<{ expense: Record<string, unknown> }>(
            "POST",
            `/expenses/${expId}/participants`,
            {
                participants: input.participants.map((p) => ({
                    name: p.name,
                    phone: p.phone.replace(/\D/g, ""),
                    amount: p.amount,
                })),
            },
        );
        const fresh = await v1Fetch<{ expense: Record<string, unknown> }>(
            "GET",
            `/expenses/${expId}`,
        );
        return mapExpenseFromApi(fresh.expense);
    },

    validateCharge: async (
        _expenseId: string,
        chargeId: string,
    ): Promise<Participant | null> => {
        if (useMockForProtected())
            return mockApi.validateParticipant(_expenseId, chargeId);
        const data = await v1Fetch<{ charge: Record<string, unknown> }>(
            "PATCH",
            `/charges/${chargeId}/validate`,
        );
        const c = data.charge;
        const { name, phone } = chargeParticipantFields(c);
        return {
            id: String(c.id ?? ""),
            name,
            phone,
            amount: Number(c.amount ?? 0),
            status: c.status as ApiStatus,
        };
    },

    rejectCharge: async (
        _expenseId: string,
        chargeId: string,
        reason: string,
    ): Promise<Participant | null> => {
        if (useMockForProtected())
            return mockApi.rejectParticipant(_expenseId, chargeId, reason);
        const data = await v1Fetch<{ charge: Record<string, unknown> }>(
            "PATCH",
            `/charges/${chargeId}/reject`,
            { reason },
        );
        const c = data.charge;
        const { name, phone } = chargeParticipantFields(c);
        return {
            id: String(c.id ?? ""),
            name,
            phone,
            amount: Number(c.amount ?? 0),
            status: c.status as ApiStatus,
            rejectionReason: c.rejection_reason
                ? String(c.rejection_reason)
                : reason,
        };
    },

    fetchChargeProofForView: async (chargeId: string): Promise<Blob> => {
        if (useMockForProtected())
            return mockApi.fetchChargeProofForView(chargeId);
        return v1FetchBlob(
            `/charges/${encodeURIComponent(chargeId)}/proofs/latest/view`,
        );
    },

    getPublicExpense: async (
        hash: string,
        manageToken?: string | null,
    ): Promise<Expense | null> => {
        if (usePublicMock(hash))
            return mockApi.getExpenseByHash(hash, manageToken);
        const base = getRealBaseUrl();
        const res = await fetch(`${base}/api/v1/public/expenses/${hash}`, {
            headers: manageTokenHeaders(manageToken),
        });
        const json = await readJson(res);
        if (res.status === 404) return null;
        if (res.status === 403) return null;
        try {
            const data = unwrapEnvelope<{ expense: Record<string, unknown> }>(
                json,
                res,
            );
            return mapPublicExpenseFromApi(data.expense);
        } catch {
            return null;
        }
    },

    identifyParticipant: async (
        hash: string,
        name: string,
        phone: string,
        manageToken?: string | null,
    ) => {
        if (usePublicMock(hash))
            return mockApi.identifyParticipant(hash, name, phone);
        try {
            const data = await publicV1Fetch<{
                status: ApiStatus;
                rejection_reason?: string | null;
                can_submit_proof?: boolean;
                amount: number;
            }>(
                "POST",
                `/public/expenses/${hash}/validate-participant`,
                {
                    name,
                    phone,
                },
            );
            const exp = await api.getPublicExpense(hash, manageToken);
            if (!exp) return null;
            const participant: Participant = {
                id: `self-${hash}`,
                name: name.trim(),
                phone: phone.trim(),
                amount: Number(data.amount ?? 0),
                status: data.status,
                rejectionReason: data.rejection_reason
                    ? String(data.rejection_reason)
                    : undefined,
            };
            return { expense: exp, participant };
        } catch (e) {
            if (
                e instanceof ApiClientError &&
                e.code === "PARTICIPANT_NOT_FOUND"
            )
                return null;
            throw e;
        }
    },

    submitProof: async (
        hash: string,
        participant: Participant,
        file: File,
    ): Promise<Participant | null> => {
        if (usePublicMock(hash))
            return mockApi.submitProof(hash, participant, file);
        const base = getRealBaseUrl();
        const fd = new FormData();
        fd.append("name", participant.name);
        fd.append("phone", participant.phone);
        fd.append("proof", file);
        const res = await fetch(
            `${base}/api/v1/public/expenses/${hash}/submit-proof`,
            {
                method: "POST",
                body: fd,
            },
        );
        const json = await readJson(res);
        if (!res.ok) {
            const o = json as Record<string, unknown>;
            if (o.success === false) {
                throw new ApiClientError(String(o.message ?? "Erro."), {
                    code: typeof o.code === "string" ? o.code : undefined,
                    status: res.status,
                    errors: o.errors as Record<string, string[]> | undefined,
                });
            }
            if (o.errors) throwLaravelValidation(o, res.status);
            throw new ApiClientError(
                String(o.message ?? "Erro ao enviar comprovante."),
                { status: res.status },
            );
        }
        const out = unwrapEnvelope<{
            status: ApiStatus;
            rejection_reason?: string | null;
        }>(json, res);
        return {
            ...participant,
            status: out.status,
            rejectionReason: out.rejection_reason
                ? String(out.rejection_reason)
                : undefined,
            proofSentAt: new Date().toISOString(),
        };
    },

    deleteExpense: async (expenseId: string): Promise<void> => {
        if (useMockForProtected()) {
            await mockApi.deleteExpense(expenseId);
            return;
        }
        await v1Fetch<unknown>(
            "DELETE",
            `/expenses/${expenseId}`,
        );
    },

    /** Criação anônima em standby no produto: POST `/api/public/expenses` retorna **410** `PUBLIC_CREATE_EXPENSE_STANDBY`. Mantido para eventual reativação. */
    createPublicExpense: async (input: {
        ownerName: string;
        ownerPhone: string;
        description: string;
        amount: number;
        pixKey: string;
        pixQrCode?: string | null;
        dueDate: string;
        participants: Array<{ name: string; phone: string }>;
        includeOwnerAsParticipant?: boolean;
    }): Promise<{
        participantUrl: string;
        manageUrl: string;
        manageToken: string;
        publicHash: string;
        expenseId: string;
    }> => {
        const base = getRealBaseUrl();
        const res = await fetch(`${base}/api/public/expenses`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                owner_name: input.ownerName.trim(),
                owner_phone: input.ownerPhone.replace(/\D/g, ""),
                description: input.description.trim(),
                amount: input.amount,
                pix_key: input.pixKey.trim(),
                pix_qr_code: input.pixQrCode ?? null,
                due_date: input.dueDate.slice(0, 10),
                participants: input.participants.map((p) => ({
                    name: p.name.trim(),
                    phone: p.phone.replace(/\D/g, ""),
                })),
                include_owner_as_participant:
                    input.includeOwnerAsParticipant ?? false,
            }),
        });
        const json = await readJson(res);
        if (!res.ok) {
            const o = json as Record<string, unknown>;
            if (o.success === false) {
                throw apiErr(String(o.message ?? "Erro."), {
                    code: typeof o.code === "string" ? o.code : undefined,
                    status: res.status,
                    errors: o.errors as Record<string, string[]> | undefined,
                });
            }
            if (o.errors) throwLaravelValidation(o, res.status);
            throw apiErr(String(o.message ?? "Erro ao criar cobrança."), {
                status: res.status,
            });
        }
        const data = unwrapEnvelope<{ expense: Record<string, unknown> }>(
            json,
            res,
        );
        const exp = data.expense;
        const publicHash = String(exp.public_hash ?? "");
        const manageToken = String(exp.manage_token ?? "");
        const participantUrl =
            typeof exp.participant_url === "string" && exp.participant_url !== ""
                ? exp.participant_url
                : buildPublicLink(publicHash);
        const manageUrl =
            typeof exp.manage_url === "string" && exp.manage_url !== ""
                ? exp.manage_url
                : manageToken && publicHash
                  ? buildPublicManageLink(publicHash, manageToken)
                  : "";
        return {
            participantUrl,
            manageUrl,
            manageToken,
            publicHash,
            expenseId: String(exp.id ?? ""),
        };
    },
};

export type { Expense, Participant, PixKeyType, User };
