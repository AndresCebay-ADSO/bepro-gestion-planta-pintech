import { Head } from '@inertiajs/react';
import { Download, Factory, FileText, Package } from 'lucide-react';

type Product = {
    name: string;
    description: string | null;
};

type Lot = {
    number: string;
    manufacturing_date: string | null;
    verification_date: string | null;
};

type DocumentItem = {
    id: number;
    name: string;
    type: string;
    size: string;
    date: string | null;
    download_url: string;
};

type Props = {
    product: Product;
    lot: Lot;
    documents: DocumentItem[];
};

export default function PublicQrLandingShow({
    product,
    lot,
    documents,
}: Props) {
    return (
        <>
            <Head title={`${product.name} - Documentación`} />
            <main className="min-h-screen bg-muted/35 text-foreground antialiased">
                <header className="border-b border-border bg-card shadow-sm">
                    <div className="mx-auto flex max-w-5xl items-center gap-4 px-5 py-6">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md">
                            <Factory className="h-7 w-7" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold tracking-tight text-foreground">
                                PINTECH
                            </p>
                            <p className="text-xs font-medium tracking-[0.22em] text-muted-foreground uppercase">
                                Ficha de producto · lote
                            </p>
                        </div>
                    </div>
                </header>

                <div className="mx-auto max-w-5xl space-y-6 px-5 py-8">
                    <section className="rounded-xl border border-border bg-card p-6 shadow-sm md:p-8">
                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div className="min-w-0 flex-1">
                                <h1 className="text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                                    {product.name}
                                </h1>
                                {product.description && (
                                    <p className="mt-3 max-w-3xl text-lg leading-relaxed text-muted-foreground">
                                        {product.description}
                                    </p>
                                )}
                            </div>
                        </div>

                        <dl className="mt-8 grid gap-6 border-t border-border pt-8 md:grid-cols-3">
                            <div className="space-y-1">
                                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Lote / orden
                                </dt>
                                <dd className="flex items-center gap-2 text-xl font-semibold tracking-tight text-foreground">
                                    <Package
                                        className="h-5 w-5 shrink-0 text-primary"
                                        aria-hidden
                                    />
                                    {lot.number}
                                </dd>
                            </div>
                            <div className="space-y-1">
                                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Fabricación
                                </dt>
                                <dd className="text-xl font-semibold tracking-tight text-foreground">
                                    {lot.manufacturing_date ?? '—'}
                                </dd>
                            </div>
                            <div className="space-y-1">
                                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Verificación
                                </dt>
                                <dd className="text-xl font-semibold tracking-tight text-foreground">
                                    {lot.verification_date ?? '—'}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section className="rounded-xl border border-border bg-card p-6 shadow-sm md:p-8">
                        <div className="border-b border-border pb-4">
                            <h2 className="text-xl font-semibold tracking-tight text-foreground md:text-2xl">
                                Documentación técnica
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Descargas ofrecidas para este lote. Los archivos
                                pueden incluir certificados del lote y
                                documentos del producto.
                            </p>
                        </div>

                        <div className="mt-6 space-y-3">
                            {documents.map((document) => (
                                <a
                                    key={`${document.type}-${document.id}`}
                                    href={document.download_url}
                                    className="group flex items-center justify-between gap-4 rounded-lg border border-border bg-background/60 p-4 transition hover:border-primary/40 hover:bg-primary/5"
                                >
                                    <span className="flex min-w-0 items-center gap-4">
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition group-hover:bg-primary/15">
                                            <FileText className="h-6 w-6" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-lg font-semibold text-foreground">
                                                {document.name}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {document.type} ·{' '}
                                                {document.size} ·{' '}
                                                {document.date ?? '—'}
                                            </span>
                                        </span>
                                    </span>
                                    <Download
                                        className="h-6 w-6 shrink-0 text-muted-foreground transition group-hover:text-primary"
                                        aria-hidden
                                    />
                                </a>
                            ))}

                            {documents.length === 0 && (
                                <div className="rounded-lg border border-dashed border-border bg-muted/25 px-6 py-10 text-center">
                                    <FileText
                                        className="mx-auto mb-3 h-10 w-10 text-muted-foreground/70"
                                        aria-hidden
                                    />
                                    <p className="text-sm font-medium text-foreground">
                                        No hay documentos disponibles
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Para este lote aún no hay PDFs
                                        publicados. Consulte con el fabricante
                                        si necesita la ficha técnica o la hoja
                                        de seguridad.
                                    </p>
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                <footer className="px-5 pt-2 pb-10 text-center text-sm text-muted-foreground">
                    PINTECH © {new Date().getFullYear()}
                </footer>
            </main>
        </>
    );
}
