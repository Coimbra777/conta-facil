import type { ApiStatus } from "@/lib/types";
import { STATUS_LABEL } from "@/lib/types";
import { cn } from "@/lib/utils";

const STYLES: Record<ApiStatus, string> = {
    pending: "bg-status-pending text-status-pending-fg",
    proof_sent: "bg-status-proof text-status-proof-fg",
    validated: "bg-status-paid text-status-paid-fg",
    rejected: "bg-status-rejected text-status-rejected-fg",
};

interface Props {
    status: ApiStatus;
    className?: string;
    size?: "sm" | "md";
}

export function StatusBadge({ status, className, size = "md" }: Props) {
    return (
        <span
            className={cn(
                "inline-flex items-center gap-1.5 border-2 border-foreground rounded-full font-bold uppercase tracking-wider whitespace-nowrap",
                size === "sm" ? "text-[10px] px-2 py-0.5" : "text-xs px-3 py-1",
                STYLES[status],
                className,
            )}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {STATUS_LABEL[status]}
        </span>
    );
}
