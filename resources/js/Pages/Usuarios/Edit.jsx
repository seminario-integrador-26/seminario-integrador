import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function Edit({ usuario, roles, puedeResetPassword }) {
    const { data, setData, put, processing, errors } = useForm({
        name: usuario.name,
        username: usuario.username,
        email: usuario.email,
        password: '',
        password_confirmation: '',
        roles: usuario.roles ?? [],
    });

    const toggleRol = (rol) => {
        setData(
            'roles',
            data.roles.includes(rol)
                ? data.roles.filter((r) => r !== rol)
                : [...data.roles, rol],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('usuarios.update', usuario.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Editar usuario
                </h2>
            }
        >
            <Head title="Editar usuario" />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="space-y-6 bg-atalaya-surface p-6 border border-atalaya-border"
                    >
                        <div>
                            <InputLabel htmlFor="name" value="Nombre" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                isFocused
                            />
                            <InputError
                                className="mt-2"
                                message={errors.name}
                            />
                        </div>

                        <div>
                            <InputLabel htmlFor="username" value="Usuario" />
                            <TextInput
                                id="username"
                                className="mt-1 block w-full"
                                value={data.username}
                                onChange={(e) =>
                                    setData('username', e.target.value)
                                }
                            />
                            <InputError
                                className="mt-2"
                                message={errors.username}
                            />
                        </div>

                        <div>
                            <InputLabel htmlFor="email" value="Email" />
                            <TextInput
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            <InputError
                                className="mt-2"
                                message={errors.email}
                            />
                        </div>

                        <div>
                            <InputLabel value="Roles" />
                            <div className="mt-2 space-y-2">
                                {roles.map((r) => (
                                    <label
                                        key={r}
                                        className="flex cursor-pointer items-center gap-2 text-sm text-atalaya-text-muted"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.roles.includes(r)}
                                            onChange={() => toggleRol(r)}
                                            className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan"
                                        />
                                        <span className="font-mono text-xs">
                                            {r}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <InputError
                                className="mt-2"
                                message={errors.roles}
                            />
                        </div>

                        {puedeResetPassword ? (
                            <>
                                <div className="border-t border-atalaya-border pt-4">
                                    <p className="text-sm text-atalaya-text-dim">
                                        Dejá la contraseña en blanco para no
                                        cambiarla.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="password"
                                        value="Nueva contraseña"
                                    />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        className="mt-1 block w-full"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={errors.password}
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="password_confirmation"
                                        value="Confirmar nueva contraseña"
                                    />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        className="mt-1 block w-full"
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </>
                        ) : (
                            <div className="border-t border-atalaya-border pt-4">
                                <p className="text-sm text-atalaya-text-dim">
                                    La contraseña de un Administrador de sistema
                                    no se blanquea desde acá: esa cuenta la
                                    recupera por email.
                                </p>
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-3">
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    router.visit(route('usuarios.index'))
                                }
                            >
                                Cancelar
                            </SecondaryButton>
                            <PrimaryButton disabled={processing}>
                                Guardar cambios
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
