/** Apenas dígitos */
export function digitsOnly(s: string): string {
    return s.replace(/\D/g, "");
}

/** Telefone BR: (DD) 99999-9999 ou (DD) 3333-4444. */
export function formatBrazilPhoneDisplay(input: string): string {
    const d = digitsOnly(input).slice(0, 11);
    if (d.length === 0) return "";
    if (d.length <= 2) return `(${d}`;
    const dd = d.slice(0, 2);
    const rest = d.slice(2);

    if (d.length >= 11) {
        if (rest.length <= 5) return `(${dd}) ${rest}`;
        return `(${dd}) ${rest.slice(0, 5)}-${rest.slice(5)}`;
    }

    if (rest.length <= 4) return `(${dd}) ${rest}`;
    return `(${dd}) ${rest.slice(0, 4)}-${rest.slice(4)}`;
}

/** Aceita "R$ 1.234,56" ou "1234,56" → número */
export function parseMoneyInput(s: string): number {
    const t = s.trim().replace(/^R\$\s*/i, "").replace(/\./g, "").replace(",", ".");
    const n = parseFloat(t);
    return Number.isFinite(n) ? n : 0;
}

/** Formata entrada monetária livre → estilo BR simples */
export function maskMoneyTyping(raw: string): string {
    const d = digitsOnly(raw);
    if (!d) return "";
    const v = (parseInt(d, 10) / 100).toFixed(2);
    const [intp, frac] = v.split(".");
    const intFmt = intp.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return `${intFmt},${frac}`;
}
