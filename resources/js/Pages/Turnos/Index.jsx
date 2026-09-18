import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PersonalPresenteInput from '@/Components/PersonalPresenteInput';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const campoClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs ' +
    'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

/** Tarjeta para abrir un turno cuando el supervisor no tiene ninguno activo. */
function AbrirTurno() {
    const { data, setData, post, processing, errors, reset } = useForm({
        personal_presente: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('turnos.store'), { onSuccess: () => reset() });
    };

    return (
        <form
            onSubmit={submit}
            className="border border-atalaya-border bg-atalaya-surface p-6"
        >
            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-orange">
                Sin turno abierto
            </h3>
            <p className="mt-1 font-mono text-[11px] text-atalaya-text-muted">
                Abrí un turno para poder registrar eventos (RN-05). Se registra
                tu usuario y la fecha/hora de inicio automáticamente.
            </p>

            <div className="mt-4">
                <InputLabel htmlFor="personal_presente">
                    Personal presente
                    <span className="text-atalaya-crimson"> *</span>
                </InputLabel>
                <PersonalPresenteInput
                    value={data.personal_presente}
                    onChange={(nombres) => setData('personal_presente', nombres)}
                />
                <InputError
                    className="mt-2"
                    message={
                        errors.personal_presente ||
                        errors['personal_presente.0'] ||
                        errors.turno
                    }
                />
            </div>

            <div className="mt-4 flex justify-end">
                <PrimaryButton disabled={processing}>Abrir turno</PrimaryButton>
            </div>
        </form>
    );
}

/** Tarjeta del turno activo, con el formulario de cierre. */
function TurnoActivo({ turno }) {
    const { data, setData, patch, processing, errors } = useForm({
        novedades_pendientes: '',
    });

    const cerrar = (e) => {
        e.preventDefault();
        patch(route('turnos.cerrar', turno.id));
    };

    return (
        <form
            onSubmit={cerrar}
            className="border border-atalaya-cyan/40 bg-atalaya-cyan/10 p-6"
        >
            <div className="flex items-center justify-between">
                <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-cyan">
                    Turno #{turno.id} abierto
                </h3>
                <span className="font-mono text-[11px] text-atalaya-text-muted">
                    Desde {turno.hora_inicio} · {turno.fecha}
                </span>
            </div>

            <div className="mt-2 flex flex-wrap gap-1.5">
                {turno.personal_presente.map((nombre, i) => (
                    <span
                        key={`${nombre}-${i}`}
                        className="border border-atalaya-border bg-atalaya-canvas px-2 py-0.5 font-mono text-[11px] text-white"
                    >
                        {nombre}
                    </span>
                ))}
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="novedades_pendientes">
                    Novedades pendientes
                    <span className="font-normal text-atalaya-text-dim">
                        {' '}
                        (opcional)
                    </span>
                </InputLabel>
                <textarea
                    id="novedades_pendientes"
                    rows={3}
                    maxLength={2000}
                    className={campoClass}
                    value={data.novedades_pendientes}
                    onChange={(e) =>
                        setData('novedades_pendientes', e.target.value)
                    }
                    placeholder="Novedades a dejar asentadas para el próximo turno…"
                />
                <InputError
                    className="mt-2"
                    message={errors.novedades_pendientes || errors.turno}
                />
            </div>

            <div className="mt-4 flex justify-end">
                <PrimaryButton disabled={processing}>Cerrar turno</PrimaryButton>
            </div>
        </form>
    );
}

export default function Index({ turnos, turnoActivo }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Turnos de guardia
                </h2>
            }
        >
            <Head title="Turnos de guardia" />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                    <FlashMessages />

                    {turnoActivo ? (
                        <TurnoActivo turno={turnoActivo} />
                    ) : (
                        <AbrirTurno />
                    )}

                    <div className="overflow-hidden border border-atalaya-border bg-atalaya-surface">
                        <div className="border-b border-atalaya-border px-6 py-3">
                            <span className="font-mono text-xs font-bold uppercase tracking-widest text-white">
                                Historial de turnos
                            </span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-atalaya-border">
                                <thead className="bg-atalaya-canvas">
                                    <tr>
                                        {[
                                            '#',
                                            'Fecha',
                                            'Supervisor',
                                            'Inicio',
                                            'Fin',
                                            'Personal',
                                            'Eventos',
                                            'Estado',
                                        ].map((titulo) => (
                                            <th
                                                key={titulo}
                                                className="px-4 py-2 text-left font-mono text-[10px] font-bold uppercase tracking-wider text-atalaya-text-muted"
                                            >
                                                {titulo}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-atalaya-border">
                                    {turnos.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="px-4 py-4 text-center font-mono text-xs text-atalaya-text-dim"
                                            >
                                                Todavía no hay turnos registrados.
                                            </td>
                                        </tr>
                                    )}
                                    {turnos.data.map((t) => (
                                        <tr
                                            key={t.id}
                                            className="font-mono text-xs align-top"
                                        >
                                            <td className="px-4 py-3 text-atalaya-text-dim">
                                                {t.id}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-white">
                                                {t.fecha}
                                            </td>
                                            <td className="px-4 py-3 text-white">
                                                {t.supervisor ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-atalaya-text-muted">
                                                {t.hora_inicio}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-atalaya-text-muted">
                                                {t.hora_fin
                                                    ? `${t.hora_fin}${
                                                          t.fecha_fin &&
                                                          t.fecha_fin !== t.fecha
                                                              ? ` (${t.fecha_fin})`
                                                              : ''
                                                      }`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-atalaya-text-muted">
                                                {t.personal_presente.length > 0
                                                    ? t.personal_presente.join(
                                                          ', ',
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-atalaya-text-muted">
                                                {t.eventos_count}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                {t.abierto ? (
                                                    <span className="border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-0.5 font-bold uppercase text-atalaya-cyan">
                                                        Abierto
                                                    </span>
                                                ) : (
                                                    <span className="border border-atalaya-border bg-atalaya-canvas px-2 py-0.5 uppercase text-atalaya-text-dim">
                                                        Cerrado
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {turnos.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {turnos.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={
                                            'rounded-none border px-3 py-1 text-sm ' +
                                            (link.active
                                                ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                                : 'border-atalaya-border bg-atalaya-surface text-atalaya-text-muted hover:bg-atalaya-canvas')
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="rounded-none border border-atalaya-border px-3 py-1 text-sm text-atalaya-text-dim"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
