import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function Edit({ usuario, roles }) {
    const { data, setData, put, processing, errors } = useForm({
        name: usuario.name,
        email: usuario.email,
        password: '',
        password_confirmation: '',
        rol: usuario.rol ?? roles[0] ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('usuarios.update', usuario.id));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="titulo-pagina">Editar usuario</h2>}
        >
            <Head title="Editar usuario" />

            <div className="mx-auto max-w-xl px-4 py-6 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="panel space-y-6 p-4 sm:p-6">
                    <div>
                        <InputLabel htmlFor="name" value="Nombre" />
                        <TextInput
                            id="name"
                            className="mt-1"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            isFocused
                        />
                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            className="mt-1"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    <div>
                        <InputLabel htmlFor="rol" value="Rol" />
                        <select
                            id="rol"
                            className="campo mt-1"
                            value={data.rol}
                            onChange={(e) => setData('rol', e.target.value)}
                        >
                            {roles.map((r) => (
                                <option key={r} value={r}>
                                    {r}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-2" message={errors.rol} />
                    </div>

                    <div className="border-t border-line pt-4">
                        <p className="text-sm text-ink-muted">
                            Dejá la contraseña en blanco para no cambiarla.
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
                            className="mt-1"
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
                            className="mt-1"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-line pt-5">
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
        </AuthenticatedLayout>
    );
}
