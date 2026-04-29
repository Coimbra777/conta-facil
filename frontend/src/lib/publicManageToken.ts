/** Armazena token de gestão da cobrança pública por hash (URL sempre sem token). */
const STORAGE_PREFIX = "contacerta:public-manage:";

export function publicManageStorageKey(publicHash: string): string {
    return `${STORAGE_PREFIX}${publicHash}`;
}

export function getPublicManageToken(publicHash: string): string | null {
    if (!publicHash) return null;
    try {
        const v = localStorage.getItem(publicManageStorageKey(publicHash));
        return v && v.trim() !== "" ? v : null;
    } catch {
        return null;
    }
}

export function setPublicManageToken(publicHash: string, token: string): void {
    if (!publicHash || !token) return;
    try {
        localStorage.setItem(publicManageStorageKey(publicHash), token);
    } catch {
        /* ignore */
    }
}
