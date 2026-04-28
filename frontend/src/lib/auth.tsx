import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from "react";
import { api } from "@/lib/api/client";
import type { User } from "@/lib/types";

interface AuthCtx {
    user: User | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    register: (name: string, email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
}

const Ctx = createContext<AuthCtx | undefined>(undefined);

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
        login: async (email, password) => {
            setUser(await api.login(email, password));
        },
        register: async (name, email, password) => {
            setUser(await api.register(name, email, password));
        },
        logout: async () => {
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
