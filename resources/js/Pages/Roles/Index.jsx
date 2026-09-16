import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ roles }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Roles y permisos
                </h2>
            }
        >
            <Head title="Roles y permisos" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('usuarios.index')}
                        className="text-sm font-medium text-atalaya-cyan hover:text-white"
                    >
                        ← Volver a usuarios
                    </Link>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {roles.map((rol) => (
                            <div
                                key={rol.id}
                                className="bg-atalaya-surface p-6 border border-atalaya-border"
                            >
                                <h3 className="text-lg font-semibold text-white">
                                    {rol.name}
                                </h3>
                                <p className="mt-1 text-xs uppercase tracking-wider text-atalaya-text-dim">
                                    {rol.permisos.length} permiso(s)
                                </p>
                                <ul className="mt-3 space-y-1">
                                    {rol.permisos.length === 0 && (
                                        <li className="text-sm text-atalaya-text-dim">
                                            Sin permisos asignados.
                                        </li>
                                    )}
                                    {rol.permisos.map((p) => (
                                        <li
                                            key={p}
                                            className="rounded bg-atalaya-canvas px-2 py-1 text-sm text-atalaya-text-muted"
                                        >
                                            {p}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>

                    <p className="text-xs text-atalaya-text-dim">
                        Vista de solo lectura. La edición de permisos por rol se
                        implementará más adelante.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
