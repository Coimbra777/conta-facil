import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from "react";
import {
    api,
    isDemoMode,
    setDemoMode,
} from "@/lib/api/client";
import { mockApi } from "@/lib/api/mockStore";
import type { User } from "@/lib/types";

interface AuthCtx {
    user: User | null;
    loading: boolean;
    isDemo: boolean;
    login: (email: string, password: string) => Promise<void>;
    register: (
        name: string,
        email: string,
        password: string,
        passwordConfirmation: string,
    ) => Promise<void>;
    loginDemo: () => Promise<void>;
    logout: () => Promise<void>;
}

const Ctx = createContext<AuthCtx | undefined>(undefined);

async function leaveDemoMock(): Promise<void> {
    if (isDemoMode()) {
        await mockApi.logout();
        setDemoMode(false);
    }
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.me()
            .then((u) => setUser(u))
            .finally(() => setLoading(false));
    }, []);

    const value: AuthCtx = {
        user,
        loading,
        isDemo: isDemoMode(),
        login: async (email, password) => {
            await leaveDemoMock();
            setUser(await api.login(email, password));
        },
        register: async (
            name,
            email,
            password,
            passwordConfirmation,
        ) => {
            await leaveDemoMock();
            setUser(
                await api.register(name, email, password, passwordConfirmation),
            );
        },
        loginDemo: async () => {
            setUser(await api.enterDemo());
        },
        logout: async () => {
            if (isDemoMode()) {
                await mockApi.logout();
                setDemoMode(false);
                const u = await api.me();
                setUser(u);
                return;
            }
            await api.logout();
            setUser(null);
        },
    };
    return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth() {
    const ctx = useContext(Ctx);
    if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
    return ctx;
}
