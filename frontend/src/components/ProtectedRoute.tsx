import { Navigate, useLocation } from "react-router-dom";
import { getSafeRedirect } from "@/lib/api/client";
import { useAuth } from "@/lib/auth";

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
    const { user, loading } = useAuth();
    const loc = useLocation();

    if (loading) {
        return (
            <div className="min-h-dvh grid place-items-center bg-background">
                <div className="border-4 border-foreground bg-card rounded-2xl px-6 py-4 font-bold brutal-shadow-sm">
                    Carregando…
                </div>
            </div>
        );
    }
    if (!user) {
        const redirect = getSafeRedirect(`${loc.pathname}${loc.search}`);
        return (
            <Navigate
                to={`/login?redirect=${encodeURIComponent(redirect)}`}
                replace
            />
        );
    }
    return <>{children}</>;
}
