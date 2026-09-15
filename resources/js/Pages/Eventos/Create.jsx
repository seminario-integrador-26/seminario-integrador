import Alerta from '@/Components/Alerta';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MapaPuntosMonitoreo from '@/Components/MapaPuntosMonitoreo';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectorPuntoMonitoreo from '@/Components/SelectorPuntoMonitoreo';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

// Fecha local en formato YYYY-MM-DD.
const hoy = () => new Date().toLocaleDateString('en-CA');

function Obligatorio() {
    return (
        <span className="text-danger" aria-hidden="true">
            {' '}
            *
        </span>
    );
}

export default function Create({
    tipos,
    categorias,
    puntos,
    jurisdicciones,
    turnoActivo,
}) {
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        tipo_evento_id: '',
        fecha: hoy(),
        hora: '',
        punto_monitoreo_id: null,
        timestamp_video: '',
        descripcion: '',
    });

    const tipoSeleccionado = tipos.find(
        (t) => String(t.id) === String(data.tipo_evento_id),
    );
    const requiereUbicacion = Boolean(tipoSeleccionado?.requiere_ubicacion);
    const hayPunto = data.punto_monitoreo_id !== null;

    const seleccionarPunto = (id) =>
        setData((d) => ({
            ...d,
            punto_monitoreo_id: id,
            timestamp_video: id ? d.timestamp_video : '',
        }));

    const submit = (e) => {
        e.preventDefault();
        post(route('eventos.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="titulo-pagina">Registrar evento</h2>}
        >
            <Head title="Registrar evento" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <Alerta tipo="exito">{flash.success}</Alerta>
                )}

                {turnoActivo ? (
                    <Alerta tipo="info">
                        Turno #{turnoActivo.id} abierto el {turnoActivo.fecha}{' '}
                        a las {turnoActivo.hora_inicio}
                        {turnoActivo.supervisor &&
                            ` por ${turnoActivo.supervisor}`}
                        . El evento quedará asociado a este turno.
                    </Alerta>
                ) : (
                    <Alerta tipo="error">
                        No hay un turno de guardia abierto. Abrí un turno antes
                        de registrar eventos.
                    </Alerta>
                )}

                {errors.turno && <Alerta tipo="error">{errors.turno}</Alerta>}

                <form onSubmit={submit} className="panel space-y-6 p-4 sm:p-6">
                    <div>
                        <InputLabel htmlFor="tipo_evento_id">
                            Tipo de evento
                            <Obligatorio />
                        </InputLabel>
                        <select
                            id="tipo_evento_id"
                            className="campo mt-1"
                            value={data.tipo_evento_id}
                            onChange={(e) =>
                                setData('tipo_evento_id', e.target.value)
                            }
                        >
                            <option value="">Seleccioná un tipo…</option>
                            {Object.entries(categorias).map(
                                ([clave, etiqueta]) => {
                                    const deCategoria = tipos.filter(
                                        (t) => t.categoria === clave,
                                    );

                                    return (
                                        deCategoria.length > 0 && (
                                            <optgroup
                                                key={clave}
                                                label={etiqueta}
                                            >
                                                {deCategoria.map((t) => (
                                                    <option
                                                        key={t.id}
                                                        value={t.id}
                                                    >
                                                        {t.nombre}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        )
                                    );
                                },
                            )}
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.tipo_evento_id}
                        />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="fecha">
                                Fecha del hecho
                                <Obligatorio />
                            </InputLabel>
                            <TextInput
                                id="fecha"
                                type="date"
                                max={hoy()}
                                className="mt-1 font-mono"
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                            <InputError
                                className="mt-2"
                                message={errors.fecha}
                            />
                        </div>

                        <div>
                            <InputLabel htmlFor="hora">
                                Horario del hecho
                                <Obligatorio />
                            </InputLabel>
                            <TextInput
                                id="hora"
                                type="time"
                                step="1"
                                className="mt-1 font-mono"
                                value={data.hora}
                                onChange={(e) =>
                                    setData('hora', e.target.value)
                                }
                            />
                            <InputError
                                className="mt-2"
                                message={errors.hora}
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="punto_monitoreo_id">
                            Punto de monitoreo
                            {requiereUbicacion ? (
                                <Obligatorio />
                            ) : (
                                <span className="font-normal text-ink-subtle">
                                    {' '}
                                    (opcional)
                                </span>
                            )}
                        </InputLabel>
                        <SelectorPuntoMonitoreo
                            id="punto_monitoreo_id"
                            puntos={puntos}
                            jurisdicciones={jurisdicciones}
                            value={data.punto_monitoreo_id}
                            onChange={seleccionarPunto}
                        />
                        <InputError
                            className="mt-2"
                            message={errors.punto_monitoreo_id}
                        />
                        <MapaPuntosMonitoreo
                            puntos={puntos}
                            seleccionadoId={data.punto_monitoreo_id}
                            onSelect={seleccionarPunto}
                            className="mt-3 h-72 w-full"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="timestamp_video">
                            Timestamp del video (hh:mm:ss)
                            {hayPunto && <Obligatorio />}
                        </InputLabel>
                        <TextInput
                            id="timestamp_video"
                            type="time"
                            step="1"
                            disabled={!hayPunto}
                            className="mt-1 font-mono"
                            value={data.timestamp_video}
                            onChange={(e) =>
                                setData('timestamp_video', e.target.value)
                            }
                        />
                        {!hayPunto && (
                            <p className="mt-1 text-xs text-ink-subtle">
                                Seleccioná un punto de monitoreo para indicar
                                el momento exacto del video.
                            </p>
                        )}
                        <InputError
                            className="mt-2"
                            message={errors.timestamp_video}
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="descripcion" value="Descripción" />
                        <textarea
                            id="descripcion"
                            rows={4}
                            maxLength={5000}
                            className="campo mt-1"
                            value={data.descripcion}
                            onChange={(e) =>
                                setData('descripcion', e.target.value)
                            }
                        />
                        <InputError
                            className="mt-2"
                            message={errors.descripcion}
                        />
                    </div>

                    <div className="flex flex-col-reverse gap-4 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs text-ink-subtle">
                            <span className="text-danger">*</span> Obligatorio.
                            El evento no se puede editar una vez registrado.
                        </p>
                        <PrimaryButton disabled={processing || !turnoActivo}>
                            Registrar evento
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
