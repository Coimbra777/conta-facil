import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from "react";
import {
    api,
    AUTH_SESSION_CLEARED_EVENT,
    getToken,
    isDemoMode,
    setDemoMode,
    setToken,
} from "@/lib/api/client";
import { mockApi } from "@/lib/api/mockStore";
import type { LoginPayload, RegisterPayload, User } from "@/lib/types";

type AuthContextValue = {
    user: User | null;
    loading: boolean;
    isAuthenticated: boolean;
    isDemo: boolean;
    login: (payload: LoginPayload) => Promise<void>;
    register: (payload: RegisterPayload) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
    loginDemo: () => Promise<void>;
};

const Ctx = createContext<AuthContextValue | undefined>(undefined);

async function leaveDemoSession(): Promise<void> {
    if (!isDemoMode()) return;
    await mockApi.logout();
    setDemoMode(false);
}

async function restoreRealSessionUser(): Promise<User | null> {
    if (!getToken()) return null;
    try {
        return await api.me();
    } catch {
        return null;
    }
}

async function loadCurrentUser(): Promise<User | null> {
    const hasDemoSession = isDemoMode();
    const hasToken = Boolean(getToken());

    if (!hasDemoSession && !hasToken) {
        return null;
    }

    return api.me();
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);
    const [isDemo, setIsDemo] = useState(() => isDemoMode());

    useEffect(() => {
        let active = true;

        const syncSessionCleared = () => {
            if (!active) return;
            setUser(null);
            setIsDemo(isDemoMode());
            setLoading(false);
        };

        const syncFromStorage = (event: StorageEvent) => {
            if (
                event.key === "contacerta:auth:v1" ||
                event.key === "contacerta:demo:v1"
            ) {
                void refreshUser();
            }
        };

        const refreshUser = async () => {
            if (!active) return;
            setLoading(true);
            try {
                const nextUser = await loadCurrentUser();
                if (!active) return;
                setUser(nextUser);
            } catch {
                if (!active) return;
                setUser(null);
            } finally {
                if (!active) return;
                setIsDemo(isDemoMode());
                setLoading(false);
            }
        };

        window.addEventListener(AUTH_SESSION_CLEARED_EVENT, syncSessionCleared);
        window.addEventListener("storage", syncFromStorage);
        void refreshUser();

        return () => {
            active = false;
            window.removeEventListener(
                AUTH_SESSION_CLEARED_EVENT,
                syncSessionCleared,
            );
            window.removeEventListener("storage", syncFromStorage);
        };
    }, []);

    const refreshUser = async () => {
        setLoading(true);
        try {
            const nextUser = await loadCurrentUser();
            setUser(nextUser);
        } catch {
            setUser(null);
        } finally {
            setIsDemo(isDemoMode());
            setLoading(false);
        }
    };

    const value: AuthContextValue = {
        user,
        loading,
        isAuthenticated: Boolean(user),
        isDemo,
        refreshUser,
        login: async (payload) => {
            const wasDemo = isDemoMode();
            setLoading(true);
            try {
                if (wasDemo) {
                    await leaveDemoSession();
                }
                const { token, user: nextUser } = await api.login(payload);
                if (token) setToken(token);
                setUser(nextUser);
                setIsDemo(isDemoMode());
            } catch (error) {
                if (wasDemo) {
                    setUser(await restoreRealSessionUser());
                    setIsDemo(false);
                }
                throw error;
            } finally {
                setLoading(false);
            }
        },
        register: async (payload) => {
            const wasDemo = isDemoMode();
            setLoading(true);
            try {
                if (wasDemo) {
                    await leaveDemoSession();
                }
                const { token, user: nextUser } = await api.register(payload);
                if (token) setToken(token);
                setUser(nextUser);
                setIsDemo(isDemoMode());
            } catch (error) {
                if (wasDemo) {
                    setUser(await restoreRealSessionUser());
                    setIsDemo(false);
                }
                throw error;
            } finally {
                setLoading(false);
            }
        },
        loginDemo: async () => {
            setLoading(true);
            try {
                const nextUser = await api.enterDemo();
                setUser(nextUser);
                setIsDemo(true);
            } finally {
                setLoading(false);
            }
        },
        logout: async () => {
            setLoading(true);
            try {
                if (isDemoMode()) {
                    await leaveDemoSession();
                    setUser(await restoreRealSessionUser());
                } else {
                    try {
                        await api.logout();
                    } catch {
                        setToken(null);
                    }
                    setUser(null);
                }
                setIsDemo(isDemoMode());
            } finally {
                setLoading(false);
            }
        },
    };

    return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth() {
    const ctx = useContext(Ctx);
    if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
    return ctx;
}
