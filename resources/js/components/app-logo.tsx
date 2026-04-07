export default function AppLogo() {
    return (
        <>
            <div className="flex size-10 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/10">
                <img
                    src="/images/logo-pintech.png"
                    alt="Pintech logo"
                    className="size-6 object-contain"
                />
            </div>
            <div className="ml-1.5 grid flex-1 text-left leading-tight">
                <span className="truncate text-[11px] tracking-[0.22em] text-sidebar-foreground/55 uppercase">
                    Pintech OS
                </span>
                <span className="truncate text-sm font-semibold text-sidebar-foreground">
                    Industrial Control
                </span>
            </div>
        </>
    );
}
