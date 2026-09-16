import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function RolCard({ rol, permisosDisponibles, permisoIrrenunciable }) {
    const [seleccion, setSeleccion] = useState(() => new Set(rol.permisos));
    const [guardando, setGuardando] = useState(false);

    const bloqueado = (permiso) =>
        rol.name === permisoIrrenunciable.rol &&
        permiso === permisoIrrenunciable.permiso;

    const toggle = (permiso) => {
        if (bloqueado(permiso)) return;
        setSeleccion((prev) => {
            const next = new Set(prev);
            next.has(permiso) ? next.delete(permiso) : next.add(permiso);
            return next;
        });
    };

    const guardar = () => {
        router.put(
            route('roles.permisos.update', rol.id),
            { permisos: [...seleccion] },
            {
                preserveScroll: true,
                onStart: () => setGuardando(true),
                onFinish: () => setGuardando(false),
            },
        );
    };

    const original = new Set(rol.permisos);
    const sinCambios =
        seleccion.size === original.size &&
        [...seleccion].every((p) => original.has(p));

    return (
        <div className="flex flex-col bg-atalaya-surface p-6 border border-atalaya-border">
            <h3 className="text-lg font-semibold text-white">{rol.name}</h3>
            <p className="mt-1 text-xs uppercase tracking-wider text-atalaya-text-dim">
                {seleccion.size} permiso(s)
            </p>

            <ul className="mt-3 space-y-1">
                {permisosDisponibles.length === 0 && (
                    <li className="text-sm text-atalaya-text-dim">
                        No hay permisos definidos.
                    </li>
                )}
                {permisosDisponibles.map((permiso) => (
                    <li key={permiso}>
                        <label
                            className={
                                'flex items-center gap-2 px-2 py-1 text-sm ' +
                                (bloqueado(permiso)
                                    ? 'text-atalaya-text-dim'
                                    : 'cursor-pointer text-atalaya-text-muted hover:bg-atalaya-canvas')
                            }
                            title={
                                bloqueado(permiso)
                                    ? 'Este permiso no puede quitarse del Administrador de sistema.'
                                    : undefined
                            }
                        >
                            <input
                                type="checkbox"
                                checked={seleccion.has(permiso)}
                                disabled={bloqueado(permiso)}
                                onChange={() => toggle(permiso)}
                                className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan disabled:opacity-50"
                            />
                            <span className="font-mono text-xs">{permiso}</span>
                        </label>
                    </li>
                ))}
            </ul>

            <div className="mt-4 flex justify-end border-t border-atalaya-border pt-3">
                <PrimaryButton
                    type="button"
                    onClick={guardar}
                    disabled={guardando || sinCambios}
                >
                    {guardando ? 'Guardando…' : 'Guardar permisos'}
                </PrimaryButton>
            </div>
        </div>
    );
}

export default function Index({
    roles,
    permisosDisponibles,
    permisoIrrenunciable,
}) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const crearPermiso = (e) => {
        e.preventDefault();
        post(route('roles.permisos.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name'),
        });
    };

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

                    <form
                        onSubmit={crearPermiso}
                        className="flex flex-wrap items-end gap-3 bg-atalaya-surface p-6 border border-atalaya-border"
                    >
                        <div className="flex-1 min-w-64">
                            <InputLabel htmlFor="name" value="Nuevo permiso" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full font-mono text-xs"
                                placeholder="dominio.accion (ej. reportes.exportar)"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            <InputError
                                className="mt-2"
                                message={errors.name}
                            />
                        </div>
                        <PrimaryButton disabled={processing}>
                            Crear permiso
                        </PrimaryButton>
                    </form>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {roles.map((rol) => (
                            <RolCard
                                key={rol.id}
                                rol={rol}
                                permisosDisponibles={permisosDisponibles}
                                permisoIrrenunciable={permisoIrrenunciable}
                            />
                        ))}
                    </div>

                    <p className="text-xs text-atalaya-text-dim">
                        La matriz de fábrica vive en{' '}
                        <span className="font-mono">PermissionSeeder</span>:
                        volver a correr los seeders (o migrate:fresh --seed)
                        reescribe estos permisos.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
