import Alert from '@/Components/Alert';
import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MapaPuntosMonitoreo, {
    EncabezadoMapaPuntos,
} from '@/Components/MapaPuntosMonitoreo';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectorPuntoMonitoreo from '@/Components/SelectorPuntoMonitoreo';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type {
    Opciones,
    PageProps,
    PuntoMapa,
    TipoEventoOpcion,
} from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    tipos: TipoEventoOpcion[];
    categorias: Opciones;
    puntos: PuntoMapa[];
    /** PM precargado al llegar desde el mapa (?punto_monitoreo_id=X). */
    puntoInicial?: number | null;
    tiposPunto: Opciones;
}

type FormEvento = {
    tipo_evento_id: string;
    fecha: string;
    hora: string;
    punto_monitoreo_id: number | null;
    timestamp_video: string;
    descripcion: string;
};

const campoClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs '
    + 'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

// Fecha local en formato YYYY-MM-DD.
const hoy = () => new Date().toLocaleDateString('en-CA');

function Obligatorio() {
    return <span className="text-atalaya-crimson"> *</span>;
}

/** Qué conviene anotar en la descripción de los eventos de turno. */
const AYUDA_DESCRIPCION: Record<string, string> = {
    'Apertura de turno': 'Personal presente en la guardia.',
    'Cierre de turno': 'Novedades pendientes para el turno siguiente.',
};

export default function Create({
    tipos,
    categorias,
    puntos,
    puntoInicial = null,
    tiposPunto,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<FormEvento>({
        tipo_evento_id: '',
        fecha: hoy(),
        hora: '',
        // Precargado cuando se llega desde un PM del mapa del panel.
        punto_monitoreo_id: puntoInicial,
        timestamp_video: '',
        descripcion: '',
    });

    const { flash } = usePage<PageProps>().props;

    const tipoSeleccionado = tipos.find(
        (t) => String(t.id) === String(data.tipo_evento_id),
    );
    const requiereUbicacion = Boolean(tipoSeleccionado?.requiere_ubicacion);
    const hayPunto = data.punto_monitoreo_id !== null;
    // Apertura y Cierre de turno se registran como cualquier evento: lo que
    // antes pedía la tarjeta de turno va en la descripción.
    const ayudaDescripcion = AYUDA_DESCRIPCION[tipoSeleccionado?.nombre ?? ''];

    const seleccionarPunto = (id: number | null) =>
        setData((d) => ({
            ...d,
            punto_monitoreo_id: id,
            timestamp_video: id ? d.timestamp_video : '',
        }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('eventos.store'), {
            // Con errores se queda donde está (al lado del campo); al registrar
            // vuelve arriba, donde aparece la confirmación.
            preserveScroll: (page) => Object.keys(page.props.errors).length > 0,
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Registrar evento
                </h2>
            }
        >
            <Head title="Registrar evento" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash?.eventoRegistrado ? (
                        <Alert variant="success">
                            {flash.success} Ya podés cargar el siguiente.{' '}
                            <Link
                                href={route('eventos.show', flash.eventoRegistrado)}
                                className="font-bold underline underline-offset-2 hover:text-white"
                            >
                                Ver evento #{flash.eventoRegistrado}
                            </Link>
                        </Alert>
                    ) : (
                        <FlashMessages />
                    )}

                    <form
                        onSubmit={submit}
                        className="grid gap-6 border border-atalaya-border bg-atalaya-surface p-6 lg:grid-cols-5"
                    >
                        <div className="space-y-6 lg:col-span-2">
                            <div>
                                <InputLabel htmlFor="tipo_evento_id">
                                    Tipo de evento
                                    <Obligatorio />
                                </InputLabel>
                                <Select
                                    id="tipo_evento_id"
                                    buscable
                                    className="mt-1"
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
                                </Select>
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
                                        <span className="font-normal text-atalaya-text-dim">
                                            {' '}
                                            (opcional)
                                        </span>
                                    )}
                                </InputLabel>
                                <SelectorPuntoMonitoreo
                                    id="punto_monitoreo_id"
                                    puntos={puntos}
                                    tiposPunto={tiposPunto}
                                    value={data.punto_monitoreo_id}
                                    onChange={seleccionarPunto}
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.punto_monitoreo_id}
                                />
                                <p className="mt-1 text-xs text-atalaya-text-dim">
                                    Buscalo por código o dirección, o hacé clic en
                                    el mapa.
                                </p>
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
                                    className="mt-1 block w-full disabled:bg-atalaya-canvas"
                                    value={data.timestamp_video}
                                    onChange={(e) =>
                                        setData('timestamp_video', e.target.value)
                                    }
                                />
                                {!hayPunto && (
                                    <p className="mt-1 text-xs text-atalaya-text-dim">
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
                                <InputLabel htmlFor="descripcion">
                                    Descripción
                                    <Obligatorio />
                                </InputLabel>
                                <textarea
                                    id="descripcion"
                                    rows={4}
                                    maxLength={5000}
                                    className={campoClass}
                                    value={data.descripcion}
                                    placeholder={ayudaDescripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.descripcion}
                                />
                            </div>
                        </div>

                        <div className="flex flex-col lg:col-span-3">
                            <EncabezadoMapaPuntos />
                            <MapaPuntosMonitoreo
                                puntos={puntos}
                                seleccionadoId={data.punto_monitoreo_id}
                                onSelect={seleccionarPunto}
                                className="h-[420px] w-full border border-atalaya-border lg:h-auto lg:min-h-[560px] lg:flex-1"
                            />
                        </div>

                        <div className="flex items-center justify-between gap-4 border-t border-atalaya-border pt-4 lg:col-span-5">
                            <p className="text-xs text-atalaya-text-dim">
                                <span className="text-atalaya-crimson">*</span>{' '}
                                Obligatorio. El evento no se puede editar una
                                vez registrado.
                            </p>
                            <PrimaryButton
                                disabled={processing}
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
