import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AuthProvider } from "@/lib/auth";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import Index from "./pages/Index.tsx";
import NotFound from "./pages/NotFound.tsx";
import AuthPage from "./pages/Auth.tsx";
import Dashboard from "./pages/Dashboard.tsx";
import NewExpense from "./pages/NewExpense.tsx";
import ExpenseDetail from "./pages/ExpenseDetail.tsx";
import ExpenseSuccess from "./pages/ExpenseSuccess.tsx";
import PublicExpense from "./pages/PublicExpense.tsx";
import PublicNewExpense from "./pages/PublicNewExpense.tsx";

const queryClient = new QueryClient();

const App = () => (
    <QueryClientProvider client={queryClient}>
        <TooltipProvider>
            <Toaster />
            <Sonner />
            <BrowserRouter>
                <AuthProvider>
                    <Routes>
                        <Route path="/" element={<Index />} />
                        <Route path="/login" element={<AuthPage mode="login" />} />
                        <Route path="/cadastro" element={<AuthPage mode="register" />} />

                        <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
                        <Route path="/cobrancas/nova" element={<ProtectedRoute><NewExpense /></ProtectedRoute>} />
                        <Route path="/cobrancas/:id" element={<ProtectedRoute><ExpenseDetail /></ProtectedRoute>} />
                        <Route path="/cobrancas/:id/sucesso" element={<ProtectedRoute><ExpenseSuccess /></ProtectedRoute>} />

                        <Route path="/p/:hash" element={<PublicExpense />} />
                        <Route path="/cobranca-publica/nova" element={<PublicNewExpense />} />

                        {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
                        <Route path="*" element={<NotFound />} />
                    </Routes>
                </AuthProvider>
            </BrowserRouter>
        </TooltipProvider>
    </QueryClientProvider>
);

export default App;
