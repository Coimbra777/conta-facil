import type { ReactNode } from "react";

interface Props {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}

export function EmptyState({ icon, title, description, action }: Props) {
    return (
        <div className="border-4 border-dashed border-foreground rounded-2xl bg-card p-10 text-center flex flex-col items-center gap-4">
            {icon && (
                <div className="size-14 rounded-2xl border-4 border-foreground bg-arcade-yellow flex items-center justify-center brutal-shadow-sm">
                    {icon}
                </div>
            )}
            <div className="space-y-1">
                <h3 className="font-display text-2xl">{title}</h3>
                {description && (
                    <p className="text-muted-foreground max-w-md mx-auto">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}
