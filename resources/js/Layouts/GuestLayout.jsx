import ApplicationLogo from '@/Components/ApplicationLogo';
import SelectorTema from '@/Components/SelectorTema';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="grid min-h-[100dvh] bg-canvas lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <aside className="fondo-marca hidden flex-col justify-between bg-brand-deep p-10 text-brand-deep-ink lg:flex">
                <Link href="/" className="flex items-center gap-3">
                    <ApplicationLogo invertido className="h-9 w-9" />
                    <span className="font-semibold">Centro de Monitoreo</span>
                </Link>

                <div className="max-w-sm">
                    <p className="text-2xl font-semibold leading-snug tracking-tight">
                        Registro único de los eventos del sistema de
                        videovigilancia.
                    </p>
                    <p className="mt-3 text-sm text-brand-deep-muted">
                        Municipalidad de Villa María
                    </p>
                </div>

                <p className="text-xs text-brand-deep-muted">
                    UTN FRVM · Seminario Integrador
                </p>
            </aside>

            <div className="flex flex-col px-4 py-5 sm:px-8">
                <div className="flex items-center justify-between lg:justify-end">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5 lg:hidden"
                    >
                        <ApplicationLogo className="h-8 w-8" />
                        <span className="text-sm font-semibold text-ink">
                            Centro de Monitoreo
                        </span>
                    </Link>
                    <SelectorTema />
                </div>

                <main className="flex flex-1 items-center justify-center py-10">
                    <div className="w-full max-w-sm">{children}</div>
                </main>
            </div>
        </div>
    );
}
