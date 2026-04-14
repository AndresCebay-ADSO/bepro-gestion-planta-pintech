/**
 * AppLogo Component
 * Uses the official Pintech brand logo (2026 version)
 * Logo files located in: /public/images/logo-pintech.{png,svg}
 * For brand guidelines, see: /docs/LOGOS.md
 */
export default function AppLogo() {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <img
                src="/images/logo-pintech.png"
                alt="Pintech logo"
                className="h-20 w-20 shrink-0 object-contain"
            />
            <div className="grid min-w-0 flex-1 text-left leading-tight">
                <span className="truncate text-[11px] tracking-[0.22em] text-sidebar-foreground/55 uppercase">
                    PINTECH OS
                </span>
                <span className="truncate text-sm font-bold text-sidebar-foreground">
                    Industrial Control
                </span>
            </div>
        </div>
    );
}
