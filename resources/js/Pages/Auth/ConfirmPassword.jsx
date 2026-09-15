import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmar contraseña" />

            <h1 className="text-2xl font-semibold tracking-tight text-ink">
                Confirmar contraseña
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                Esta es un área protegida del sistema. Confirme su contraseña
                para continuar.
            </p>

            <form onSubmit={submit} className="mt-8 space-y-5">
                <div>
                    <InputLabel htmlFor="password" value="Contraseña" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <PrimaryButton className="w-full" disabled={processing}>
                    Confirmar
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
