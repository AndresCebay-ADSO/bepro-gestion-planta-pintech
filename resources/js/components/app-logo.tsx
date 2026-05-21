/**
 * AppLogo Component
 * Uses the official Pintech brand logo (2026 version)
 * Logo files located in: /public/images/logo-pintech.svg
 * For brand guidelines, see: /docs/LOGOS.md
 */
export default function AppLogo() {
    return (
        <div className="flex min-w-0 items-center gap-2">
            <div className="flex aspect-square size-12 items-center justify-center rounded-lg bg-sidebar-primary/10">
                <img
                    src="/images/logo-icon.svg?v=1.1"
                    alt="Pintech icon"
                    width={40}
                    height={40}
                    className="size-9 object-contain"
                />
            </div>

            <div className="grid min-w-0 flex-1 text-left leading-tight">
                <span className="truncate font-semibold text-sidebar-foreground">
                    Pintech Colombia
                </span>
                <span className="truncate text-xs text-sidebar-foreground/70">
                    Industrial Control OS
                </span>
            </div>
        </div>
    );
}
