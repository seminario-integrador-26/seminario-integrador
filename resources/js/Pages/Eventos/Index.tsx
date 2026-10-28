import EstadoNotificaciones from '@/Components/EstadoNotificaciones';
import FlashMessages from '@/Components/FlashMessages';
import { Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Paginacion, Tabla } from '@/Components/Tabla';
import InputError from '@/Components/InputError';
import HoraInput from '@/Components/HoraInput';
import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import FechaInput from '@/Components/FechaInput';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { ItemCatalogo, Notificacion, Opciones, PageProps, Paginador, PuntoMapa, TipoEventoOpcion } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/** Fila de la tabla (EventoController::index). */
interface EventoFila {
    id: number;
    /** dd/mm/aaaa hh:mm:ss */
    fecha_hora: string;
    tipo: string;
    /** Nombre del GrupoEvento. */
    categoria: string;
    informativo: boolean;
    /** "CODIGO — Nombre" o null si no tiene ubicación. */
    punto: string | null;
    descripcion: string | null;
    autor: string | null;
    notificaciones: Notificacion[];
    corregido: boolean;
}

/** Filtros de la búsqueda (BuscarEventosRequest); vacío = '' o []. */
interface Filtros {
    categorias: string[];
    tipo_evento_id: string;
    punto_monitoreo_id: string;
    tipo_punto: string;
    barrio_id: string;
    cuadrante_id: string;
    desde: string;
    hasta: string;
    hora_desde: string;
    hora_hasta: string;
    texto: string;
    alcance: string;
    orden: string;
    direccion: '' | 'asc' | 'desc';
}

type CampoTexto = Exclude<keyof Filtros, 'categorias'>;

interface Props {
    eventos: Paginador<EventoFila>;
    filtros: Partial<Filtros>;
    tipos: TipoEventoOpcion[];
    categorias: Opciones;
    puntos: PuntoMapa[];
    tiposPunto: Opciones;
    barrios: ItemCatalogo[];
    cuadrantes: ItemCatalogo[];
}

interface Exportacion {
    estado: 'generando' | 'ok' | 'error';
    formato?: string;
    mensaje?: string;
}

const categoriaBadge: Record<string, string> = {
    Policía: 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    'Guardia Local':
        'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    Internos: 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

const filtrosVacios: Filtros = {
    categorias: [],
    tipo_evento_id: '',
    punto_monitoreo_id: '',
    tipo_punto: '',
    barrio_id: '',
    cuadrante_id: '',
    desde: '',
    hasta: '',
    hora_desde: '',
    hora_hasta: '',
    texto: '',
    alcance: '',
    orden: '',
    direccion: '',
};

// US-011: eventos informativos (tipo no cuantificable) y sin ubicación.
const alcances: Opciones = {
    con_ubicacion: 'Solo con ubicación',
    sin_ubicacion: 'Solo sin ubicación',
    informativos: 'Solo informativos',
    sin_informativos: 'Excluir informativos',
};

// Columnas de la tabla; `orden` = clave de ordenamiento del backend (US-009).
const columnas: { titulo: string; orden?: string; alinear?: 'derecha' }[] = [
    { titulo: '#', orden: 'id' },
    { titulo: 'Fecha y hora', orden: 'fecha_hora' },
    { titulo: 'Tipo', orden: 'tipo' },
    { titulo: 'Ubicación', orden: 'punto' },
    { titulo: 'Descripción' },
    { titulo: 'Registró', orden: 'autor' },
    { titulo: 'Notificación', alinear: 'derecha' },
];

/** Quita los filtros vacíos para no ensuciar la URL. */
const conValor = (valores: Partial<Filtros>) =>
    Object.fromEntries(
        Object.entries(valores).filter(([, v]) =>
            Array.isArray(v) ? v.length > 0 : v !== '' && v != null,
        ),
    );

export default function Index({
    eventos,
    filtros,
    tipos,
    categorias,
    puntos,
    tiposPunto,
    barrios,
    cuadrantes,
}: Props) {
    const { errors, auth } = usePage<PageProps>().props;
    // US-021: si se llegó desde un gráfico de Análisis, ofrecer volver.
    const desdeAnalisis = usePage().url.includes('origen=analisis');
    const puedeReenviar = auth.roles.includes('Supervisor');
    const puedeExportar = auth.permissions.includes('reportes.exportar');
    const [exportacion, setExportacion] = useState<Exportacion | null>(null);
    const [form, setForm] = useState<Filtros>({ ...filtrosVacios, ...filtros });
    const primeraVez = useRef(true);

    // US-008: los resultados se actualizan solos al cambiar un filtro
    // (con una pequeña espera para no buscar en cada tecla).
    useEffect(() => {
        if (primeraVez.current) {
            primeraVez.current = false;
            return;
        }

        const espera = setTimeout(
            () =>
                router.get(route('eventos.index'), conValor(form), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            350,
        );

        return () => clearTimeout(espera);
    }, [form]);

    const cambiar = (campo: CampoTexto) => (e: { target: { value: string } }) =>
        setForm((actual) => ({ ...actual, [campo]: e.target.value }));

    const alternarCategoria = (clave: string) =>
        setForm((actual) => ({
            ...actual,
            categorias: actual.categorias.includes(clave)
                ? actual.categorias.filter((c) => c !== clave)
                : [...actual.categorias, clave],
        }));

    const ordenarPor = (orden: string) =>
        setForm((actual) => ({
            ...actual,
            orden,
            direccion:
                (actual.orden || 'fecha_hora') === orden &&
                (actual.direccion || 'desc') === 'desc'
                    ? 'asc'
                    : 'desc',
        }));

    // US-013: descarga la planilla (.xlsx o .xls) con los filtros aplicados y confirma al terminar.
    const exportar = async (formato: string) => {
        setExportacion({ estado: 'generando', formato });

        try {
            const respuesta = await fetch(
                route('reportes.eventos.planilla', { ...conValor(form), formato }),
                { headers: { Accept: 'application/octet-stream' } },
            );

            if (respuesta.status === 422) {
                // Motivo legible del servidor (p. ej. demasiados eventos para .xls).
                setExportacion({ estado: 'error', mensaje: await respuesta.text() });
                return;
            }

            if (!respuesta.ok) {
                throw new Error();
            }

            const nombre =
                /filename="([^"]+)"/.exec(
                    respuesta.headers.get('Content-Disposition') ?? '',
                )?.[1] ?? `Eventos_CMU.${formato}`;
            const url = URL.createObjectURL(await respuesta.blob());
            const enlace = Object.assign(document.createElement('a'), {
                href: url,
                download: nombre,
            });
            enlace.click();
            URL.revokeObjectURL(url);

            setExportacion({
                estado: 'ok',
                mensaje: `Exportación completada: ${nombre} (${respuesta.headers.get('X-Cantidad-Eventos')} novedades).`,
            });
        } catch {
            setExportacion({
                estado: 'error',
                mensaje: 'No se pudo generar la exportación. Probá de nuevo.',
            });
        }
    };

    const ordenActual = form.orden || 'fecha_hora';
    const direccionActual = form.direccion || 'desc';
    const hayFiltros = Object.keys(
        conValor({ ...form, orden: '', direccion: '' }),
    ).length > 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Novedades
                </h2>
            }
        >
            <Head title="Novedades" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    {desdeAnalisis && (
                        <div className="flex flex-wrap items-center gap-4 text-sm">
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                            >
                                ← Volver al análisis
                            </button>
                        </div>
                    )}

                    <form
                        onSubmit={(e) => e.preventDefault()}
                        className="space-y-4 border border-atalaya-border bg-atalaya-surface p-6"
                    >
                        <div>
                            <InputLabel value="Categoría" />
                            <div className="mt-1 flex flex-wrap gap-2">
                                {Object.entries(categorias).map(
                                    ([clave, etiqueta]) => {
                                        const activa =
                                            form.categorias.includes(clave);

                                        return (
                                            <button
                                                key={clave}
                                                type="button"
                                                aria-pressed={activa}
                                                onClick={() =>
                                                    alternarCategoria(clave)
                                                }
                                                className={
                                                    'border px-3 py-1 font-mono text-[11px] font-bold uppercase ' +
                                                    (activa
                                                        ? categoriaBadge[
                                                              etiqueta
                                                          ]
                                                        : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                                                }
                                            >
                                                {etiqueta}
                                            </button>
                                        );
                                    },
                                )}
                            </div>
                            <InputError message={errors.categorias} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                            <div className="lg:col-span-2">
                                <InputLabel htmlFor="tipo_evento_id" value="Tipo" />
                                <Select
                                    id="tipo_evento_id"
                                    buscable
                                    className="mt-1"
                                    value={form.tipo_evento_id}
                                    onChange={cambiar('tipo_evento_id')}
                                >
                                    <option value="">Todos</option>
                                    {tipos.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.nombre}
                                        </option>
                                    ))}
                                </Select>
                                <InputError message={errors.tipo_evento_id} />
                            </div>

                            <div className="lg:col-span-2">
                                <InputLabel
                                    htmlFor="punto_monitoreo_id"
                                    value="Punto de monitoreo"
                                />
                                <Select
                                    id="punto_monitoreo_id"
                                    buscable
                                    className="mt-1"
                                    value={form.punto_monitoreo_id}
                                    onChange={cambiar('punto_monitoreo_id')}
                                >
                                    <option value="">Todos</option>
                                    {puntos.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.codigo} — {p.nombre}
                                        </option>
                                    ))}
                                </Select>
                                <InputError
                                    message={errors.punto_monitoreo_id}
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="tipo_punto"
                                    value="Tipo de PM"
                                />
                                <Select
                                    id="tipo_punto"
                                    buscable
                                    className="mt-1"
                                    value={form.tipo_punto}
                                    onChange={cambiar('tipo_punto')}
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(tiposPunto).map(
                                        ([clave, etiqueta]) => (
                                            <option key={clave} value={clave}>
                                                {etiqueta}
                                            </option>
                                        ),
                                    )}
                                </Select>
                                <InputError message={errors.tipo_punto} />
                            </div>

                            <div>
                                <InputLabel htmlFor="barrio_id" value="Barrio" />
                                <Select
                                    id="barrio_id"
                                    buscable
                                    className="mt-1"
                                    value={form.barrio_id}
                                    onChange={cambiar('barrio_id')}
                                >
                                    <option value="">Todos</option>
                                    {barrios.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.nombre}
                                        </option>
                                    ))}
                                </Select>
                                <InputError message={errors.barrio_id} />
                            </div>

                            <div>
                                <InputLabel htmlFor="desde" value="Fecha desde" />
                                <FechaInput
                                    id="desde"
                                    className="mt-1 block w-full"
                                    value={form.desde}
                                    onChange={cambiar('desde')}
                                />
                                <InputError message={errors.desde} />
                            </div>

                            <div>
                                <InputLabel htmlFor="hasta" value="Fecha hasta" />
                                <FechaInput
                                    id="hasta"
                                    className="mt-1 block w-full"
                                    value={form.hasta}
                                    onChange={cambiar('hasta')}
                                />
                                <InputError message={errors.hasta} />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="hora_desde"
                                    value="Hora desde"
                                />
                                <HoraInput
                                    id="hora_desde"
                                    className="mt-1 block w-full"
                                    value={form.hora_desde}
                                    onChange={cambiar('hora_desde')}
                                />
                                <InputError message={errors.hora_desde} />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="hora_hasta"
                                    value="Hora hasta"
                                />
                                <HoraInput
                                    id="hora_hasta"
                                    className="mt-1 block w-full"
                                    value={form.hora_hasta}
                                    onChange={cambiar('hora_hasta')}
                                />
                                <InputError message={errors.hora_hasta} />
                            </div>

                            <div>
                                <InputLabel htmlFor="alcance" value="Mostrar" />
                                <Select
                                    id="alcance"
                                    buscable
                                    className="mt-1"
                                    value={form.alcance}
                                    onChange={cambiar('alcance')}
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(alcances).map(([clave, etiqueta]) => (
                                        <option key={clave} value={clave}>
                                            {etiqueta}
                                        </option>
                                    ))}
                                </Select>
                                <InputError message={errors.alcance} />
                            </div>

                            {/* Cuadrante: solo si hay cuadrantes cargados. */}
                            {cuadrantes.length > 0 && (
                                <div>
                                    <InputLabel htmlFor="cuadrante_id" value="Cuadrante" />
                                    <Select
                                        id="cuadrante_id"
                                        buscable
                                        className="mt-1"
                                        value={form.cuadrante_id}
                                        onChange={cambiar('cuadrante_id')}
                                    >
                                        <option value="">Todos</option>
                                        {cuadrantes.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.nombre}
                                            </option>
                                        ))}
                                    </Select>
                                    <InputError message={errors.cuadrante_id} />
                                </div>
                            )}

                            <div className={cuadrantes.length > 0 ? 'md:col-span-3 lg:col-span-6' : ''}>
                                <InputLabel
                                    htmlFor="texto"
                                    value="Texto en la descripción"
                                />
                                <TextInput
                                    id="texto"
                                    type="search"
                                    placeholder="Palabras clave…"
                                    className="mt-1 block w-full"
                                    value={form.texto}
                                    onChange={cambiar('texto')}
                                />
                                <InputError message={errors.texto} />
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-atalaya-text-dim">
                                Los filtros se combinan entre sí. Si la hora
                                desde es mayor que la hora hasta, la franja
                                cruza la medianoche.
                            </p>
                            <SecondaryButton
                                type="button"
                                disabled={!hayFiltros}
                                onClick={() =>
                                    setForm({
                                        ...filtrosVacios,
                                        orden: form.orden,
                                        direccion: form.direccion,
                                    })
                                }
                            >
                                Limpiar filtros
                            </SecondaryButton>
                        </div>
                    </form>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-atalaya-text-muted">
                            {eventos.total === 0
                                ? 'Sin resultados.'
                                : `Mostrando ${eventos.from}–${eventos.to} de ${eventos.total} novedades.`}
                        </p>
                        {puedeExportar && (
                            <div className="flex items-center gap-2">
                                {['xlsx', 'xls'].map((formato) => (
                                    <SecondaryButton
                                        key={formato}
                                        type="button"
                                        onClick={() => exportar(formato)}
                                        disabled={
                                            eventos.total === 0 ||
                                            exportacion?.estado === 'generando'
                                        }
                                    >
                                        {exportacion?.estado === 'generando' && exportacion.formato === formato
                                            ? 'Generando…'
                                            : `Exportar .${formato}`}
                                    </SecondaryButton>
                                ))}
                            </div>
                        )}
                    </div>

                    {exportacion?.mensaje && (
                        <p
                            role="status"
                            className={
                                'border px-4 py-2 text-sm ' +
                                (exportacion.estado === 'ok'
                                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200'
                                    : 'border-atalaya-crimson/50 bg-atalaya-crimson/10 text-red-100')
                            }
                        >
                            {exportacion.mensaje}
                        </p>
                    )}

                    <Tabla>
                        <Cabecera>
                            {columnas.map(({ titulo, orden, alinear }) => (
                                <Encabezado
                                    key={titulo}
                                    alinear={alinear}
                                    orden={orden}
                                    ordenActual={ordenActual}
                                    direccion={direccionActual}
                                    onOrdenar={ordenarPor}
                                >
                                    {titulo}
                                </Encabezado>
                            ))}
                        </Cabecera>
                        <Cuerpo>
                            {eventos.data.length === 0 && (
                                <FilaVacia columnas={columnas.length}>No hay novedades registradas con esos filtros.</FilaVacia>
                            )}
                            {eventos.data.map((e) => (
                                <Fila key={e.id} onClick={() => router.visit(route('eventos.show', e.id))}>
                                    <Celda tono="tenue" mono nowrap>
                                        {e.id}
                                        {e.corregido && (
                                            <div className="mt-1">
                                                <Insignia
                                                    color="border-atalaya-amber/60 text-atalaya-amber"
                                                    title="Tiene fe de errata: el detalle muestra los datos vigentes"
                                                >
                                                    Corregido
                                                </Insignia>
                                            </div>
                                        )}
                                    </Celda>
                                    <Celda tono="principal" mono nowrap>
                                        {e.fecha_hora}
                                    </Celda>
                                    <Celda>
                                        <div className="font-medium text-white">{e.tipo}</div>
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            <Insignia color={categoriaBadge[e.categoria]}>{e.categoria}</Insignia>
                                            {e.informativo && (
                                                <Insignia title="Tipo no cuantificable: no entra en las estadísticas">
                                                    Informativo
                                                </Insignia>
                                            )}
                                        </div>
                                    </Celda>
                                    <Celda tono="tenue">
                                        {e.punto ?? (
                                            <Insignia color="border-atalaya-cyan/60 text-atalaya-cyan">Sin ubicación</Insignia>
                                        )}
                                    </Celda>
                                    <Celda className="max-w-md">{e.descripcion ?? '—'}</Celda>
                                    <Celda tono="tenue" nowrap>{e.autor}</Celda>
                                    <CeldaAcciones>
                                        <EstadoNotificaciones notificaciones={e.notificaciones} puedeReenviar={puedeReenviar} />
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>

                    <Paginacion paginador={eventos} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
