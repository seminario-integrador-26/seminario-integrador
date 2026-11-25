import { colorGrupo } from '@/catalogos';
import { AjustarTamano, CapaCalor, CENTRO_VILLA_MARIA, DrillDownPorZona, GRADIENTE } from '@/Components/CapaCalor';
import FechaInput from '@/Components/FechaInput';
import { PestanasTableros } from '@/Components/Tableros/Encabezado';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Opciones } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { MapContainer, TileLayer } from 'react-leaflet';

/** PM con novedades en el período (MapaService::densidad). */
interface PuntoDensidad {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    tipo_nombre: string;
    latitud: number;
    longitud: number;
    cantidad: number;
}

interface TipoCalor {
    id: number;
    nombre: string;
    /** Código del GrupoEvento. */
    categoria: string;
}

interface Filtros {
    /** Vacío = todos los tipos. */
    tipos?: (string | number)[];
    desde: string;
    hasta: string;
}

interface Props {
    filtros: Filtros;
    densidad: { puntos: PuntoDensidad[]; total: number; sin_ubicacion: number; sin_coordenadas: number };
    /** aaaa-mm-dd de la primera novedad cuantificable (por defecto el mapa arranca ahí). */
    primeraFecha: string;
    tipos: TipoCalor[];
    categorias: Opciones;
}

const dmy = (iso: string) => iso.split('-').reverse().join('/');

export default function Calor({ filtros, densidad, primeraFecha, tipos, categorias }: Props) {
    const hoy = new Date().toLocaleDateString('en-CA');
    const [rango, setRango] = useState({ desde: filtros.desde, hasta: filtros.hasta });
    // Selección en edición; se aplica con el botón (son muchos tipos para recargar en cada clic).
    const [elegidos, setElegidos] = useState<number[]>((filtros.tipos ?? []).map(Number));

    const porGrupo = useMemo(
        () => Object.entries(categorias).map(([clave, nombre]) => ({ clave, nombre, tipos: tipos.filter((t) => t.categoria === clave) })),
        [categorias, tipos],
    );

    const aplicados = (filtros.tipos ?? []).map(Number);
    const pendiente =
        rango.desde !== filtros.desde ||
        rango.hasta !== filtros.hasta ||
        elegidos.length !== aplicados.length ||
        elegidos.some((id) => !aplicados.includes(id));

    const aplicar = (cambios?: Partial<{ desde: string; hasta: string; tipos: number[] }>) => {
        const datos = { desde: rango.desde, hasta: rango.hasta, tipos: elegidos, ...cambios };
        router.get(
            route('tableros.calor'),
            { desde: datos.desde, hasta: datos.hasta, ...(datos.tipos.length > 0 && { tipos: datos.tipos }) },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const todoHastaHoy = () => {
        setRango({ desde: primeraFecha, hasta: hoy });
        setElegidos([]);
        aplicar({ desde: primeraFecha, hasta: hoy, tipos: [] });
    };

    const alternar = (ids: number[], marcar: boolean) =>
        setElegidos((actual) => (marcar ? [...new Set([...actual, ...ids])] : actual.filter((id) => !ids.includes(id))));

    // Drill-down: PM de la zona → consulta de novedades (con el tipo si hay uno solo).
    const verPunto = (p: PuntoDensidad) =>
        router.visit(
            route('eventos.index', {
                punto_monitoreo_id: p.id,
                desde: filtros.desde,
                hasta: filtros.hasta,
                ...(aplicados.length === 1 && { tipo_evento_id: aplicados[0] }),
                origen: 'tableros',
            }),
        );

    const top = densidad.puntos.slice(0, 10);
    const rangoValido = rango.desde && rango.hasta && rango.desde <= rango.hasta;

    return (
        <AuthenticatedLayout
            header={<h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">Tableros</h2>}
        >
            <Head title="Mapa de calor" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <PestanasTableros
                        extra={
                            <span className="font-mono text-xs text-atalaya-text-dim">
                                {densidad.total} novedades · {dmy(filtros.desde)} al {dmy(filtros.hasta)} ·{' '}
                                {aplicados.length === 0 ? 'todos los tipos' : `${aplicados.length} tipo(s)`}
                            </span>
                        }
                    />

                    <div className="flex flex-col gap-4 lg:flex-row">
                        {/* Filtros */}
                        <aside className="space-y-4 border border-atalaya-border bg-atalaya-surface p-4 lg:w-80 lg:shrink-0">
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Desde</p>
                                    <FechaInput value={rango.desde} max={hoy} onChange={(e) => setRango((r) => ({ ...r, desde: e.target.value }))} />
                                </div>
                                <div>
                                    <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Hasta</p>
                                    <FechaInput value={rango.hasta} max={hoy} onChange={(e) => setRango((r) => ({ ...r, hasta: e.target.value }))} />
                                </div>
                            </div>

                            <div>
                                <div className="mb-1 flex items-center justify-between">
                                    <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                                        Tipos {elegidos.length === 0 ? '(todos)' : `(${elegidos.length})`}
                                    </p>
                                    {elegidos.length > 0 && (
                                        <button type="button" onClick={() => setElegidos([])} className="font-mono text-[10px] uppercase text-atalaya-cyan hover:underline">
                                            Todos
                                        </button>
                                    )}
                                </div>
                                <div className="max-h-[46vh] space-y-3 overflow-y-auto pr-1">
                                    {porGrupo.map((g) => {
                                        const ids = g.tipos.map((t) => t.id);
                                        const todosDelGrupo = ids.length > 0 && ids.every((id) => elegidos.includes(id));

                                        return (
                                            <fieldset key={g.clave}>
                                                <legend className="flex w-full items-center justify-between">
                                                    <label className="flex items-center gap-2 text-sm font-semibold text-white">
                                                        <input
                                                            type="checkbox"
                                                            checked={todosDelGrupo}
                                                            onChange={(e) => alternar(ids, e.target.checked)}
                                                            className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan"
                                                        />
                                                        <span className="inline-block h-2.5 w-2.5" style={{ backgroundColor: colorGrupo(g.clave) }} />
                                                        {g.nombre}
                                                    </label>
                                                </legend>
                                                <ul className="ml-6 mt-1 space-y-0.5">
                                                    {g.tipos.map((t) => (
                                                        <li key={t.id}>
                                                            <label className="flex items-center gap-2 text-xs text-atalaya-text-muted hover:text-white">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={elegidos.includes(t.id)}
                                                                    onChange={(e) => alternar([t.id], e.target.checked)}
                                                                    className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan"
                                                                />
                                                                {t.nombre}
                                                            </label>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </fieldset>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => aplicar()}
                                    disabled={!pendiente || !rangoValido}
                                    className="flex-1 border border-atalaya-cyan bg-atalaya-cyan/10 px-3 py-1.5 font-mono text-[11px] font-bold uppercase text-atalaya-cyan hover:bg-atalaya-cyan/25 disabled:opacity-40"
                                >
                                    Generar mapa
                                </button>
                                <button
                                    type="button"
                                    onClick={todoHastaHoy}
                                    className="border border-atalaya-border px-3 py-1.5 font-mono text-[11px] font-bold uppercase text-atalaya-text-muted hover:text-white"
                                    title="Todas las novedades, en todos los puntos, desde la primera hasta hoy"
                                >
                                    Todo
                                </button>
                            </div>

                            {(densidad.sin_ubicacion > 0 || densidad.sin_coordenadas > 0) && (
                                <p className="text-[11px] text-atalaya-text-dim">
                                    No se dibujan {densidad.sin_ubicacion} novedad(es) sin PM
                                    {densidad.sin_coordenadas > 0 && ` y ${densidad.sin_coordenadas} de PMs sin coordenadas`}. Los tipos
                                    informativos (no cuantificables) no entran en el mapa.
                                </p>
                            )}
                        </aside>

                        {/* Mapa */}
                        <div className="flex flex-1 flex-col gap-4">
                            <div className="mapa-tactico relative h-[60vh] border border-atalaya-border">
                                <MapContainer center={CENTRO_VILLA_MARIA} zoom={14} className="h-full w-full" preferCanvas>
                                    <TileLayer
                                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                    />
                                    <AjustarTamano />
                                    <CapaCalor puntos={densidad.puntos} />
                                    <DrillDownPorZona puntos={densidad.puntos} onElegir={verPunto} />
                                </MapContainer>

                                <div className="pointer-events-none absolute bottom-4 left-4 z-[1000] border border-atalaya-border bg-atalaya-surface/90 p-3 font-mono text-[10px] text-atalaya-text-muted">
                                    <div className="mb-1 font-bold text-white">[ Intensidad ]</div>
                                    <div className="flex items-center gap-2">
                                        menos
                                        <span
                                            className="inline-block h-2 w-28"
                                            style={{ background: `linear-gradient(to right, ${Object.values(GRADIENTE).join(', ')})` }}
                                        />
                                        más
                                    </div>
                                    <div className="mt-1">Clic en una zona: novedades del PM</div>
                                </div>

                                {densidad.puntos.length === 0 && (
                                    <div className="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center">
                                        <p className="border border-atalaya-border bg-atalaya-surface/90 px-4 py-2 text-sm text-atalaya-text-muted">
                                            Sin novedades ubicadas para estos filtros.
                                        </p>
                                    </div>
                                )}
                            </div>

                            {top.length > 0 && (
                                <section className="border border-atalaya-border bg-atalaya-surface p-4">
                                    <h3 className="mb-3 font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">PMs con más novedades</h3>
                                    <ol className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                                        {top.map((p, i) => (
                                            <li key={p.id}>
                                                <button type="button" onClick={() => verPunto(p)} className="w-full text-left hover:text-atalaya-cyan">
                                                    <div className="flex justify-between gap-3 text-sm">
                                                        <span className="truncate text-white">
                                                            <span className="mr-2 font-mono text-atalaya-text-dim">{i + 1}.</span>
                                                            {p.codigo} — {p.nombre}
                                                        </span>
                                                        <span className="font-mono text-atalaya-text-muted">{p.cantidad}</span>
                                                    </div>
                                                    <div className="mt-1 h-1.5 bg-atalaya-canvas">
                                                        <div className="h-full bg-atalaya-cyan" style={{ width: `${(p.cantidad / top[0].cantidad) * 100}%` }} />
                                                    </div>
                                                </button>
                                            </li>
                                        ))}
                                    </ol>
                                </section>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
