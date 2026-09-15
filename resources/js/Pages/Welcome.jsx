import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth, canLogin, laravelVersion, phpVersion }) {
    return (
        <>
            <Head title="Inicio" />

            <div className="flex min-h-screen flex-col bg-gray-100 text-gray-900">
                <header className="border-b border-gray-200 bg-white">
                    <nav className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                        <span className="text-lg font-semibold">
                            Centro de Monitoreo
                        </span>

                        <div className="flex items-center gap-4 text-sm">
                            {auth?.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-md px-3 py-2 text-gray-700 underline hover:text-gray-900"
                                >
                                    Panel
                                </Link>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Link
                                            href={route('login')}
                                            className="rounded-md px-3 py-2 text-gray-700 underline hover:text-gray-900"
                                        >
                                            Iniciar sesión
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-7xl flex-1 px-6 py-16">
                    <h1 className="text-3xl font-semibold">
                        Gestión de eventos del Sistema de Videovigilancia
                    </h1>

                    <p className="mt-4 max-w-3xl text-gray-600">
                        Registro único y centralizado de los eventos detectados
                        por el Centro de Monitoreo de la Municipalidad de Villa
                        María: registro, clasificación, localización,
                        notificación y consulta.
                    </p>

                    <dl className="mt-12 grid gap-6 sm:grid-cols-3">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <dt className="font-semibold">Registro</dt>
                            <dd className="mt-2 text-sm text-gray-600">
                                Cada evento queda asociado al turno de guardia
                                activo y es inmutable: se corrige por fe de
                                errata.
                            </dd>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <dt className="font-semibold">Clasificación</dt>
                            <dd className="mt-2 text-sm text-gray-600">
                                Prevención, Convivencia Urbana y Seguridad
                                Pública, con ubicación en el mapa cuando el tipo
                                lo requiere.
                            </dd>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <dt className="font-semibold">Notificación</dt>
                            <dd className="mt-2 text-sm text-gray-600">
                                Aviso automático por WhatsApp a los grupos
                                interesados según la clasificación del evento.
                            </dd>
                        </div>
                    </dl>
                </main>

                <footer className="mx-auto w-full max-w-7xl px-6 py-8 text-sm text-gray-500">
                    UTN FRVM — Seminario Integrador · Laravel v{laravelVersion}{' '}
                    (PHP v{phpVersion})
                </footer>
            </div>
        </>
    );
}
