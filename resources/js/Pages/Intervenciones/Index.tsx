import FlashMessages from '@/Components/FlashMessages';
import InputLabel from '@/Components/InputLabel';
import Select from '@/Components/Select';
import { Cabecera, Celda, Cuerpo, Encabezado, Fila, Tabla } from '@/Components/Tabla';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

/** RegistroIntervencionesService::resumen */
interface Resumen {
    /** "OCTUBRE 2026" */
    titulo: string;
    total: number;
    bloques: {
        titulo: string;
        subtotal: number;
        filas: { concepto: string; total: number }[];
    }[];
}

interface Filtros {
    anio: number;
    mes: number;
}

const MESES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
    'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

/**
 * Registro de Intervenciones mensual para el Portal de Transparencia: se elige
 * el mes, se revisan los totales y se descarga el .xlsx con el formato de la
 * planilla oficial (intervenciones-del-centro-de-monitoreo-<mes>-<año>.xlsx).
 */
export default function Index({
    filtros,
    resumen,
    error,
    enCurso,
}: {
    filtros: Filtros;
    resumen: Resumen | null;
    error: string | null;
    enCurso: boolean;
}) {
    const anioActual = new Date().getFullYear();
    const anios = Array.from({ length: 6 }, (_, i) => anioActual - i);

    const cambiar = (campo: keyof Filtros) => (ev: { target: { value: string } }) =>
        router.get(
            route('intervenciones.index'),
            { ...filtros, [campo]: ev.target.value },
            { preserveScroll: true, replace: true },
        );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Registro de intervenciones
                </h2>
            }
        >
            <Head title="Registro de intervenciones" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    <div className="flex flex-wrap items-end gap-4 border border-atalaya-border bg-atalaya-surface p-4">
                        <div className="w-40">
                            <InputLabel htmlFor="mes" value="Mes" />
                            <Select id="mes" className="mt-1" value={filtros.mes} onChange={cambiar('mes')}>
                                {MESES.map((nombre, i) => (
                                    <option key={nombre} value={i + 1}>
                                        {nombre}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="w-28">
                            <InputLabel htmlFor="anio" value="Año" />
                            <Select id="anio" className="mt-1" value={filtros.anio} onChange={cambiar('anio')}>
                                {anios.map((a) => (
                                    <option key={a} value={a}>
                                        {a}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        {resumen && (
                            <a
                                href={route('intervenciones.descargar', { anio: filtros.anio, mes: filtros.mes })}
                                download
                                className="inline-flex items-center justify-center border border-atalaya-cyan bg-atalaya-cyan px-4 py-2 font-mono text-xs font-bold uppercase tracking-widest text-black transition duration-150 ease-in-out hover:bg-cyan-300 focus:outline-none focus:ring-1 focus:ring-atalaya-cyan"
                            >
                                Descargar .xlsx
                            </a>
                        )}

                        <p className="ml-auto max-w-sm text-xs text-atalaya-text-dim">
                            Planilla para el Portal de Transparencia, con el
                            formato oficial: cantidad de novedades por tipo y
                            por día, con las fe de errata aplicadas.
                        </p>
                    </div>

                    {error && (
                        <div className="border border-red-500/50 bg-red-500/10 p-4 text-sm text-red-300">{error}</div>
                    )}

                    {resumen && enCurso && (
                        <div className="border border-amber-500/50 bg-amber-500/10 p-4 text-sm text-amber-200">
                            El mes está en curso: la planilla incluye lo registrado hasta ahora.
                        </div>
                    )}

                    {resumen && (
                        <>
                            <div className="flex items-baseline justify-between">
                                <p className="font-mono text-sm text-white">{resumen.titulo}</p>
                                <p className="font-mono text-sm text-atalaya-text-dim">
                                    Total de intervenciones: <span className="text-white">{resumen.total}</span>
                                </p>
                            </div>

                            {resumen.bloques.map((b) => (
                                <Tabla key={b.titulo}>
                                    <Cabecera>
                                        <Encabezado>{b.titulo}</Encabezado>
                                        <Encabezado alinear="derecha">Total</Encabezado>
                                    </Cabecera>
                                    <Cuerpo>
                                        {b.filas.map((f, i) => (
                                            <Fila key={`${b.titulo}-${i}`}>
                                                <Celda>{f.concepto}</Celda>
                                                <Celda alinear="derecha" mono tono={f.total ? 'principal' : 'tenue'}>
                                                    {f.total}
                                                </Celda>
                                            </Fila>
                                        ))}
                                        <Fila>
                                            <Celda tono="principal">Subtotal</Celda>
                                            <Celda alinear="derecha" mono tono="principal">
                                                {b.subtotal}
                                            </Celda>
                                        </Fila>
                                    </Cuerpo>
                                </Tabla>
                            ))}
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
