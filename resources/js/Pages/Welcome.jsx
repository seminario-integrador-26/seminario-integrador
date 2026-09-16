import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Welcome({ auth }) {
    const [hora, setHora] = useState('');

    useEffect(() => {
        const actualizar = () =>
            setHora(
                new Date().toLocaleTimeString('es-AR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                }),
            );

        actualizar();
        const intervalo = setInterval(actualizar, 1000);

        return () => clearInterval(intervalo);
    }, []);

    return (
        <>
            <Head title="Atalaya — Centro de Monitoreo Urbano" />

            <div className="flex min-h-screen flex-col bg-atalaya-canvas font-sans text-atalaya-text-primary selection:bg-atalaya-cyan selection:text-atalaya-canvas">
                <header className="sticky top-0 z-20 flex items-center justify-between border-b border-atalaya-border bg-atalaya-surface px-4 py-3.5 sm:px-8">
                    <div className="flex items-center gap-3">
                        <ApplicationLogo className="h-9 w-9" />
                        <div className="flex flex-col">
                            <span className="font-mono text-base font-black tracking-widest text-white">
                                ATALAYA
                            </span>
                            <span className="font-mono text-[10px] uppercase tracking-wider text-atalaya-text-muted">
                                Centro de Monitoreo Urbano · Villa María
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="hidden items-center gap-3 font-mono text-xs sm:flex">
                            <div className="flex items-center gap-1.5 border border-atalaya-border bg-atalaya-canvas px-2.5 py-1">
                                <span className="h-2 w-2 bg-green-400"></span>
                                <span className="text-[11px] uppercase text-atalaya-text-muted">
                                    Sistema en línea
                                </span>
                            </div>
                            <div className="border border-atalaya-border bg-atalaya-canvas px-2.5 py-1 text-[11px] text-atalaya-cyan">
                                <span className="mr-1 text-atalaya-text-dim">
                                    UTC-3
                                </span>
                                <span>{hora || '00:00:00'}</span>
                            </div>
                        </div>

                        <Link
                            href={auth.user ? route('dashboard') : route('login')}
                            className="border border-atalaya-cyan bg-atalaya-cyan px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider text-black transition hover:bg-cyan-300"
                        >
                            {auth.user
                                ? '[ Ir al panel ]'
                                : '[ Acceso a terminal ]'}
                        </Link>
                    </div>
                </header>

                <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col justify-center px-4 py-10 sm:px-8">
                    <div className="relative mb-8 overflow-hidden border border-atalaya-border bg-atalaya-surface p-6 sm:p-10">
                        <div className="pointer-events-none absolute right-0 top-0 p-4 font-mono text-[10px] uppercase tracking-widest text-atalaya-text-dim">
                            SEC // SOC-TELEMETRY-NODE
                        </div>

                        <div className="mb-3 flex items-center gap-2 font-mono text-xs uppercase tracking-widest text-atalaya-orange">
                            <span className="h-2 w-2 bg-atalaya-orange"></span>
                            <span>
                                Sistema de Videovigilancia Municipal · UTN FRVM
                            </span>
                        </div>

                        <h1 className="max-w-3xl font-mono text-3xl font-black uppercase leading-tight tracking-tight text-white sm:text-5xl">
                            Plataforma de registro y telemetría de eventos
                        </h1>

                        <p className="mt-4 max-w-2xl font-sans text-sm leading-relaxed text-atalaya-text-muted sm:text-base">
                            Registro único y centralizado de los eventos
                            detectados por el Centro de Monitoreo de la Ciudad
                            de Villa María: clasificación, localización
                            cartográfica, notificación y consulta.
                        </p>

                        <div className="mt-8">
                            <Link
                                href={
                                    auth.user
                                        ? route('dashboard')
                                        : route('login')
                                }
                                className="border border-atalaya-cyan bg-atalaya-cyan px-6 py-3 font-mono text-xs font-bold uppercase tracking-widest text-black shadow-[0_0_15px_rgba(0,210,255,0.2)] transition hover:bg-cyan-300"
                            >
                                [ Ingresar al centro de control ]
                            </Link>
                        </div>
                    </div>

                    {/* Reglas de negocio que definen la plataforma */}
                    <div className="swiss-grid mb-10 grid-cols-1 md:grid-cols-3">
                        <div className="swiss-panel flex flex-col justify-between p-6">
                            <div>
                                <div className="mb-2 font-mono text-xs font-bold uppercase tracking-wider text-atalaya-cyan">
                                    [ Regla RN-01 ]
                                </div>
                                <h2 className="mb-2 font-mono text-base font-bold uppercase text-white">
                                    Eventos inmutables y fe de errata
                                </h2>
                                <p className="font-sans text-xs leading-relaxed text-atalaya-text-muted">
                                    Los registros no admiten edición ni borrado.
                                    Toda corrección se asienta como una fe de
                                    errata que referencia al evento original.
                                </p>
                            </div>
                            <div className="mt-4 border-t border-atalaya-border/40 pt-3 font-mono text-[11px] text-atalaya-text-dim">
                                Trazabilidad y auditoría
                            </div>
                        </div>

                        <div className="swiss-panel flex flex-col justify-between p-6">
                            <div>
                                <div className="mb-2 font-mono text-xs font-bold uppercase tracking-wider text-atalaya-orange">
                                    [ Clasificación obligatoria ]
                                </div>
                                <h2 className="mb-2 font-mono text-base font-bold uppercase text-white">
                                    Prevención · Convivencia · Seguridad
                                </h2>
                                <p className="font-sans text-xs leading-relaxed text-atalaya-text-muted">
                                    Cada evento se clasifica al registrarse y se
                                    georreferencia con OpenStreetMap sobre los
                                    puntos de monitoreo cuando el tipo lo
                                    requiere.
                                </p>
                            </div>
                            <div className="mt-4 border-t border-atalaya-border/40 pt-3 font-mono text-[11px] text-atalaya-text-dim">
                                Notificación automática por WhatsApp
                            </div>
                        </div>

                        <div className="swiss-panel flex flex-col justify-between p-6">
                            <div>
                                <div className="mb-2 font-mono text-xs font-bold uppercase tracking-wider text-white">
                                    [ Reportes y datos abiertos ]
                                </div>
                                <h2 className="mb-2 font-mono text-base font-bold uppercase text-white">
                                    Libro de guardia digital
                                </h2>
                                <p className="font-sans text-xs leading-relaxed text-atalaya-text-muted">
                                    Reporte de turno en PDF/A para archivo a
                                    largo plazo, exportación .xlsx en UTF-8 y API
                                    pública de solo lectura versionada (/api/v1).
                                </p>
                            </div>
                            <div className="mt-4 border-t border-atalaya-border/40 pt-3 font-mono text-[11px] text-atalaya-text-dim">
                                Laravel Sanctum · REST API v1
                            </div>
                        </div>
                    </div>
                </main>

                <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-atalaya-border bg-atalaya-surface px-4 py-4 font-mono text-xs text-atalaya-text-dim sm:px-8">
                    <div>ATALAYA © {new Date().getFullYear()}</div>
                    <div className="flex items-center gap-4 text-[11px]">
                        <span>PostgreSQL</span>
                        <span>Laravel + Inertia</span>
                        <span>React</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
