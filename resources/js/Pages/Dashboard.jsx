import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const roles = auth.roles ?? [];
    const permisos = auth.permissions ?? [];

    const accesos = [
        permisos.includes('eventos.consultar') && {
            ruta: 'eventos.index',
            titulo: 'Eventos',
            texto: 'Consultá los eventos registrados y filtralos por tipo, ubicación o fecha.',
        },
        roles.includes('Supervisor') && {
            ruta: 'eventos.create',
            titulo: 'Registrar evento',
            texto: 'Cargá un evento en el turno de guardia abierto.',
        },
        roles.includes('Administrativo') && {
            ruta: 'usuarios.index',
            titulo: 'Usuarios',
            texto: 'Administrá las cuentas y los roles del sistema.',
        },
    ].filter(Boolean);

    return (
        <AuthenticatedLayout header={<h2 className="titulo-pagina">Panel</h2>}>
            <Head title="Panel" />

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                    <section className="panel fondo-marca border-transparent bg-brand-deep p-6 text-brand-deep-ink">
                        <p className="text-sm text-brand-deep-muted">
                            Sesión iniciada como
                        </p>
                        <p className="mt-1 text-xl font-semibold tracking-tight">
                            {auth.user.name}
                        </p>
                        {roles[0] && (
                            <p className="mt-4 inline-flex rounded border border-brand-deep-ink/20 px-2 py-0.5 text-xs font-medium">
                                {roles[0]}
                            </p>
                        )}
                    </section>

                    <section className="panel">
                        <h3 className="border-b border-line px-6 py-4 text-sm font-semibold text-ink">
                            Accesos
                        </h3>

                        {accesos.length === 0 ? (
                            <p className="px-6 py-8 text-sm text-ink-muted">
                                Todavía no hay secciones disponibles para tu
                                rol.
                            </p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {accesos.map((a) => (
                                    <li key={a.ruta}>
                                        <Link
                                            href={route(a.ruta)}
                                            className="group flex items-center justify-between gap-6 px-6 py-4 transition-colors duration-150 hover:bg-surface-2/60 focus-visible:bg-surface-2 focus-visible:outline-none"
                                        >
                                            <span>
                                                <span className="block font-medium text-ink">
                                                    {a.titulo}
                                                </span>
                                                <span className="mt-0.5 block text-sm text-ink-muted">
                                                    {a.texto}
                                                </span>
                                            </span>
                                            <span
                                                aria-hidden="true"
                                                className="text-ink-subtle transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-accent"
                                            >
                                                →
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
