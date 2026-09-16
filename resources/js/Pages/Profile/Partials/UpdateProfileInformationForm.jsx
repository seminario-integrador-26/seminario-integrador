import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

/** Dato de identidad que el usuario ve pero no puede cambiarse solo. */
function DatoSoloLectura({ etiqueta, valor }) {
    return (
        <div>
            <InputLabel value={etiqueta} />
            <p className="mt-1 border border-atalaya-border bg-atalaya-canvas px-3 py-2 font-mono text-xs text-atalaya-text-muted">
                {valor}
            </p>
        </div>
    );
}

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    puedeEditarIdentidad = false,
    className = '',
}) {
    const user = usePage().props.auth.user;

    // El usuario y el correo solo viajan si quien edita puede cambiarlos.
    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm(
            puedeEditarIdentidad
                ? {
                      name: user.name,
                      username: user.username,
                      email: user.email,
                  }
                : { name: user.name },
        );

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-white">
                    Datos del perfil
                </h2>

                <p className="mt-1 text-sm text-atalaya-text-muted">
                    {puedeEditarIdentidad
                        ? 'Actualice los datos de su cuenta y su dirección de correo.'
                        : 'Actualice su nombre para mostrar. El usuario y el correo los administra el Administrador de sistema.'}
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nombre" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                {puedeEditarIdentidad ? (
                    <div>
                        <InputLabel htmlFor="username" value="Usuario" />

                        <TextInput
                            id="username"
                            className="mt-1 block w-full"
                            value={data.username}
                            onChange={(e) =>
                                setData('username', e.target.value)
                            }
                            required
                            autoComplete="username"
                        />

                        <InputError
                            className="mt-2"
                            message={errors.username}
                        />
                    </div>
                ) : (
                    <DatoSoloLectura
                        etiqueta="Usuario"
                        valor={user.username}
                    />
                )}

                {puedeEditarIdentidad ? (
                    <div>
                        <InputLabel
                            htmlFor="email"
                            value="Correo electrónico"
                        />

                        <TextInput
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="email"
                        />

                        <InputError className="mt-2" message={errors.email} />
                    </div>
                ) : (
                    <DatoSoloLectura
                        etiqueta="Correo electrónico"
                        valor={user.email}
                    />
                )}

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-white">
                            Su dirección de correo no está verificada.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-none text-sm text-atalaya-text-muted underline hover:text-white focus:outline-none focus:ring-1 focus:ring-atalaya-cyan"
                            >
                                Haga clic aquí para reenviar el correo de verificación.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-400">
                                Se envió un nuevo enlace de verificación a su
                                dirección de correo.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Guardar</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-atalaya-text-muted">
                            Guardado.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
