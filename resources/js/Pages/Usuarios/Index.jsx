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

export default function Index({ usuarios, authUserId, roles }) {
    const { flash } = usePage().props;
    const [porEliminar, setPorEliminar] = useState(null);

    const eliminar = () => {
        router.delete(route('usuarios.destroy', porEliminar.id), {
            preserveScroll: true,
            onFinish: () => setPorEliminar(null),
        });
    };

    // Alterna un rol del usuario (un usuario puede tener varios). No permite
    // quedar sin ningún rol; el auto-bloqueo del admin lo valida el backend.
    const toggleRol = (usuario, rol) => {
        const tiene = usuario.roles.includes(rol);
        const roles = tiene
            ? usuario.roles.filter((r) => r !== rol)
            : [...usuario.roles, rol];

        if (roles.length === 0) return;

        router.patch(
            route('usuarios.roles', usuario.id),
            { roles },
            { preserveScroll: true },
        );
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
                                        <td className="px-6 py-4 text-sm">
                                            <div className="flex flex-wrap gap-1">
                                                {roles.map((r) => {
                                                    const activo =
                                                        u.roles.includes(r);
                                                    // Un admin no puede quitarse
                                                    // a sí mismo ese rol.
                                                    const bloqueado =
                                                        u.id === authUserId &&
                                                        r ===
                                                            'Administrador de sistema';
                                                    return (
                                                        <button
                                                            key={r}
                                                            type="button"
                                                            disabled={bloqueado}
                                                            onClick={() =>
                                                                toggleRol(u, r)
                                                            }
                                                            title={
                                                                bloqueado
                                                                    ? 'No podés quitarte tu propio rol de Administrador de sistema'
                                                                    : activo
                                                                      ? `Quitar ${r}`
                                                                      : `Asignar ${r}`
                                                            }
                                                            className={
                                                                'inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4 transition-colors ' +
                                                                (activo
                                                                    ? rolBadge[
                                                                          r
                                                                      ]
                                                                    : 'border-atalaya-border bg-atalaya-canvas text-atalaya-text-dim hover:text-atalaya-text-muted') +
                                                                (bloqueado
                                                                    ? ' cursor-not-allowed opacity-80'
                                                                    : ' cursor-pointer')
                                                            }
                                                        >
                                                            {r}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {u.created_at}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link
                                                    href={route(
                                                        'usuarios.edit',
                                                        u.id,
                                                    )}
                                                    title="Editar usuario"
                                                    aria-label="Editar usuario"
                                                    className="inline-flex items-center justify-center border border-atalaya-border p-1.5 text-atalaya-cyan transition-colors hover:border-atalaya-cyan hover:bg-atalaya-elevated hover:text-white"
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        className="h-4 w-4"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        strokeWidth={1.5}
                                                        stroke="currentColor"
                                                    >
                                                        <path
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"
                                                        />
                                                    </svg>
                                                </Link>
                                                {u.id !== authUserId && (
                                                    <button
                                                        onClick={() =>
                                                            setPorEliminar(u)
                                                        }
                                                        title="Eliminar usuario"
                                                        aria-label="Eliminar usuario"
                                                        className="inline-flex items-center justify-center border border-atalaya-border p-1.5 text-atalaya-crimson transition-colors hover:border-atalaya-crimson hover:bg-atalaya-elevated hover:text-white"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            className="h-4 w-4"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            strokeWidth={1.5}
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                                            />
                                                        </svg>
                                                    </button>
                                                )}
                                            </div>
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
