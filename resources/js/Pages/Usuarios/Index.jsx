import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const rolBadge = {
    Supervisor: 'bg-indigo-100 text-indigo-800',
    Administrativo: 'bg-emerald-100 text-emerald-800',
    'Administrador de sistema': 'bg-rose-100 text-rose-800',
};

export default function Index({ usuarios, authUserId }) {
    const { flash } = usePage().props;
    const [porEliminar, setPorEliminar] = useState(null);

    const eliminar = () => {
        router.delete(route('usuarios.destroy', porEliminar.id), {
            preserveScroll: true,
            onFinish: () => setPorEliminar(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Usuarios
                </h2>
            }
        >
            <Head title="Usuarios" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="rounded-md bg-red-50 p-4 text-sm text-red-800">
                            {flash.error}
                        </div>
                    )}

                    <div className="flex items-center justify-between">
                        <div className="flex gap-4">
                            <Link
                                href={route('roles.index')}
                                className="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                            >
                                Ver roles y permisos →
                            </Link>
                            <Link
                                href={route('usuarios.auditoria')}
                                className="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                            >
                                Ver bitácora de auditoría →
                            </Link>
                        </div>
                        <PrimaryButton
                            onClick={() =>
                                router.visit(route('usuarios.create'))
                            }
                        >
                            Nuevo usuario
                        </PrimaryButton>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Nombre
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Usuario
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Email
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Rol
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Alta
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {usuarios.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-4 text-center text-sm text-gray-500"
                                        >
                                            No hay usuarios.
                                        </td>
                                    </tr>
                                )}
                                {usuarios.map((u) => (
                                    <tr key={u.id}>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                            {u.name}
                                            {u.id === authUserId && (
                                                <span className="ml-2 text-xs text-gray-400">
                                                    (vos)
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                            {u.username}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {u.email}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <span
                                                className={
                                                    'inline-flex rounded-full px-2 text-xs font-semibold leading-5 ' +
                                                    (rolBadge[u.rol] ||
                                                        'bg-gray-100 text-gray-800')
                                                }
                                            >
                                                {u.rol || 'sin rol'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {u.created_at}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <Link
                                                href={route(
                                                    'usuarios.edit',
                                                    u.id,
                                                )}
                                                className="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Editar
                                            </Link>
                                            {u.id !== authUserId && (
                                                <button
                                                    onClick={() =>
                                                        setPorEliminar(u)
                                                    }
                                                    className="ml-4 text-red-600 hover:text-red-900"
                                                >
                                                    Eliminar
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Modal show={!!porEliminar} onClose={() => setPorEliminar(null)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        ¿Eliminar a {porEliminar?.name}?
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Esta acción no se puede deshacer.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setPorEliminar(null)}>
                            Cancelar
                        </SecondaryButton>
                        <DangerButton onClick={eliminar}>Eliminar</DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
