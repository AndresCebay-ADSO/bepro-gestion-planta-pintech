import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const alertVariants = cva(
  "relative w-full rounded-lg border px-4 py-3 text-sm grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current",
  {
    variants: {
      variant: {
        default: "bg-background text-foreground",
        // DESIGN.md §3 y §4: relleno al 10 % y texto con `*-text` (contraste >= 4.5:1 sobre su tinte en claro y
        // oscuro). `*-foreground` es el texto sobre el relleno sólido, nunca sobre el tinte.
        success:
          "border-success/25 bg-success/10 text-success-text *:data-[slot=alert-description]:text-success-text",
        warning:
          "border-warning/25 bg-warning/10 text-warning-text *:data-[slot=alert-description]:text-warning-text",
        info:
          "border-info/25 bg-info/10 text-info-text *:data-[slot=alert-description]:text-info-text",
        destructive:
          "border-destructive/25 bg-destructive/10 text-destructive-text *:data-[slot=alert-description]:text-destructive-text",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Alert({
  className,
  variant,
  ...props
}: React.ComponentProps<"div"> & VariantProps<typeof alertVariants>) {
  return (
    <div
      data-slot="alert"
      role="alert"
      className={cn(alertVariants({ variant }), className)}
      {...props}
    />
  )
}

function AlertTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-title"
      className={cn(
        "col-start-2 line-clamp-1 min-h-4 font-medium tracking-tight",
        className
      )}
      {...props}
    />
  )
}

function AlertDescription({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-description"
      className={cn(
        "text-muted-foreground col-start-2 grid justify-items-start gap-1 text-sm [&_p]:leading-relaxed",
        className
      )}
      {...props}
    />
  )
}

export { Alert, AlertTitle, AlertDescription }
