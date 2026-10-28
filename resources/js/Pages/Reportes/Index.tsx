import FlashMessages from '@/Components/FlashMessages';
import InputLabel from '@/Components/InputLabel';
import { Accion, Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Paginacion, Tabla } from '@/Components/Tabla';
import SecondaryButton from '@/Components/SecondaryButton';
import FechaInput from '@/Components/FechaInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginador } from '@/types';
import { Head, router } from '@inertiajs/react';

/** Apertura de turno y su cierre (LibroGuardiaService::aperturas). */
interface Guardia {
    /** Número del evento Apertura de turno. */
    id: number;
    /** dd/mm/aaaa */
    fecha: string;
    /** hh:mm */
    inicio: string;
    /** null: todavía no se registró el Cierre de turno. */
    fin: string | null;
    fecha_fin: string | null;
    supervisor: string | null;
    eventos: number | null;
}

/**
 * US-012: elegir una Apertura de turno y descargar el Libro de guardia en PDF,
 * hasta el Cierre de turno siguiente.
 */
export default function Index({ turnos, filtros }: { turnos: Paginador<Guardia>; filtros: { fecha: string | null } }) {
    const filtrarPorFecha = (fecha: string) =>
        router.get(
            route('reportes.index'),
            fecha ? { fecha } : {},
            { preserveState: true, replace: true },
        );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Libro de guardia
                </h2>
            }
        >
            <Head title="Reportes" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    <div className="flex flex-wrap items-end gap-4 border border-atalaya-border bg-atalaya-surface p-4">
                        <div>
                            <InputLabel htmlFor="fecha" value="Fecha del turno" />
                            <FechaInput
                                id="fecha"
                                className="mt-1 w-40"
                                value={filtros.fecha ?? ''}
                                onChange={(e) => filtrarPorFecha(e.target.value)}
                            />
                        </div>
                        {filtros.fecha && (
                            <SecondaryButton onClick={() => filtrarPorFecha('')}>
                                Ver todos
                            </SecondaryButton>
                        )}
                        <p className="ml-auto max-w-sm text-xs text-atalaya-text-dim">
                            Cada fila es una Apertura de turno. El PDF incluye
                            todo lo registrado hasta el Cierre de turno
                            siguiente; sin cierre, el turno sigue en curso.
                        </p>
                    </div>

                    <Tabla>
                        <Cabecera>
                            <Encabezado>Apertura</Encabezado>
                            <Encabezado>Fecha</Encabezado>
                            <Encabezado>Horario</Encabezado>
                            <Encabezado>Supervisor</Encabezado>
                            <Encabezado alinear="derecha">Novedades</Encabezado>
                            <Encabezado alinear="derecha">Acciones</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {turnos.data.length === 0 && (
                                <FilaVacia columnas={6}>
                                    No hay aperturas de turno{filtros.fecha ? ' en esa fecha' : ''}.
                                </FilaVacia>
                            )}
                            {turnos.data.map((t) => (
                                <Fila key={t.id}>
                                    <Celda tono="tenue" mono>#{t.id}</Celda>
                                    <Celda tono="principal" mono nowrap>{t.fecha}</Celda>
                                    <Celda mono nowrap>
                                        {t.inicio} – {t.fin ?? 'en curso'}
                                        {t.fecha_fin && t.fecha_fin !== t.fecha && (
                                            <span className="ml-1 text-xs text-atalaya-text-dim">({t.fecha_fin})</span>
                                        )}
                                    </Celda>
                                    <Celda>{t.supervisor}</Celda>
                                    <Celda alinear="derecha" mono>{t.eventos ?? '—'}</Celda>
                                    <CeldaAcciones>
                                        {t.fin && (
                                            <Accion
                                                icono="descargar"
                                                etiqueta={`Descargar el Libro de guardia desde la apertura #${t.id}`}
                                                href={route('reportes.guardia.pdf', t.id)}
                                                descarga
                                            />
                                        )}
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>

                    <Paginacion paginador={turnos} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
