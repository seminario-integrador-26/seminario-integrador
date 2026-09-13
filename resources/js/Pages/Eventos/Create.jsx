import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MapaPuntosMonitoreo from '@/Components/MapaPuntosMonitoreo';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectorPuntoMonitoreo from '@/Components/SelectorPuntoMonitoreo';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

const campoClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

// Fecha local en formato YYYY-MM-DD.
const hoy = () => new Date().toLocaleDateString('en-CA');

function Obligatorio() {
    return <span className="text-red-600"> *</span>;
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
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Registrar evento
                </h2>
            }
        >
            <Head title="Registrar evento" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    {turnoActivo ? (
                        <div className="rounded-md bg-indigo-50 p-4 text-sm text-indigo-800">
                            Turno #{turnoActivo.id} abierto el{' '}
                            {turnoActivo.fecha} a las {turnoActivo.hora_inicio}
                            {turnoActivo.supervisor &&
                                ` por ${turnoActivo.supervisor}`}
                            . El evento quedará asociado a este turno.
                        </div>
                    ) : (
                        <div className="rounded-md bg-red-50 p-4 text-sm text-red-800">
                            No hay un turno de guardia abierto. Abrí un turno
                            antes de registrar eventos.
                        </div>
                    )}

                    {errors.turno && (
                        <div className="rounded-md bg-red-50 p-4 text-sm text-red-800">
                            {errors.turno}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                    >
                        <div>
                            <InputLabel htmlFor="tipo_evento_id">
                                Tipo de evento
                                <Obligatorio />
                            </InputLabel>
                            <select
                                id="tipo_evento_id"
                                className={campoClass}
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
                                    className="mt-1 block w-full"
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
                                    className="mt-1 block w-full"
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
                                    <span className="font-normal text-gray-500">
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
                                className="mt-1 block w-full disabled:bg-gray-100"
                                value={data.timestamp_video}
                                onChange={(e) =>
                                    setData('timestamp_video', e.target.value)
                                }
                            />
                            {!hayPunto && (
                                <p className="mt-1 text-xs text-gray-500">
                                    Seleccioná un punto de monitoreo para
                                    indicar el momento exacto del video.
                                </p>
                            )}
                            <InputError
                                className="mt-2"
                                message={errors.timestamp_video}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="descripcion"
                                value="Descripción"
                            />
                            <textarea
                                id="descripcion"
                                rows={4}
                                maxLength={5000}
                                className={campoClass}
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

                        <div className="flex items-center justify-between">
                            <p className="text-xs text-gray-500">
                                <span className="text-red-600">*</span>{' '}
                                Obligatorio. El evento no se puede editar una
                                vez registrado.
                            </p>
                            <PrimaryButton
                                disabled={processing || !turnoActivo}
                            >
                                Registrar evento
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
