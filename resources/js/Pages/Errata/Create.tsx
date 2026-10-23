import Alert from '@/Components/Alert';
import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MapaPuntosMonitoreo, { EncabezadoMapaPuntos } from '@/Components/MapaPuntosMonitoreo';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectorPuntoMonitoreo from '@/Components/SelectorPuntoMonitoreo';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Opciones, PuntoMapa, TipoEventoOpcion } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

/** Valores vigentes del evento a corregir (ErrataController::create). */
interface EventoVigente {
    id: number;
    tipo_evento_id: number;
    /** aaaa-mm-dd */
    fecha: string;
    /** hh:mm:ss */
    hora: string;
    punto_monitoreo_id: number | null;
    timestamp_video: string | null;
    descripcion: string | null;
}

interface Props {
    evento: EventoVigente;
    tipos: TipoEventoOpcion[];
    categorias: Opciones;
    puntos: PuntoMapa[];
    tiposPunto: Opciones;
}

type DatosCorregibles = {
    tipo_evento_id: string;
    fecha: string;
    hora: string;
    punto_monitoreo_id: number | null;
    timestamp_video: string;
    descripcion: string;
};

const campoClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs ' +
    'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

const hoy = () => new Date().toLocaleDateString('en-CA');

/** hh:mm y hh:mm:ss se comparan igual. */
const normalizar = (v: string | number | null | undefined) =>
    v === null || v === undefined || v === ''
        ? ''
        : /^\d{2}:\d{2}$/.test(String(v))
          ? `${v}:00`
          : String(v);

function Cambio({ cambiado }: { cambiado: boolean }) {
    return cambiado ? (
        <span className="ml-2 font-mono text-[10px] font-bold uppercase text-atalaya-amber">
            · corregido
        </span>
    ) : null;
}

/**
 * Fe de errata (regla de negocio 1): se corrigen los datos vigentes del evento
 * sin tocar el original. Cada campo cambiado queda registrado aparte con el
 * valor anterior, el nuevo, el motivo y el autor.
 */
export default function Create({ evento, tipos, categorias, puntos, tiposPunto }: Props) {
    const original: DatosCorregibles = {
        tipo_evento_id: String(evento.tipo_evento_id),
        fecha: evento.fecha,
        hora: evento.hora,
        punto_monitoreo_id: evento.punto_monitoreo_id,
        timestamp_video: evento.timestamp_video ?? '',
        descripcion: evento.descripcion ?? '',
    };

    const { data, setData, post, processing, errors } = useForm<DatosCorregibles & { motivo: string }>({
        ...original,
        motivo: '',
    });

    const cambio = (campo: keyof DatosCorregibles) => normalizar(data[campo]) !== normalizar(original[campo]);
    const cambiados = (Object.keys(original) as (keyof DatosCorregibles)[]).filter(cambio);

    const tipoSeleccionado = tipos.find((t) => String(t.id) === String(data.tipo_evento_id));
    const requiereUbicacion = Boolean(tipoSeleccionado?.requiere_ubicacion);
    const hayPunto = data.punto_monitoreo_id !== null;

    const seleccionarPunto = (id: number | null) =>
        setData((d) => ({
            ...d,
            punto_monitoreo_id: id,
            timestamp_video: id ? d.timestamp_video : '',
        }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('erratas.store', evento.id), {
            // Con errores se queda donde está; al registrar va al detalle del
            // evento desde arriba, donde aparece la confirmación.
            preserveScroll: (page) => Object.keys(page.props.errors).length > 0,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                        Fe de errata · Evento #{evento.id}
                    </h2>
                    <Link
                        href={route('eventos.show', evento.id)}
                        className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                    >
                        ← Volver al evento
                    </Link>
                </div>
            }
        >
            <Head title={`Fe de errata · Evento #${evento.id}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    <Alert variant="info">
                        El evento original no se modifica: cada dato que
                        cambies queda registrado como fe de errata, con el
                        valor anterior, el nuevo y el motivo.
                    </Alert>

                    <form
                        onSubmit={submit}
                        className="grid gap-6 border border-atalaya-border bg-atalaya-surface p-6 lg:grid-cols-5"
                    >
                        <div className="space-y-6 lg:col-span-2">
                            <div>
                                <InputLabel htmlFor="tipo_evento_id">
                                    Tipo de evento
                                    <Cambio cambiado={cambio('tipo_evento_id')} />
                                </InputLabel>
                                <Select
                                    id="tipo_evento_id"
                                    buscable
                                    className="mt-1"
                                    value={data.tipo_evento_id}
                                    onChange={(e) => setData('tipo_evento_id', e.target.value)}
                                >
                                    {Object.entries(categorias).map(([clave, etiqueta]) => {
                                        const deCategoria = tipos.filter((t) => t.categoria === clave);

                                        return (
                                            deCategoria.length > 0 && (
                                                <optgroup key={clave} label={etiqueta}>
                                                    {deCategoria.map((t) => (
                                                        <option key={t.id} value={t.id}>
                                                            {t.nombre}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            )
                                        );
                                    })}
                                </Select>
                                <InputError className="mt-2" message={errors.tipo_evento_id} />
                            </div>

                            <div className="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="fecha">
                                        Fecha del hecho
                                        <Cambio cambiado={cambio('fecha')} />
                                    </InputLabel>
                                    <TextInput
                                        id="fecha"
                                        type="date"
                                        max={hoy()}
                                        className="mt-1 block w-full"
                                        value={data.fecha}
                                        onChange={(e) => setData('fecha', e.target.value)}
                                    />
                                    <InputError className="mt-2" message={errors.fecha} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="hora">
                                        Horario del hecho
                                        <Cambio cambiado={cambio('hora')} />
                                    </InputLabel>
                                    <TextInput
                                        id="hora"
                                        type="time"
                                        step="1"
                                        className="mt-1 block w-full"
                                        value={data.hora}
                                        onChange={(e) => setData('hora', e.target.value)}
                                    />
                                    <InputError className="mt-2" message={errors.hora} />
                                </div>
                            </div>

                            <div>
                                <InputLabel htmlFor="punto_monitoreo_id">
                                    Punto de monitoreo
                                    {!requiereUbicacion && (
                                        <span className="font-normal text-atalaya-text-dim"> (opcional)</span>
                                    )}
                                    <Cambio cambiado={cambio('punto_monitoreo_id')} />
                                </InputLabel>
                                <SelectorPuntoMonitoreo
                                    id="punto_monitoreo_id"
                                    puntos={puntos}
                                    tiposPunto={tiposPunto}
                                    value={data.punto_monitoreo_id}
                                    onChange={seleccionarPunto}
                                />
                                <InputError className="mt-2" message={errors.punto_monitoreo_id} />
                                <p className="mt-1 text-xs text-atalaya-text-dim">
                                    Buscalo por código o dirección, o hacé clic en el mapa.
                                </p>
                            </div>

                            <div>
                                <InputLabel htmlFor="timestamp_video">
                                    Timestamp del video (hh:mm:ss)
                                    <Cambio cambiado={cambio('timestamp_video')} />
                                </InputLabel>
                                <TextInput
                                    id="timestamp_video"
                                    type="time"
                                    step="1"
                                    disabled={!hayPunto}
                                    className="mt-1 block w-full disabled:bg-atalaya-canvas"
                                    value={data.timestamp_video}
                                    onChange={(e) => setData('timestamp_video', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.timestamp_video} />
                            </div>

                            <div>
                                <InputLabel htmlFor="descripcion">
                                    Descripción
                                    <Cambio cambiado={cambio('descripcion')} />
                                </InputLabel>
                                <textarea
                                    id="descripcion"
                                    rows={4}
                                    maxLength={5000}
                                    className={campoClass}
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.descripcion} />
                            </div>

                            <div className="border-t border-atalaya-border pt-6">
                                <InputLabel htmlFor="motivo">
                                    Motivo de la corrección
                                    <span className="text-atalaya-crimson"> *</span>
                                </InputLabel>
                                <textarea
                                    id="motivo"
                                    rows={3}
                                    maxLength={1000}
                                    placeholder="Ej.: se cargó el PM equivocado; el hecho fue en la esquina de enfrente."
                                    className={campoClass}
                                    value={data.motivo}
                                    onChange={(e) => setData('motivo', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.motivo} />
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
                                {cambiados.length === 0
                                    ? 'Todavía no cambiaste ningún dato.'
                                    : `${cambiados.length} dato(s) a corregir.`}
                            </p>
                            <PrimaryButton disabled={processing || cambiados.length === 0}>
                                Registrar fe de errata
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
