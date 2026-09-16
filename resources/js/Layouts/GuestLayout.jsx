import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-atalaya-canvas p-4 font-sans text-atalaya-text-primary selection:bg-atalaya-cyan selection:text-atalaya-canvas">
            <div className="mb-6 flex flex-col items-center text-center">
                <Link href="/" className="group flex flex-col items-center">
                    <ApplicationLogo className="mb-3 h-16 w-16 transition-transform duration-200 group-hover:scale-105" />
                    <span className="font-mono text-xl font-black tracking-widest text-white">
                        ATALAYA
                    </span>
                    <span className="font-mono text-[11px] uppercase tracking-wider text-atalaya-cyan">
                        Centro de Monitoreo Urbano · Villa María
                    </span>
                </Link>
            </div>

            <div className="w-full max-w-md border border-atalaya-border bg-atalaya-surface p-6 shadow-2xl">
                {children}
            </div>

            <div className="mt-8 text-center font-mono text-[10px] uppercase tracking-wider text-atalaya-text-dim">
                Sistema restringido · Acceso exclusivo personal autorizado
            </div>
        </div>
    );
}
