import type { Ref } from "react";
import { maskMoneyTyping } from "@/lib/inputMasks";
import { cn } from "@/lib/utils";

type Props = {
    value: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    id?: string;
    className?: string;
    inputRef?: Ref<HTMLInputElement>;
    "aria-invalid"?: boolean;
};

/**
 * Valores em real (BR): prefixo R$, máscara por centavos (ex.: 1.234,56).
 */
export function CurrencyBrInput({
    value,
    onValueChange,
    placeholder = "0,00",
    id,
    className,
    inputRef,
    "aria-invalid": ariaInvalid,
}: Props) {
    return (
        <div className={cn("relative", className)}>
            <span
                className="pointer-events-none absolute left-[0.85rem] top-1/2 -translate-y-1/2 text-sm font-bold tabular-nums text-foreground/50"
                aria-hidden
            >
                R$
            </span>
            <input
                ref={inputRef}
                id={id}
                type="text"
                autoComplete="off"
                className={cn(
                    "brutal-input tabular-nums w-full",
                    ariaInvalid && "border-status-rejected-fg bg-status-rejected/20",
                )}
                style={{ paddingLeft: "2.75rem" }}
                inputMode="numeric"
                aria-invalid={ariaInvalid}
                value={value}
                onChange={(e) => onValueChange(maskMoneyTyping(e.target.value))}
                placeholder={placeholder}
            />
        </div>
    );
}
