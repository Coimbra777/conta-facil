// Mock store em memória (com persistência leve em localStorage).
// Substitua por chamadas reais à API trocando o adapter em src/lib/api/client.ts.

import type { Expense, Participant, PixKeyType, User } from "../types";
import { validateExpenseParticipantsPayload } from "../splitAmountEqually";

const LS_KEY = "contacerta:mock:v1";
/** Sessão mock isolada — nunca usar a mesma chave do token Sanctum (`contacerta:auth:v1`). */
const MOCK_SESSION_KEY = "contacerta:mock-session:v1";

/** Hash público da cobrança seed na landing / modo apresentação — não existe na API real. */
export const DEMO_PRESENTATION_PUBLIC_HASH = "demo-churrasco-2025";

export function isDemoPresentationPublicHash(hash: string): boolean {
    return hash === DEMO_PRESENTATION_PUBLIC_HASH;
}

function hasMockSession(): boolean {
    try {
        return localStorage.getItem(MOCK_SESSION_KEY) === "1";
    } catch {
        return false;
    }
}

function setMockSession(on: boolean) {
    try {
        if (on) localStorage.setItem(MOCK_SESSION_KEY, "1");
        else localStorage.removeItem(MOCK_SESSION_KEY);
    } catch {}
}

interface DB {
    user: User | null;
    expenses: Expense[];
}

const seedExpense = (): Expense => ({
    id: "exp_demo_1",
    publicHash: DEMO_PRESENTATION_PUBLIC_HASH,
    title: "Churrasco da galera 🔥",
    description: "Carne, carvão e bebida pro sábado.",
    totalAmount: 384.5,
    dueDate: new Date(Date.now() + 6 * 86400000).toISOString(),
    pixKeyType: "email",
    pixKey: "organizador@exemplo.com",
    pixReceiverName: "Lucas Martins",
    organizerName: "Lucas Martins",
    createdAt: new Date(Date.now() - 2 * 86400000).toISOString(),
    participants: [
        {
            id: "p1",
            name: "Lucas Martins",
            phone: "(11) 91234-5678",
            amount: 76.9,
            status: "validated",
            hasProof: true,
            proofSentAt: new Date(Date.now() - 86400000).toISOString(),
        },
        {
            id: "p2",
            name: "Bia Rocha",
            phone: "(11) 99876-5432",
            amount: 76.9,
            status: "proof_sent",
            hasProof: true,
            proofSentAt: new Date(Date.now() - 3600000).toISOString(),
        },
        {
            id: "p3",
            name: "João Pedro",
            phone: "(21) 98888-1111",
            amount: 76.9,
            status: "pending",
        },
        {
            id: "p4",
            name: "Marina Reis",
            phone: "(31) 97777-2222",
            amount: 76.9,
            status: "rejected",
            hasProof: true,
            rejectionReason: "Comprovante ilegível, reenvie por favor.",
            proofSentAt: new Date(Date.now() - 7200000).toISOString(),
        },
        {
            id: "p5",
            name: "Thiago Costa",
            phone: "(41) 96666-3333",
            amount: 76.9,
            status: "pending",
        },
    ],
});

/** Snapshot só para UI da página /demo (mesmos dados do seed persistido). */
export function getDemoPresentationSeedExpense(): Expense {
    return seedExpense();
}

function load(): DB {
    try {
        const raw = localStorage.getItem(LS_KEY);
        if (raw) return JSON.parse(raw) as DB;
    } catch {}
    const initial: DB = { user: null, expenses: [seedExpense()] };
    save(initial);
    return initial;
}

function save(db: DB) {
    try {
        localStorage.setItem(LS_KEY, JSON.stringify(db));
    } catch {}
}

const uid = (prefix: string) =>
    `${prefix}_${Math.random().toString(36).slice(2, 9)}`;
const slug = (s: string) =>
    s
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "")
        .slice(0, 30) || "cobranca";

const delay = (ms = 350) => new Promise((r) => setTimeout(r, ms));

export const mockApi = {
    // Auth
    async register(name: string, email: string, _password: string) {
        await delay();
        const db = load();
        const user: User = { id: uid("u"), name, email };
        db.user = user;
        save(db);
        setMockSession(true);
        return user;
    },
    async login(email: string, _password: string) {
        await delay();
        const db = load();
        const user: User = db.user ?? {
            id: uid("u"),
            name: email.split("@")[0],
            email,
        };
        db.user = user;
        save(db);
        setMockSession(true);
        return user;
    },
    /** Entra na sessão demo sem e-mail/senha (apresentação). */
    async enterDemo() {
        await delay(150);
        const db = load();
        const user: User = {
            id: "demo_visitor",
            name: "Visitante (demonstração)",
            email: "demo@contacerta.local",
        };
        db.user = user;
        save(db);
        setMockSession(true);
        return user;
    },
    async me() {
        await delay(120);
        if (!hasMockSession()) return null;
        return load().user;
    },
    async logout() {
        setMockSession(false);
        return true;
    },

    // Expenses
    async listExpenses() {
        await delay(200);
        return load().expenses;
    },
    async getExpense(id: string) {
        await delay(180);
        return load().expenses.find((e) => e.id === id) ?? null;
    },
    async getExpenseByHash(hash: string, manageToken?: string | null) {
        await delay(180);
        const exp = load().expenses.find((e) => e.publicHash === hash) ?? null;
        if (!exp) return null;
        const hasManage = Boolean(manageToken?.trim());
        if (hasManage) {
            return { ...exp, canManage: true };
        }
        const total = exp.participants.length;
        const validated = exp.participants.filter(
            (p) => p.status === "validated",
        ).length;
        return {
            ...exp,
            participants: [],
            canManage: false,
            participantsTotalCount: total,
            validatedChargesCount: validated,
            openChargesCount: Math.max(0, total - validated),
            averageAmountPerParticipant:
                total > 0 ? Math.round((exp.totalAmount / total) * 100) / 100 : 0,
        };
    },
    async createExpense(input: {
        title: string;
        description?: string;
        totalAmount: number;
        dueDate?: string;
        pixKeyType: PixKeyType;
        pixKey: string;
        pixReceiverName: string;
        participants: Array<{ name: string; phone: string; amount: number }>;
    }) {
        await delay(450);
        const validation = validateExpenseParticipantsPayload(
            input.participants.map((p) => ({
                name: p.name,
                phone: p.phone,
                amount: p.amount.toFixed(2).replace(".", ","),
            })),
            input.totalAmount,
        );
        if (!validation.ok) {
            throw new Error(validation.message);
        }
        const db = load();
        const organizer = db.user?.name ?? input.pixReceiverName;
        const expense: Expense = {
            id: uid("exp"),
            publicHash: `${slug(input.title)}-${Math.random().toString(36).slice(2, 6)}`,
            title: input.title,
            description: input.description,
            totalAmount: input.totalAmount,
            dueDate: input.dueDate,
            pixKeyType: input.pixKeyType,
            pixKey: input.pixKey,
            pixReceiverName: input.pixReceiverName,
            organizerName: organizer,
            createdAt: new Date().toISOString(),
            participants: input.participants.map((p) => ({
                id: uid("p"),
                name: p.name,
                phone: p.phone,
                amount: p.amount,
                status: "pending",
            })),
        };
        db.expenses.unshift(expense);
        save(db);
        return expense;
    },
    async validateParticipant(expenseId: string, participantId: string) {
        await delay(200);
        return updateParticipant(expenseId, participantId, (p) => {
            p.status = "validated";
            p.rejectionReason = undefined;
        });
    },
    async rejectParticipant(
        expenseId: string,
        participantId: string,
        reason: string,
    ) {
        await delay(200);
        return updateParticipant(expenseId, participantId, (p) => {
            p.status = "rejected";
            p.rejectionReason = reason;
        });
    },

    async fetchChargeProofForView(_chargeId: string): Promise<Blob> {
        await delay(200);
        const svg =
            '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="160"><rect fill="#bfdbfe" width="240" height="160" rx="8"/><text x="120" y="88" text-anchor="middle" font-family="system-ui,sans-serif" font-size="13" fill="#1e3a8a">Demo — comprovante</text></svg>';
        return new Blob([svg], { type: "image/svg+xml" });
    },

    // Public
    async identifyParticipant(hash: string, name: string, phone: string) {
        await delay(250);
        const db = load();
        const exp = db.expenses.find((e) => e.publicHash === hash);
        if (!exp) return null;
        const norm = (s: string) => s.replace(/\D/g, "");
        let p = exp.participants.find(
            (x) =>
                x.name.toLowerCase().trim() === name.toLowerCase().trim() ||
                (phone && norm(x.phone) === norm(phone)),
        );
        if (!p) {
            // Permite auto-cadastro com valor 0 se desejado; aqui exigimos match.
            return null;
        }
        return { expense: exp, participant: p };
    },

    /**
     * Fluxo público demo: convidado não está na lista — adiciona participante temporário
     * ao mock local para permitir ver Pix/comprovante sem alterar API real.
     */
    async continuePublicDemoIdentification(
        hash: string,
        name: string,
        phoneDigits: string,
    ) {
        await delay(250);
        const db = load();
        const exp = db.expenses.find((e) => e.publicHash === hash);
        if (!exp) return null;
        const template =
            exp.participants.find((p) => p.status === "pending") ??
            exp.participants[0];
        if (!template) return null;
        const guestId = `guest_${Date.now()}_${Math.floor(Math.random() * 1e6)}`;
        const guest: Participant = {
            id: guestId,
            name: name.trim() || "Visitante (demo)",
            phone: phoneDigits,
            amount: template.amount,
            status: "pending",
        };
        exp.participants.push(guest);
        save(db);
        return { expense: exp, participant: guest };
    },

    async submitProof(hash: string, participant: Participant, _file: File) {
        await delay(450);
        void _file;
        const db = load();
        const exp = db.expenses.find((e) => e.publicHash === hash);
        if (!exp) return null;
        const p = exp.participants.find((x) => x.id === participant.id);
        if (!p) return null;
        p.status = "proof_sent";
        p.hasProof = true;
        p.proofSentAt = new Date().toISOString();
        p.rejectionReason = undefined;
        save(db);
        return p;
    },
    async deleteExpense(id: string) {
        await delay(200);
        const db = load();
        db.expenses = db.expenses.filter((e) => e.id !== id);
        save(db);
    },
};

function updateParticipant(
    expenseId: string,
    participantId: string,
    mut: (p: Participant) => void,
) {
    const db = load();
    const exp = db.expenses.find((e) => e.id === expenseId);
    if (!exp) return null;
    const p = exp.participants.find((x) => x.id === participantId);
    if (!p) return null;
    mut(p);
    save(db);
    return p;
}
