import ApplicationLogo from '@/Components/ApplicationLogo';
import SelectorTema from '@/Components/SelectorTema';
import { Head, Link } from '@inertiajs/react';
import 'leaflet/dist/leaflet.css';
import { MapContainer, TileLayer } from 'react-leaflet';

const CENTRO_VILLA_MARIA = [-32.4075, -63.2402];

const botonPrimario =
    'inline-flex items-center justify-center whitespace-nowrap rounded-md bg-brand px-5 py-2.5 text-sm font-semibold text-brand-ink shadow-sm transition duration-150 hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-canvas active:translate-y-px';

export default function Welcome({ auth, canLogin, canRegister }) {
    return (
        <>
            <Head title="Inicio" />

            <div className="flex min-h-[100dvh] flex-col bg-canvas text-ink">
                <header className="border-b border-line bg-surface">
                    <nav className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <Link href="/" className="flex items-center gap-2.5">
                            <ApplicationLogo className="h-8 w-8" />
                            <span className="text-sm font-semibold">
                                Centro de Monitoreo
                            </span>
                        </Link>

                        <SelectorTema />
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-7xl flex-1 px-4 sm:px-6 lg:px-8">
                    <section className="grid items-center gap-10 py-12 lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] lg:py-20">
                        <div>
                            <h1 className="max-w-xl text-balance text-4xl font-semibold leading-[1.08] tracking-tight md:text-5xl">
                                Gestión de eventos del Sistema de
                                Videovigilancia
                            </h1>

                            <p className="mt-5 max-w-[52ch] text-lg leading-relaxed text-ink-muted">
                                Registro único y centralizado de los eventos
                                detectados por el Centro de Monitoreo de la
                                Municipalidad de Villa María.
                            </p>

                            <div className="mt-8 flex flex-wrap items-center gap-5">
                                {auth?.user ? (
                                    <Link
                                        href={route('dashboard')}
                                        className={botonPrimario}
                                    >
                                        Panel
                                    </Link>
                                ) : (
                                    canLogin && (
                                        <Link
                                            href={route('login')}
                                            className={botonPrimario}
                                        >
                                            Iniciar sesión
                                        </Link>
                                    )
                                )}

                                {!auth?.user && canRegister && (
                                    <Link
                                        href={route('register')}
                                        className="enlace text-sm"
                                    >
                                        Registrarse
                                    </Link>
                                )}
                            </div>
                        </div>

                        <div
                            role="img"
                            aria-label="Mapa de Villa María"
                            className="panel overflow-hidden"
                        >
                            <MapContainer
                                center={CENTRO_VILLA_MARIA}
                                zoom={14}
                                zoomControl={false}
                                dragging={false}
                                scrollWheelZoom={false}
                                doubleClickZoom={false}
                                touchZoom={false}
                                keyboard={false}
                                className="z-0 h-72 w-full sm:h-80 lg:h-[26rem]"
                            >
                                <TileLayer
                                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                />
                            </MapContainer>
                        </div>
                    </section>

                    <section className="grid gap-4 pb-16 md:grid-cols-2">
                        <div className="panel fondo-marca border-transparent bg-brand-deep p-8 text-brand-deep-ink md:row-span-2">
                            <h2 className="text-lg font-semibold">Registro</h2>
                            <p className="mt-3 max-w-[48ch] leading-relaxed text-brand-deep-muted">
                                Cada evento queda asociado al turno de guardia
                                activo y es inmutable: se corrige por fe de
                                errata.
                            </p>
                        </div>

                        <div className="panel p-6">
                            <h2 className="font-semibold">Clasificación</h2>
                            <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                                Prevención, Convivencia Urbana y Seguridad
                                Pública, con ubicación en el mapa cuando el tipo
                                lo requiere.
                            </p>
                        </div>

                        <div className="panel p-6">
                            <h2 className="font-semibold">Notificación</h2>
                            <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                                Aviso automático por WhatsApp a los grupos
                                interesados según la clasificación del evento.
                            </p>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-line">
                    <div className="mx-auto flex max-w-7xl flex-wrap justify-between gap-2 px-4 py-6 text-sm text-ink-subtle sm:px-6 lg:px-8">
                        <span>UTN FRVM · Seminario Integrador</span>
                        <span>Municipalidad de Villa María</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
