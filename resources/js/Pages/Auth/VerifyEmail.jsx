import Alerta from '@/Components/Alerta';
import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificación de correo" />

            <h1 className="text-2xl font-semibold tracking-tight text-ink">
                Verificación de correo
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                Gracias por registrarse. Antes de comenzar, verifique su
                dirección de correo haciendo clic en el enlace que le enviamos.
                Si no recibió el correo, podemos enviarle otro.
            </p>

            {status === 'verification-link-sent' && (
                <Alerta tipo="exito" className="mt-6">
                    Se envió un nuevo enlace de verificación a la dirección de
                    correo indicada durante el registro.
                </Alerta>
            )}

            <form
                onSubmit={submit}
                className="mt-8 flex flex-wrap items-center justify-between gap-4"
            >
                <PrimaryButton disabled={processing}>
                    Reenviar correo de verificación
                </PrimaryButton>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="enlace text-sm"
                >
                    Cerrar sesión
                </Link>
            </form>
        </GuestLayout>
    );
}
