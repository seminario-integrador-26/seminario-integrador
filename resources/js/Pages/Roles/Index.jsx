import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ roles }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Roles y permisos
                </h2>
            }
        >
            <Head title="Roles y permisos" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('usuarios.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                    >
                        ← Volver a usuarios
                    </Link>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {roles.map((rol) => (
                            <div
                                key={rol.id}
                                className="bg-white p-6 shadow-sm sm:rounded-lg"
                            >
                                <h3 className="text-lg font-semibold text-gray-900">
                                    {rol.name}
                                </h3>
                                <p className="mt-1 text-xs uppercase tracking-wider text-gray-400">
                                    {rol.permisos.length} permiso(s)
                                </p>
                                <ul className="mt-3 space-y-1">
                                    {rol.permisos.length === 0 && (
                                        <li className="text-sm text-gray-400">
                                            Sin permisos asignados.
                                        </li>
                                    )}
                                    {rol.permisos.map((p) => (
                                        <li
                                            key={p}
                                            className="rounded bg-gray-50 px-2 py-1 text-sm text-gray-700"
                                        >
                                            {p}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>

                    <p className="text-xs text-gray-400">
                        Vista de solo lectura. La edición de permisos por rol se
                        implementará más adelante.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
