import Alerta from '@/Components/Alerta';
import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const rolBadge = {
    Supervisor: 'bg-accent-soft text-accent',
    Administrativo: 'bg-success-soft text-success',
    Operador: 'bg-warning-soft text-warning',
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
            header={<h2 className="titulo-pagina">Usuarios</h2>}
        >
            <Head title="Usuarios" />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <Alerta tipo="exito">{flash.success}</Alerta>
                )}
                {flash?.error && <Alerta tipo="error">{flash.error}</Alerta>}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={route('roles.index')}
                        className="enlace text-sm"
                    >
                        Ver roles y permisos →
                    </Link>
                    <PrimaryButton
                        onClick={() => router.visit(route('usuarios.create'))}
                    >
                        Nuevo usuario
                    </PrimaryButton>
                </div>

                <div className="panel overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="tabla">
                            <thead>
                                <tr>
                                    <th scope="col">Nombre</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Rol</th>
                                    <th scope="col">Alta</th>
                                    <th scope="col" className="text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {usuarios.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="py-10 text-center text-ink-muted"
                                        >
                                            No hay usuarios.
                                        </td>
                                    </tr>
                                )}
                                {usuarios.map((u) => (
                                    <tr key={u.id}>
                                        <td className="whitespace-nowrap font-medium text-ink">
                                            {u.name}
                                            {u.id === authUserId && (
                                                <span className="ml-2 text-xs font-normal text-ink-subtle">
                                                    (vos)
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap text-ink-muted">
                                            {u.email}
                                        </td>
                                        <td className="whitespace-nowrap">
                                            <span
                                                className={
                                                    'badge ' +
                                                    (rolBadge[u.rol] ||
                                                        'bg-surface-2 text-ink-muted')
                                                }
                                            >
                                                {u.rol || 'sin rol'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap font-mono text-ink-muted tabular-nums">
                                            {u.created_at}
                                        </td>
                                        <td className="whitespace-nowrap text-right">
                                            <Link
                                                href={route(
                                                    'usuarios.edit',
                                                    u.id,
                                                )}
                                                className="enlace"
                                            >
                                                Editar
                                            </Link>
                                            {u.id !== authUserId && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setPorEliminar(u)
                                                    }
                                                    className="ml-4 rounded-sm font-medium text-danger underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger"
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
                    <h2 className="text-lg font-semibold text-ink">
                        ¿Eliminar a {porEliminar?.name}?
                    </h2>
                    <p className="mt-1 text-sm text-ink-muted">
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
