import Alert from '@/Components/Alert';
import FlashMessages from '@/Components/FlashMessages';
import InputError from '@/Components/InputError';
import HoraInput from '@/Components/HoraInput';
import InputLabel from '@/Components/InputLabel';
import MapaPuntosMonitoreo, {
    EncabezadoMapaPuntos,
} from '@/Components/MapaPuntosMonitoreo';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectorPuntoMonitoreo from '@/Components/SelectorPuntoMonitoreo';
import FechaInput from '@/Components/FechaInput';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type {
    ItemCatalogo,
    Opciones,
    PageProps,
    PuntoMapa,
    TipoEventoOpcion,
} from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface Props {
    tipos: TipoEventoOpcion[];
    categorias: Opciones;
    puntos: PuntoMapa[];
    /** PM precargado al llegar desde el mapa (?punto_monitoreo_id=X). */
    puntoInicial?: number | null;
    tiposPunto: Opciones;
    barrios: ItemCatalogo[];
    /** Apertura de turno vigente; sin ella solo se puede abrir el turno. */
    turno: { id: number; desde: string; guardia: string | null } | null;
    /** Franjas del turno (manana => Mañana) y grupos de guardia (1 a 4). */
    franjas: Opciones;
    gruposGuardia: number[];
}

type FormEvento = {
    tipo_evento_id: string;
    fecha: string;
    hora: string;
    punto_monitoreo_id: number | null;
    /** Sin PM: barrio y dirección a mano. */
    barrio_id: string;
    direccion: string;
    /** Solo la Apertura de turno: franja y grupo de guardia. */
    franja: string;
    grupo_guardia: string;
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

/**
 * Turno que corresponde a una hora (HH:MM): mañana de 6 a 14, tarde de 14 a
 * 22 y noche de 22 a 6 del día siguiente.
 */
const franjaDeHora = (hora: string) => {
    const h = Number(hora.slice(0, 2));
    if (h >= 6 && h < 14) return 'manana';
    if (h >= 14 && h < 22) return 'tarde';
    return 'noche';
};

// Hora local actual (HH:MM).
const horaActual = () => new Date().toTimeString().slice(0, 5);

const esApertura = (t: TipoEventoOpcion) =>
    t.sin_camara && t.nombre === 'Apertura de turno';

export default function Create({
    tipos: todosLosTipos,
    categorias,
    puntos,
    puntoInicial = null,
    tiposPunto,
    barrios,
    franjas,
    gruposGuardia,
    turno,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<FormEvento>({
        tipo_evento_id: '',
        fecha: hoy(),
        hora: '',
        // Precargado cuando se llega desde un PM del mapa del panel.
        punto_monitoreo_id: puntoInicial,
        barrio_id: '',
        direccion: '',
        franja: '',
        grupo_guardia: '',
        descripcion: '',
    });

    const { flash } = usePage<PageProps>().props;

    // El turno se preselecciona por la hora hasta que el Supervisor lo cambia a mano.
    const [franjaElegida, setFranjaElegida] = useState(false);

    // Todo evento pertenece a un turno: sin turno abierto solo se puede
    // registrar la Apertura, y con uno abierto no se abre otro.
    const tipos = todosLosTipos.filter((t) =>
        turno ? !esApertura(t) : esApertura(t),
    );

    const tipoSeleccionado = tipos.find(
        (t) => String(t.id) === String(data.tipo_evento_id),
    );
    const requiereUbicacion = Boolean(tipoSeleccionado?.requiere_ubicacion);
    // Apertura y Cierre de turno no tienen cámara: no se pide PM.
    const sinCamara = Boolean(tipoSeleccionado?.sin_camara);
    const abreTurno = tipoSeleccionado ? esApertura(tipoSeleccionado) : false;
    const hayPunto = data.punto_monitoreo_id !== null;
    const puntoSeleccionado = puntos.find((p) => p.id === data.punto_monitoreo_id);
    // Sin PM la dirección es obligatoria si el tipo exige ubicación; con PM es
    // opcional (la novedad suele no estar justo donde está la cámara).
    const direccionObligatoria = requiereUbicacion && !hayPunto;
    // Apertura y Cierre de turno se registran como cualquier evento: lo que
    // antes pedía la tarjeta de turno va en la descripción.
    const ayudaDescripcion = AYUDA_DESCRIPCION[tipoSeleccionado?.nombre ?? ''];

    const seleccionarPunto = (id: number | null) =>
        setData((d) => ({
            ...d,
            punto_monitoreo_id: id,
            // Con PM, el barrio sale del PM; la dirección se conserva.
            barrio_id: id ? '' : d.barrio_id,
        }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('eventos.store'), {
            // Con errores se queda donde está (al lado del campo); al registrar
            // vuelve arriba, donde aparece la confirmación.
            preserveScroll: (page) => Object.keys(page.props.errors).length > 0,
            onSuccess: () => {
                reset();
                setFranjaElegida(false);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Registrar novedad
                </h2>
            }
        >
            <Head title="Registrar novedad" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash?.eventoRegistrado ? (
                        <Alert variant="success">
                            {flash.success} Ya podés cargar el siguiente.{' '}
                            <Link
                                href={route('eventos.show', flash.eventoRegistrado)}
                                className="font-bold underline underline-offset-2 hover:text-white"
                            >
                                Ver novedad #{flash.eventoRegistrado}
                            </Link>
                        </Alert>
                    ) : (
                        <FlashMessages />
                    )}

                    {turno ? (
                        <p className="font-mono text-xs text-atalaya-text-dim">
                            Turno {turno.guardia ? `${turno.guardia} ` : ''}abierto desde el{' '}
                            {turno.desde} (Apertura #{turno.id}).
                        </p>
                    ) : (
                        <Alert variant="info">
                            No hay un turno abierto: registrá la Apertura de turno
                            para empezar a cargar novedades.
                        </Alert>
                    )}

                    <form
                        onSubmit={submit}
                        className="grid gap-6 border border-atalaya-border bg-atalaya-surface p-6 lg:grid-cols-5"
                    >
                        {/* En pantalla ancha, la descripción crece hasta la base del mapa. */}
                        <div className="space-y-6 lg:col-span-2 lg:flex lg:flex-col">
                            <div>
                                <InputLabel htmlFor="tipo_evento_id">
                                    Tipo de novedad
                                    <Obligatorio />
                                </InputLabel>
                                <Select
                                    id="tipo_evento_id"
                                    buscable
                                    className="mt-1"
                                    value={data.tipo_evento_id}
                                    onChange={(e) => {
                                        const tipo = tipos.find(
                                            (t) => String(t.id) === e.target.value,
                                        );
                                        setData((d) => ({
                                            ...d,
                                            tipo_evento_id: e.target.value,
                                            ...(tipo?.sin_camara && {
                                                punto_monitoreo_id: null,
                                                barrio_id: '',
                                                direccion: '',
                                            }),
                                            ...(tipo && esApertura(tipo) && !franjaElegida && {
                                                franja: franjaDeHora(d.hora || horaActual()),
                                            }),
                                        }));
                                    }}
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
                                        Fecha de la novedad
                                        <Obligatorio />
                                    </InputLabel>
                                    <FechaInput
                                        id="fecha"
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
                                        Horario de la novedad
                                        <Obligatorio />
                                    </InputLabel>
                                    <HoraInput
                                        id="hora"
                                        segundos
                                        className="mt-1 block w-full"
                                        value={data.hora}
                                        onChange={(e) =>
                                            setData((d) => ({
                                                ...d,
                                                hora: e.target.value,
                                                ...(abreTurno && !franjaElegida && e.target.value && {
                                                    franja: franjaDeHora(e.target.value),
                                                }),
                                            }))
                                        }
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={errors.hora}
                                    />
                                </div>
                            </div>

                            {abreTurno && (
                                <div className="grid gap-6 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="franja">
                                            Turno
                                            <Obligatorio />
                                        </InputLabel>
                                        <Select
                                            id="franja"
                                            className="mt-1"
                                            value={data.franja}
                                            onChange={(e) => {
                                                setFranjaElegida(true);
                                                setData('franja', e.target.value);
                                            }}
                                        >
                                            <option value="">Elegí el turno…</option>
                                            {Object.entries(franjas).map(([codigo, etiqueta]) => (
                                                <option key={codigo} value={codigo}>
                                                    {etiqueta}
                                                </option>
                                            ))}
                                        </Select>
                                        <InputError
                                            className="mt-2"
                                            message={errors.franja}
                                        />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="grupo_guardia">
                                            Grupo
                                            <Obligatorio />
                                        </InputLabel>
                                        <Select
                                            id="grupo_guardia"
                                            className="mt-1"
                                            value={data.grupo_guardia}
                                            onChange={(e) =>
                                                setData('grupo_guardia', e.target.value)
                                            }
                                        >
                                            <option value="">Elegí tu grupo…</option>
                                            {gruposGuardia.map((g) => (
                                                <option key={g} value={g}>
                                                    Grupo {g}
                                                </option>
                                            ))}
                                        </Select>
                                        <InputError
                                            className="mt-2"
                                            message={errors.grupo_guardia}
                                        />
                                    </div>
                                </div>
                            )}

                            {!sinCamara && (
                                <>
                                <div>
                                    <InputLabel htmlFor="punto_monitoreo_id">
                                        Punto de monitoreo
                                        {requiereUbicacion ? (
                                            <>
                                                <Obligatorio />
                                                <span className="font-normal text-atalaya-text-dim">
                                                    {' '}
                                                    (si no hay, barrio y dirección)
                                                </span>
                                            </>
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

                                <div className="grid gap-6 sm:grid-cols-2">
                                        {hayPunto ? (
                                            <div>
                                                <InputLabel htmlFor="barrio_pm">
                                                    Barrio
                                                    <span className="font-normal text-atalaya-text-dim">
                                                        {' '}
                                                        (del punto de monitoreo)
                                                    </span>
                                                </InputLabel>
                                                <TextInput
                                                    id="barrio_pm"
                                                    readOnly
                                                    tabIndex={-1}
                                                    className="mt-1 block w-full cursor-default opacity-70"
                                                    value={puntoSeleccionado?.barrio ?? 'Sin barrio cargado'}
                                                />
                                            </div>
                                        ) : (
                                        <div>
                                            <InputLabel htmlFor="barrio_id">
                                                Barrio
                                                {requiereUbicacion ? (
                                                    <Obligatorio />
                                                ) : (
                                                    <span className="font-normal text-atalaya-text-dim">
                                                        {' '}
                                                        (opcional)
                                                    </span>
                                                )}
                                            </InputLabel>
                                            <Select
                                                id="barrio_id"
                                                buscable
                                                className="mt-1"
                                                value={data.barrio_id}
                                                onChange={(e) =>
                                                    setData('barrio_id', e.target.value)
                                                }
                                            >
                                                <option value="">Elegí un barrio…</option>
                                                {barrios.map((b) => (
                                                    <option key={b.id} value={b.id}>
                                                        {b.nombre}
                                                    </option>
                                                ))}
                                            </Select>
                                            <InputError
                                                className="mt-2"
                                                message={errors.barrio_id}
                                            />
                                        </div>
                                        )}
                                        <div>
                                            <InputLabel htmlFor="direccion">
                                                Dirección
                                                {direccionObligatoria ? (
                                                    <Obligatorio />
                                                ) : (
                                                    <span className="font-normal text-atalaya-text-dim">
                                                        {' '}
                                                        (opcional)
                                                    </span>
                                                )}
                                            </InputLabel>
                                            <TextInput
                                                id="direccion"
                                                maxLength={255}
                                                placeholder="Ej.: San Martín 1234 / Salta y La Rioja"
                                                className="mt-1 block w-full"
                                                value={data.direccion}
                                                onChange={(e) =>
                                                    setData('direccion', e.target.value)
                                                }
                                            />
                                            <InputError
                                                className="mt-2"
                                                message={errors.direccion}
                                            />
                                        </div>
                                    </div>
                                </>
                            )}

                            <div className="lg:flex lg:flex-1 lg:flex-col">
                                <InputLabel htmlFor="descripcion">
                                    Descripción
                                    <Obligatorio />
                                </InputLabel>
                                <textarea
                                    id="descripcion"
                                    rows={4}
                                    maxLength={5000}
                                    className={`${campoClass} lg:flex-1`}
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
                                onSelect={sinCamara ? undefined : seleccionarPunto}
                                className="h-[420px] w-full border border-atalaya-border lg:h-auto lg:min-h-[560px] lg:flex-1"
                            />
                        </div>

                        <div className="flex items-center justify-between gap-4 border-t border-atalaya-border pt-4 lg:col-span-5">
                            <p className="text-xs text-atalaya-text-dim">
                                <span className="text-atalaya-crimson">*</span>{' '}
                                Obligatorio. La novedad no se puede editar una
                                vez registrada.
                            </p>
                            <PrimaryButton
                                disabled={processing}
                            >
                                Registrar novedad
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
