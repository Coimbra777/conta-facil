// Helpers de formatação BR.
export const formatBRL = (value: number) =>
    value.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

export const formatPhone = (raw: string) => {
    const digits = raw.replace(/\D/g, "");
    if (digits.length <= 10) {
        return digits.replace(/(\d{2})(\d{4})(\d{0,4})/, "($1) $2-$3").trim();
    }
    return digits.replace(/(\d{2})(\d{5})(\d{0,4})/, "($1) $2-$3").trim();
};

export const formatDate = (iso?: string) => {
    if (!iso) return "—";
    return new Date(iso).toLocaleDateString("pt-BR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    });
};

export const formatDateTime = (iso?: string) => {
    if (!iso) return "—";
    return new Date(iso).toLocaleString("pt-BR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

export const initials = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((n) => n[0]?.toUpperCase() ?? "")
        .join("");

export const buildPublicLink = (hash: string) =>
    `${window.location.origin}/p/${hash}`;

/** Link completo com fragmento `#manage=` para o SPA persistir o token (mesmo padrão da API). */
export const buildPublicManageLink = (hash: string, manageToken: string) =>
    `${window.location.origin}/p/${hash}#manage=${encodeURIComponent(manageToken)}`;

/** Extrai o token de uma `manage_url` retornada pela API (absoluta ou relativa). */
export function parseManageTokenFromManageUrl(manageUrl: string): string | null {
    try {
        const withOrigin =
            manageUrl.startsWith("http://") || manageUrl.startsWith("https://")
                ? manageUrl
                : `${window.location.origin}${manageUrl.startsWith("/") ? "" : "/"}${manageUrl}`;
        const u = new URL(withOrigin);
        const h = u.hash;
        if (h.startsWith("#manage=")) {
            return decodeURIComponent(
                h.slice("#manage=".length).replace(/\+/g, "%20"),
            );
        }
    } catch {
        /* ignore */
    }
    return null;
}

/** Indica se a data de vencimento (YYYY-MM-DD ou ISO) já passou (somente calendário local). */
export function isDueDateBeforeToday(iso?: string): boolean {
    if (!iso) return false;
    const day = iso.slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return false;
    const due = new Date(`${day}T12:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    due.setHours(0, 0, 0, 0);
    return due < today;
}
