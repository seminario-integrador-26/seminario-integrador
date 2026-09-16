import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const rolBadge = {
    Supervisor: 'border-atalaya-orange bg-atalaya-orange/10 text-atalaya-orange',
    Administrativo: 'border-atalaya-sky bg-atalaya-sky/10 text-atalaya-sky',
    'Administrador de sistema':
        'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
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
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Usuarios
                </h2>
            }
        >
            <Head title="Usuarios" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-none bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="rounded-none bg-red-50 p-4 text-sm text-red-800">
                            {flash.error}
                        </div>
                    )}

                    <div className="flex items-center justify-between">
                        <div className="flex gap-4">
                            <Link
                                href={route('roles.index')}
                                className="text-sm font-medium text-atalaya-cyan hover:text-white"
                            >
                                Ver roles y permisos →
                            </Link>
                            <Link
                                href={route('usuarios.auditoria')}
                                className="text-sm font-medium text-atalaya-cyan hover:text-white"
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

                    <div className="overflow-hidden bg-atalaya-surface border border-atalaya-border">
                        <table className="min-w-full divide-y divide-atalaya-border">
                            <thead className="bg-atalaya-canvas">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Nombre
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Usuario
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Email
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Rol
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Alta
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-atalaya-border bg-atalaya-surface">
                                {usuarios.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-4 text-center text-sm text-atalaya-text-dim"
                                        >
                                            No hay usuarios.
                                        </td>
                                    </tr>
                                )}
                                {usuarios.map((u) => (
                                    <tr key={u.id}>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-white">
                                            {u.name}
                                            {u.id === authUserId && (
                                                <span className="ml-2 text-xs text-atalaya-text-dim">
                                                    (vos)
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-muted">
                                            {u.username}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {u.email}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <span
                                                className={
                                                    'inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4 ' +
                                                    (rolBadge[u.rol] ||
                                                        'border-atalaya-border bg-atalaya-canvas text-atalaya-text-muted')
                                                }
                                            >
                                                {u.rol || 'sin rol'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {u.created_at}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <Link
                                                href={route(
                                                    'usuarios.edit',
                                                    u.id,
                                                )}
                                                className="text-atalaya-cyan hover:text-white"
                                            >
                                                Editar
                                            </Link>
                                            {u.id !== authUserId && (
                                                <button
                                                    onClick={() =>
                                                        setPorEliminar(u)
                                                    }
                                                    className="ml-4 text-atalaya-crimson hover:text-red-900"
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
                    <h2 className="text-lg font-medium text-white">
                        ¿Eliminar a {porEliminar?.name}?
                    </h2>
                    <p className="mt-1 text-sm text-atalaya-text-muted">
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
