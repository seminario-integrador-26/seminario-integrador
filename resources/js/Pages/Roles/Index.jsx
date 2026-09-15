import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ roles }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="titulo-pagina">Roles y permisos</h2>}
        >
            <Head title="Roles y permisos" />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <Link
                    href={route('usuarios.index')}
                    className="enlace text-sm"
                >
                    ← Volver a usuarios
                </Link>

                <div className="grid gap-4 md:grid-cols-3">
                    {roles.map((rol) => (
                        <section key={rol.id} className="panel p-6">
                            <div className="flex items-baseline justify-between gap-3">
                                <h3 className="text-lg font-semibold text-ink">
                                    {rol.name}
                                </h3>
                                <span className="font-mono text-xs text-ink-subtle tabular-nums">
                                    {rol.permisos.length} permiso(s)
                                </span>
                            </div>
                            <ul className="mt-4 flex flex-wrap gap-1.5">
                                {rol.permisos.length === 0 && (
                                    <li className="text-sm text-ink-subtle">
                                        Sin permisos asignados.
                                    </li>
                                )}
                                {rol.permisos.map((p) => (
                                    <li
                                        key={p}
                                        className="rounded bg-surface-2 px-2 py-1 font-mono text-xs text-ink-muted"
                                    >
                                        {p}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>

                <p className="text-xs text-ink-subtle">
                    Vista de solo lectura. La edición de permisos por rol se
                    implementará más adelante.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
