import { Head } from '@inertiajs/react';
import {
    FileText,
    FileBadge,
    FileWarning,
    ShieldCheck,
    Mail,
    Globe,
    MessageCircle,
} from 'lucide-react';
import { useState, useEffect } from 'react';

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

/** Style map for document type badges & accents */
const getDocTypeStyles = (type: string) => {
    const lower = type.toLowerCase();

    if (
        lower.includes('seguridad') ||
        lower.includes('msds') ||
        lower.includes('sds')
    ) {
        return {
            border: 'border-l-bepro-safety',
            bg: 'bg-bepro-safety-bg',
            text: 'text-bepro-safety',
            label: 'Seguridad (MSDS)',
            icon: <FileWarning className="h-5 w-5" />,
        };
    }

    if (
        lower.includes('certificado') ||
        lower.includes('calidad') ||
        lower.includes('coa')
    ) {
        return {
            border: 'border-l-bepro-cert',
            bg: 'bg-bepro-cert-bg',
            text: 'text-bepro-cert',
            label: 'Certificado',
            icon: <FileBadge className="h-5 w-5" />,
        };
    }

    return {
        border: 'border-l-bepro-tech',
        bg: 'bg-bepro-tech-bg',
        text: 'text-bepro-tech',
        label: 'Ficha Técnica',
        icon: <FileText className="h-5 w-5" />,
    };
};

export default function PublicQrLandingShow({
    product,
    lot,
    documents,
}: Props) {
    const whatsappNumber = '+573188757659';
    const whatsappMessage = encodeURIComponent(
        `Hola, necesito asesoría sobre el producto ${product.name} (Lote: ${lot.number}).`,
    );
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${whatsappMessage}`;

    // Header scroll contraction effect (mobile only)
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            if (window.innerWidth < 768) {
                setScrolled(window.scrollY > 20);
            } else {
                setScrolled(false);
            }
        };

        window.addEventListener('scroll', handleScroll);
        window.addEventListener('resize', handleScroll);
        handleScroll();

        return () => {
            window.removeEventListener('scroll', handleScroll);
            window.removeEventListener('resize', handleScroll);
        };
    }, []);

    return (
        <>
            <Head title={`${product.name} - Documentación`} />
            <main className="min-h-screen font-bepro-sans text-bepro-text antialiased">
                {/* ── HEADER ─────────────────────────────────────── */}
                <header
                    className="sticky top-0 z-40 border-b border-gray-100 bg-white transition-all duration-300 ease-in-out"
                    style={{ height: scrolled ? '56px' : '112px' }}
                >
                    <div className="mx-auto flex h-full max-w-3xl items-center justify-between px-6">
                        <img
                            src="/images/beprocoatings.png"
                            alt="BePro Coatings"
                            className="w-auto object-contain transition-all duration-300 ease-in-out"
                            style={{ height: scrolled ? '36px' : '80px' }}
                        />
                        <nav className="flex items-center gap-5">
                            <a
                                href="#documentos"
                                className="text-sm font-medium text-bepro-text/70 transition-colors hover:text-bepro-primary"
                            >
                                Documentos
                            </a>
                            <a
                                href="#contacto"
                                className="rounded-lg bg-bepro-action px-4 py-2 text-sm font-semibold text-white transition-colors duration-200 hover:bg-bepro-action-hover"
                            >
                                Contacto
                            </a>
                        </nav>
                    </div>
                </header>

                {/* ── HERO ────────────────────────────────────────── */}
                <div className="border-b border-gray-100 bg-white">
                    <div className="mx-auto max-w-3xl px-6 py-14">
                        {/* Authenticity Badge */}
                        <div className="mb-6 inline-flex items-center gap-1.5 rounded-md bg-bepro-tech-bg px-3 py-1 text-[10px] font-bold tracking-wider text-bepro-tech uppercase">
                            <ShieldCheck className="h-3.5 w-3.5 animate-pulse" />
                            Garantía de Autenticidad
                        </div>

                        {/* Product Name — largest, boldest element */}
                        <h1 className="mb-4 text-4xl leading-[1.1] font-black tracking-tight text-bepro-primary sm:text-5xl">
                            {product.name}
                        </h1>

                        {product.description && (
                            <p className="mb-10 max-w-2xl text-base leading-relaxed text-bepro-text/80">
                                {product.description}
                            </p>
                        )}
                        {!product.description && <div className="mb-8" />}

                        {/* ── LOT PASSPORT ────────────────────────── */}
                        <div className="relative max-w-xl overflow-hidden rounded-xl border border-bepro-primary/15 bg-slate-50/80 shadow-[inset_0_1px_3px_rgba(0,44,66,0.06)]">
                            {/* Top bar — high-security label feel */}
                            <div className="flex items-center justify-between border-b border-dashed border-bepro-primary/10 bg-bepro-primary/[0.03] px-5 py-3">
                                <span className="text-[10px] font-bold tracking-[0.25em] text-bepro-primary/50 uppercase">
                                    Pasaporte de Lote
                                </span>
                                <div className="flex items-center gap-1.5 text-[9px] font-bold tracking-wider text-bepro-accent/70 uppercase">
                                    <ShieldCheck className="h-3 w-3" />
                                    Verificado
                                </div>
                            </div>

                            {/* Lot content */}
                            <div className="px-5 py-6">
                                {/* Lot Number — most prominent element in the passport */}
                                <div className="mb-5">
                                    <span className="mb-2 block text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                        N.° de Lote
                                    </span>
                                    <span className="inline-block rounded-lg border-2 border-bepro-primary/20 bg-white px-4 py-2 font-bepro-mono text-2xl font-black tracking-widest text-bepro-primary shadow-sm sm:text-3xl">
                                        {lot.number}
                                    </span>
                                </div>

                                {/* Dates row */}
                                <div className="grid grid-cols-2 gap-6 border-t border-dashed border-slate-200 pt-5">
                                    <div>
                                        <span className="mb-1.5 block text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                            Fabricación
                                        </span>
                                        <span className="font-bepro-mono text-sm font-semibold text-bepro-primary tabular-nums">
                                            {lot.manufacturing_date ?? '—'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="mb-1.5 block text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                            Verificación
                                        </span>
                                        <span className="font-bepro-mono text-sm font-semibold text-bepro-primary tabular-nums">
                                            {lot.verification_date ?? '—'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── CONTENT ─────────────────────────────────────── */}
                <div className="min-h-screen bg-bepro-bg pb-16">
                    <div className="mx-auto max-w-3xl space-y-14 px-6 py-12">
                        {/* Documents Section */}
                        <section id="documentos" className="space-y-6">
                            <div>
                                <h2 className="flex items-center gap-2 text-sm font-bold tracking-widest text-bepro-primary uppercase">
                                    <span className="inline-block h-5 w-1 rounded-full bg-bepro-accent" />
                                    Documentos de Calidad
                                </h2>
                                <p className="mt-2 text-sm text-bepro-text/70">
                                    Descarga los certificados y fichas de
                                    seguridad oficiales de este lote.
                                </p>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {documents.map((document) => {
                                    const docStyle = getDocTypeStyles(
                                        document.type,
                                    );

                                    return (
                                        <div
                                            key={`${document.type}-${document.id}`}
                                            className={`group flex flex-col justify-between rounded-xl border border-l-4 border-slate-200/60 ${docStyle.border} bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.03)] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg`}
                                        >
                                            <div>
                                                {/* Type badge + icon */}
                                                <div className="mb-4 flex items-start justify-between">
                                                    <div
                                                        className={`flex h-10 w-10 items-center justify-center rounded-lg ${docStyle.bg} ${docStyle.text}`}
                                                    >
                                                        {docStyle.icon}
                                                    </div>
                                                    <span
                                                        className={`rounded px-2 py-0.5 text-[9px] font-extrabold tracking-wider uppercase ${docStyle.bg} ${docStyle.text}`}
                                                    >
                                                        {docStyle.label}
                                                    </span>
                                                </div>

                                                <h3
                                                    className="mb-1 line-clamp-2 text-sm leading-snug font-bold text-bepro-primary"
                                                    title={document.name}
                                                >
                                                    {document.name}
                                                </h3>
                                                <p className="text-xs text-slate-400">
                                                    PDF · {document.size}
                                                </p>
                                            </div>

                                            <a
                                                href={document.download_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-5 flex items-center justify-center gap-2 rounded-lg bg-bepro-action px-4 py-2.5 text-xs font-bold text-white transition-colors duration-200 hover:bg-bepro-action-hover"
                                            >
                                                Visualizar PDF
                                            </a>
                                        </div>
                                    );
                                })}

                                {documents.length === 0 && (
                                    <div className="col-span-full rounded-xl border border-dashed border-slate-200 bg-white p-14 text-center">
                                        <FileText className="mx-auto mb-3 h-8 w-8 text-slate-300" />
                                        <p className="text-sm font-bold text-bepro-primary">
                                            Sin archivos cargados
                                        </p>
                                        <p className="mt-1 text-xs text-slate-400">
                                            Este lote aún no cuenta con
                                            documentos publicados.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Contact Section */}
                        <section
                            id="contacto"
                            className="grid gap-5 md:grid-cols-2"
                        >
                            {/* WhatsApp CTA */}
                            <div className="flex flex-col justify-between rounded-xl bg-white p-8 shadow-[0_1px_3px_rgba(0,0,0,0.03)]">
                                <div>
                                    <p className="mb-2 text-[13px] font-bold tracking-widest text-bepro-accent uppercase">
                                        Soporte Técnico
                                    </p>
                                    <h3 className="mb-3 text-xl leading-snug font-bold text-bepro-primary">
                                        ¿Dudas con la aplicación?
                                    </h3>
                                    <p className="mb-7 max-w-xs text-xs leading-relaxed text-bepro-text/80">
                                        Habla directamente con nuestro
                                        departamento técnico para recibir
                                        asesoría de uso y rendimiento en obra.
                                    </p>
                                </div>
                                <a
                                    href={whatsappUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center justify-center gap-2 self-start rounded-lg bg-[#25D366] px-5 py-2.5 text-xs font-bold text-white shadow-[0_4px_14px_rgba(37,211,102,0.2)] transition-all duration-200 hover:scale-[1.02] hover:shadow-lg"
                                >
                                    <MessageCircle className="h-4 w-4" />
                                    WhatsApp
                                </a>
                            </div>

                            {/* Direct contact */}
                            <div className="flex flex-col justify-between rounded-xl border border-slate-200/60 bg-white p-8 shadow-[0_1px_3px_rgba(0,0,0,0.03)]">
                                <div>
                                    <p className="mb-4 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                                        Contacto Comercial
                                    </p>
                                    <div className="space-y-2.5">
                                        <a
                                            href="mailto:info@pintech.com.co"
                                            className="flex items-center gap-3 rounded-lg bg-bepro-bg p-3.5 text-xs font-bold text-bepro-primary transition-all duration-200 hover:bg-bepro-primary hover:text-white"
                                        >
                                            <Mail className="h-4 w-4 shrink-0 text-bepro-accent" />
                                            info@pintech.com.co
                                        </a>
                                        <a
                                            href="https://beprocoatings.com"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-3 rounded-lg bg-bepro-bg p-3.5 text-xs font-bold text-bepro-primary transition-all duration-200 hover:bg-bepro-primary hover:text-white"
                                        >
                                            <Globe className="h-4 w-4 shrink-0 text-bepro-accent" />
                                            beprocoatings.com
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                {/* ── FOOTER ──────────────────────────────────────── */}
                <footer className="border-t border-gray-100 bg-white px-6 py-10">
                    <div className="mx-auto flex max-w-3xl flex-col items-center justify-between gap-4 text-[10px] font-semibold text-slate-400 sm:flex-row">
                        <div className="flex flex-col gap-1 text-center sm:text-left">
                            <span className="font-bold text-bepro-primary">
                                PINTECH COLOMBIA S.A.S.
                            </span>
                            <span>
                                NIT 901123507-9 · Palmira, Valle del Cauca
                            </span>
                        </div>
                        <div className="flex flex-col items-center gap-1 sm:items-end">
                            <div className="flex gap-3 text-slate-400">
                                <a
                                    href="https://pintech.com.co"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="transition-colors hover:text-bepro-accent"
                                >
                                    Tratamiento de Datos
                                </a>
                                <span>·</span>
                                <a
                                    href="https://beprocoatings.com"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="transition-colors hover:text-bepro-accent"
                                >
                                    Términos y Condiciones
                                </a>
                            </div>
                            <span>
                                © {new Date().getFullYear()} · Todos los
                                derechos reservados
                            </span>
                        </div>
                    </div>
                </footer>
            </main>
        </>
    );
}
