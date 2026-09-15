import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const accionBadge = {
    'Inicio de sesión': 'bg-green-100 text-green-800',
    'Cierre de sesión': 'bg-gray-100 text-gray-800',
    'Intento fallido': 'bg-amber-100 text-amber-800',
    'Cuenta bloqueada': 'bg-red-100 text-red-800',
    'Evento registrado': 'bg-indigo-100 text-indigo-800',
    'Usuario creado': 'bg-emerald-100 text-emerald-800',
    'Usuario actualizado': 'bg-sky-100 text-sky-800',
    'Usuario eliminado': 'bg-rose-100 text-rose-800',
};

export default function Auditoria({ registros }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Bitácora de auditoría
                </h2>
            }
        >
            <Head title="Bitácora de auditoría" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('usuarios.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                    >
                        ← Volver a usuarios
                    </Link>

                    <p className="text-sm text-gray-600">
                        Movimientos de los usuarios en el sistema: quién, con qué
                        rol, desde qué IP y qué acción realizó.
                    </p>

                    <div className="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Fecha y hora
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Usuario
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Rol
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Acción
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        IP
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Detalle
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {registros.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-4 text-center text-sm text-gray-500"
                                        >
                                            No hay registros de auditoría.
                                        </td>
                                    </tr>
                                )}
                                {registros.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {r.fecha_hora}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <div className="font-medium text-gray-900">
                                                {r.usuario}
                                            </div>
                                            {r.email && r.email !== r.usuario && (
                                                <div className="text-xs text-gray-400">
                                                    {r.email}
                                                </div>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {r.rol || '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <span
                                                className={
                                                    'inline-flex rounded-full px-2 text-xs font-semibold leading-5 ' +
                                                    (accionBadge[r.accion] ||
                                                        'bg-gray-100 text-gray-800')
                                                }
                                            >
                                                {r.accion}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {r.ip || '—'}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-600">
                                            {r.descripcion || '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {registros.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {registros.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={
                                            'rounded-md border px-3 py-1 text-sm ' +
                                            (link.active
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50')
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="rounded-md border border-gray-200 px-3 py-1 text-sm text-gray-400"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
