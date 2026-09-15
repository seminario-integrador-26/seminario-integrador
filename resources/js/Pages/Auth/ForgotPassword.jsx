import Alerta from '@/Components/Alerta';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Recuperar contraseña" />

            <h1 className="text-2xl font-semibold tracking-tight text-ink">
                Recuperar contraseña
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                ¿Olvidó su contraseña? Indique su dirección de correo y le
                enviaremos un enlace para elegir una nueva.
            </p>

            {status && (
                <Alerta tipo="exito" className="mt-6">
                    {status}
                </Alerta>
            )}

            <form onSubmit={submit} className="mt-8 space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Correo electrónico" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <PrimaryButton className="w-full" disabled={processing}>
                    Enviar enlace de restablecimiento
                </PrimaryButton>

                <p className="text-center text-sm">
                    <Link href={route('login')} className="enlace">
                        Volver a iniciar sesión
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
