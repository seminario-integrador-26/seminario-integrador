import Alert from '@/Components/Alert';
import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MapaPuntosMonitoreo, { EncabezadoMapaPuntos } from '@/Components/MapaPuntosMonitoreo';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
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
    /** Dirección y barrio cuando no hay PM (Evento::ubicacion). */
    ubicacion: string | null;
}

interface Props {
    evento: EventoVigente;
    tipos: TipoEventoOpcion[];
    categorias: Opciones;
    puntos: PuntoMapa[];
    tiposPunto: Opciones;
}

const campoClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs ' +
    'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

const bloqueadoClass = 'disabled:cursor-not-allowed disabled:text-atalaya-text-muted';

/**
 * Fe de errata (regla de negocio 1): los datos del evento se muestran
 * bloqueados y solo se registra el motivo de la corrección, con el autor. El
 * evento original no se toca y la errata deja constancia en el libro.
 */
export default function Create({ evento, tipos, categorias, puntos, tiposPunto }: Props) {
    const { data, setData, post, processing, errors } = useForm({ motivo: '' });

    const tipo = tipos.find((t) => t.id === evento.tipo_evento_id);
    const punto = puntos.find((p) => p.id === evento.punto_monitoreo_id);

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
                        Los datos del evento no se modifican: la fe de errata
                        registra el motivo de la corrección, con tu usuario y
                        la hora, y deja constancia en el libro de guardia.
                    </Alert>

                    <form
                        onSubmit={submit}
                        className="grid gap-6 border border-atalaya-border bg-atalaya-surface p-6 lg:grid-cols-5"
                    >
                        <div className="space-y-6 lg:col-span-2">
                            <fieldset disabled className="space-y-6">
                                <div>
                                    <InputLabel htmlFor="tipo_evento_id" value="Tipo de evento" />
                                    <TextInput
                                        id="tipo_evento_id"
                                        className={'mt-1 block w-full ' + bloqueadoClass}
                                        value={tipo ? `${tipo.nombre} · ${categorias[tipo.categoria] ?? ''}` : ''}
                                        readOnly
                                    />
                                </div>

                                <div className="grid gap-6 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="fecha" value="Fecha del hecho" />
                                        <TextInput
                                            id="fecha"
                                            type="date"
                                            className={'mt-1 block w-full ' + bloqueadoClass}
                                            value={evento.fecha}
                                            readOnly
                                        />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="hora" value="Horario del hecho" />
                                        <TextInput
                                            id="hora"
                                            type="time"
                                            step="1"
                                            className={'mt-1 block w-full ' + bloqueadoClass}
                                            value={evento.hora}
                                            readOnly
                                        />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel htmlFor="punto_monitoreo_id" value="Ubicación" />
                                    <TextInput
                                        id="punto_monitoreo_id"
                                        className={'mt-1 block w-full ' + bloqueadoClass}
                                        value={
                                            punto
                                                ? `${punto.codigo} — ${punto.nombre} · ${tiposPunto[punto.tipo] ?? ''}`
                                                : (evento.ubicacion ?? 'Sin ubicación')
                                        }
                                        readOnly
                                    />
                                </div>

                                <div>
                                    <InputLabel htmlFor="timestamp_video" value="Timestamp del video (hh:mm:ss)" />
                                    <TextInput
                                        id="timestamp_video"
                                        type="time"
                                        step="1"
                                        className={'mt-1 block w-full ' + bloqueadoClass}
                                        value={evento.timestamp_video ?? ''}
                                        readOnly
                                    />
                                </div>

                                <div>
                                    <InputLabel htmlFor="descripcion" value="Descripción" />
                                    <textarea
                                        id="descripcion"
                                        rows={4}
                                        className={campoClass + ' ' + bloqueadoClass}
                                        value={evento.descripcion ?? ''}
                                        readOnly
                                    />
                                </div>
                            </fieldset>

                            <div className="border-t border-atalaya-border pt-6">
                                <InputLabel htmlFor="motivo">
                                    Motivo de la corrección
                                    <span className="text-atalaya-crimson"> *</span>
                                </InputLabel>
                                <textarea
                                    id="motivo"
                                    rows={4}
                                    maxLength={1000}
                                    autoFocus
                                    placeholder="Ej.: se cargó el PM equivocado; el hecho fue en la esquina de enfrente (PM-M-045)."
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
                                seleccionadoId={evento.punto_monitoreo_id}
                                className="h-[420px] w-full border border-atalaya-border lg:h-auto lg:min-h-[560px] lg:flex-1"
                            />
                        </div>

                        <div className="flex items-center justify-end gap-4 border-t border-atalaya-border pt-4 lg:col-span-5">
                            <PrimaryButton disabled={processing || data.motivo.trim().length < 5}>
                                Registrar fe de errata
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
