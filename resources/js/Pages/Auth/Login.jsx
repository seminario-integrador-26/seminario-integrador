import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    // US-001: el ingreso es por nombre de usuario; el email sólo recupera la clave.
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Iniciar sesión" />

            <div className="mb-4 flex items-center justify-between border-b border-atalaya-border pb-3">
                <span className="font-mono text-xs font-bold uppercase tracking-wider text-white">
                    [ Autenticación de operador ]
                </span>
                <span className="font-mono text-[10px] text-atalaya-cyan">
                    Sesión segura
                </span>
            </div>

            {status && (
                <div className="mb-4 border border-green-800 bg-green-950/40 p-2 font-mono text-xs text-green-400">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="username" value="Usuario" />

                    <TextInput
                        id="username"
                        type="text"
                        name="username"
                        value={data.username}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('username', e.target.value)}
                    />

                    <InputError message={errors.username} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Clave de acceso" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between pt-1 font-mono text-xs">
                    <label className="flex cursor-pointer items-center text-atalaya-text-muted">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-[11px] text-atalaya-text-muted">
                            Recordar terminal
                        </span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-[11px] text-atalaya-text-muted transition hover:text-atalaya-cyan"
                        >
                            ¿Olvidó su contraseña?
                        </Link>
                    )}
                </div>

                <div className="pt-2">
                    <PrimaryButton className="w-full py-2.5" disabled={processing}>
                        [ Ingresar al centro de control ]
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
