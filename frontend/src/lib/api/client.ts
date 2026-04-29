// Cliente HTTP: modo demo explícito usa mock local; API real em /api/v1 (Bearer) quando configurado.

import type {
    ApiStatus,
    Expense,
    Participant,
    PixKeyType,
    User,
} from "../types";
import { mockApi, isDemoPresentationPublicHash } from "./mockStore";

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
function usePublicMock(hash: string): boolean {
    return isDemoMode() || isDemoPresentationPublicHash(hash);
}

const TOKEN_KEY = "contacerta:auth:v1";
export const getToken = () => localStorage.getItem(TOKEN_KEY);
export const setToken = (t: string | null) => {
    if (t) localStorage.setItem(TOKEN_KEY, t);
    else localStorage.removeItem(TOKEN_KEY);
};

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

function mapExpenseFromApi(e: Record<string, unknown>): Expense {
    const charges = (e.charges as Record<string, unknown>[] | undefined) ?? [];
    return {
        id: String(e.id ?? ""),
        publicHash: String(e.public_hash ?? ""),
        title: String(e.description ?? "Cobrança"),
        description: e.description ? String(e.description) : undefined,
        totalAmount: Number(e.total_amount ?? 0),
        dueDate: e.due_date ? String(e.due_date) : undefined,
        pixKeyType: guessPixKeyType(String(e.pix_key ?? "")),
        pixKey: String(e.pix_key ?? ""),
        pixReceiverName: "",
        organizerName: "",
        createdAt: e.created_at
            ? String(e.created_at)
            : new Date().toISOString(),
        participants: charges.map((c) => {
            const member = c.member as Record<string, unknown> | undefined;
            return {
                id: String(c.id ?? ""),
                name: String(member?.name ?? ""),
                phone: String(member?.phone ?? ""),
                amount: Number(c.amount ?? 0),
                status: c.status as ApiStatus,
                rejectionReason: c.rejection_reason
                    ? String(c.rejection_reason)
                    : undefined,
                proofSentAt: undefined,
                proofUrl: undefined,
            };
        }),
    };
}

function mapPublicExpenseFromApi(e: Record<string, unknown>): Expense {
    const members = e.members as Record<string, unknown>[] | undefined;
    const parts =
        (e.participants as Record<string, unknown>[] | undefined) ?? [];
    const per = Number(e.amount_per_member ?? 0);

    const participants =
        members && members.length > 0
            ? members.map((m) => ({
                  id: String(m.charge_id ?? m.id ?? ""),
                  name: String(m.name ?? ""),
                  phone: String(m.phone ?? ""),
                  amount: Number(m.amount ?? per),
                  status: (m.charge_status ?? m.status) as ApiStatus,
                  rejectionReason: m.rejection_reason
                      ? String(m.rejection_reason)
                      : undefined,
              }))
            : parts.map((p, i) => ({
                  id: `pub-${i}-${String(p.name ?? "")}`,
                  name: String(p.name ?? ""),
                  phone: "",
                  amount: per,
                  status: p.status as ApiStatus,
              }));

    return {
        id: String(e.id ?? ""),
        publicHash: String(e.public_hash ?? ""),
        title: String(e.description ?? "Cobrança"),
        description: e.description ? String(e.description) : undefined,
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
    };
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
    const msg =
        (typeof json.message === "string" && json.message) ||
        (errs
            ? String(Object.values(errs).flat()[0] ?? "Dados inválidos.")
            : "Dados inválidos.");
    throw new ApiClientError(msg, {
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
        throw new ApiClientError(String(o.message ?? "Erro."), {
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
    throw new ApiClientError(
        String(o.message ?? "Erro ao processar a solicitação."),
        { status: res.status },
    );
}

async function authRegister(
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
): Promise<User> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1/auth/register`, {
        method: "POST",
        headers: authHeaders(true),
        body: JSON.stringify({
            name,
            email,
            password,
            password_confirmation,
        }),
    });
    const json = (await readJson(res)) as Record<string, unknown>;
    if (!res.ok) {
        if (res.status === 422 && json.errors)
            throwLaravelValidation(json, res.status);
        throw new ApiClientError(
            String(json.message ?? "Não foi possível criar a conta."),
            { status: res.status },
        );
    }
    const user = json.user as Record<string, unknown>;
    const token = json.token as string;
    setToken(token);
    return normalizeUser(user);
}

async function authLogin(email: string, password: string): Promise<User> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1/auth/login`, {
        method: "POST",
        headers: authHeaders(true),
        body: JSON.stringify({ email, password }),
    });
    const json = (await readJson(res)) as Record<string, unknown>;
    if (!res.ok) {
        throw new ApiClientError(
            String(json.message ?? "Credenciais inválidas."),
            { status: res.status, code: "UNAUTHENTICATED" },
        );
    }
    const user = json.user as Record<string, unknown>;
    const token = json.token as string;
    setToken(token);
    return normalizeUser(user);
}

async function authMe(): Promise<User | null> {
    if (isDemoMode()) return null;
    if (!getToken()) return null;
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1/auth/me`, {
        headers: authHeaders(),
    });
    if (res.status === 401) {
        setToken(null);
        return null;
    }
    const json = (await readJson(res)) as Record<string, unknown>;
    if (!res.ok) {
        throw new ApiClientError(String(json.message ?? "Erro."), {
            status: res.status,
        });
    }
    const user = json.user as Record<string, unknown>;
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
        setToken(null);
    }
}

function onUnauthorized(): never {
    setToken(null);
    window.location.href = "/login";
    throw new ApiClientError("Sessão expirada. Faça login novamente.", {
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
            code: "FORBIDDEN",
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
    if (res.status === 401) onUnauthorized();
    if (res.status === 403) {
        const json = (await readJson(res)) as Record<string, unknown>;
        throw new ApiClientError(String(json.message ?? "Acesso negado."), {
            status: 403,
            code: "FORBIDDEN",
        });
    }
    const json = await readJson(res);
    if (res.status === 404 && path.includes("/public/expenses/")) {
        return null as T;
    }
    return unwrapEnvelope<T>(json, res);
}

async function ensureTeamId(): Promise<string> {
    const base = getRealBaseUrl();
    const res = await fetch(`${base}/api/v1/teams`, {
        headers: authHeaders(),
    });
    if (res.status === 401) onUnauthorized();
    const json = (await readJson(res)) as Record<string, unknown>;
    if (!res.ok)
        throw new ApiClientError(
            String(json.message ?? "Erro ao listar equipes."),
            { status: res.status },
        );
    const teams = json.teams as { id: string | number }[] | undefined;
    if (teams?.length) return String(teams[0].id);
    const cr = await fetch(`${base}/api/v1/teams`, {
        method: "POST",
        headers: authHeaders(true),
        body: JSON.stringify({ name: "Minha equipe" }),
    });
    const cj = (await readJson(cr)) as Record<string, unknown>;
    if (!cr.ok) {
        if (cr.status === 422 && cj.errors)
            throwLaravelValidation(cj, cr.status);
        throw new ApiClientError(
            String(cj.message ?? "Erro ao criar equipe."),
            { status: cr.status },
        );
    }
    const team = cj.team as Record<string, unknown>;
    return String(team.id);
}

export const api = {
    /** Modo apresentação: sem Bearer; dados só em mockStore. */
    enterDemo: async (): Promise<User> => {
        setDemoMode(true);
        return mockApi.enterDemo();
    },

    register: (
        name: string,
        email: string,
        password: string,
        password_confirmation?: string,
    ): Promise<User> =>
        useMockForProtected()
            ? mockApi.register(name, email, password)
            : authRegister(
                  name,
                  email,
                  password,
                  password_confirmation ?? password,
              ),

    login: (email: string, password: string): Promise<User> =>
        useMockForProtected()
            ? mockApi.login(email, password)
            : authLogin(email, password),

    me: (): Promise<User | null> => (isDemoMode() ? mockApi.me() : authMe()),

    logout: (): Promise<boolean> =>
        useMockForProtected()
            ? mockApi.logout()
            : authLogout().then(() => true),

    listExpenses: async (): Promise<Expense[]> => {
        if (useMockForProtected()) return mockApi.listExpenses();
        const teamId = await ensureTeamId();
        const data = await v1Fetch<{ expenses: Record<string, unknown>[] }>(
            "GET",
            `/teams/${teamId}/expenses`,
        );
        const list = data.expenses ?? [];
        return list.map((e) => mapExpenseFromApi(e));
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
        const teamId = await ensureTeamId();
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
            `/teams/${teamId}/expenses`,
            payload,
        );
        const expId = String(created.expense.id);
        await v1Fetch<{ expense: Record<string, unknown> }>(
            "POST",
            `/teams/${teamId}/expenses/${expId}/participants`,
            {
                participants: input.participants.map((p) => ({
                    name: p.name,
                    phone: p.phone,
                })),
            },
        );
        const fresh = await v1Fetch<{ expense: Record<string, unknown> }>(
            "GET",
            `/teams/${teamId}/expenses/${expId}`,
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
        const member = c.member as Record<string, unknown> | undefined;
        return {
            id: String(c.id ?? ""),
            name: String(member?.name ?? ""),
            phone: String(member?.phone ?? ""),
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
        const member = c.member as Record<string, unknown> | undefined;
        return {
            id: String(c.id ?? ""),
            name: String(member?.name ?? ""),
            phone: String(member?.phone ?? ""),
            amount: Number(c.amount ?? 0),
            status: c.status as ApiStatus,
            rejectionReason: c.rejection_reason
                ? String(c.rejection_reason)
                : reason,
        };
    },

    getPublicExpense: async (
        hash: string,
        manageToken?: string | null,
    ): Promise<Expense | null> => {
        if (usePublicMock(hash)) return mockApi.getExpenseByHash(hash);
        const base = getRealBaseUrl();
        const q =
            manageToken !== undefined &&
            manageToken !== null &&
            manageToken !== ""
                ? `?manage=${encodeURIComponent(manageToken)}`
                : "";
        const res = await fetch(`${base}/api/v1/public/expenses/${hash}${q}`);
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
            }>("POST", `/public/expenses/${hash}/validate-participant`, {
                name,
                phone,
            });
            const exp = await api.getPublicExpense(hash, manageToken);
            if (!exp) return null;
            const perMember =
                exp.participants.find(
                    (p) => p.name.toLowerCase() === name.toLowerCase().trim(),
                )?.amount ??
                exp.participants[0]?.amount ??
                exp.totalAmount / Math.max(1, exp.participants.length);
            const participant: Participant = {
                id: `self-${hash}`,
                name: name.trim(),
                phone: phone.trim(),
                amount: perMember,
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
};

export type { Expense, Participant, PixKeyType, User };
