import InputLabel from '@/Components/InputLabel';
import { Cabecera, Celda, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Paginacion, Tabla } from '@/Components/Tabla';
import SecondaryButton from '@/Components/SecondaryButton';
import FechaInput from '@/Components/FechaInput';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import type { Opciones, Paginador } from '@/types';
import { useEffect, useRef, useState } from 'react';

/** Fila del log (AuditoriaConsultaService::fila). */
interface RegistroAuditoria {
    id: number;
    /** dd/mm/aaaa hh:mm:ss */
    fecha_hora: string | null;
    usuario: string | null;
    email: string | null;
    rol: string | null;
    /** Etiqueta legible (AuditoriaAcceso::ACCIONES). */
    accion: string;
    /** Clave del evento: elige el color de la insignia. */
    clave_accion: string;
    ip: string | null;
    descripcion: string | null;
}

interface Filtros {
    usuario_id: string;
    accion: string;
    desde: string;
    hasta: string;
    objeto: string;
    orden: string;
    direccion: '' | 'asc' | 'desc';
}

interface Props {
    registros: Paginador<RegistroAuditoria>;
    filtros: Partial<Filtros>;
    usuarios: { id: number; name: string }[];
    /** clave => etiqueta (AuditoriaAcceso::ACCIONES). */
    acciones: Opciones;
}

// Color por TIPO de acción (clave del backend), igual en todas las filas:
// verde = acceso/alta, azul = modificación, rojo = baja/alerta,
// ámbar = corrección, cian = consulta/exportación.
const VERDE = 'border-emerald-500/50 bg-emerald-500/10 text-emerald-400';
const AZUL = 'border-atalaya-sky bg-atalaya-sky/10 text-atalaya-sky';
const ROJO = 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson';
const AMBAR = 'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber';
const CIAN = 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan';

const accionBadge: Record<string, string | undefined> = {
    login: VERDE,
    logout: undefined, // gris por defecto
    login_fallido: AMBAR,
    bloqueo: ROJO,
    evento_registrado: CIAN,
    usuario_creado: VERDE,
    usuario_actualizado: AZUL,
    usuario_eliminado: ROJO,
    permiso_creado: VERDE,
    permisos_actualizados: AZUL,
    turno_abierto: VERDE,
    turno_cerrado: undefined,
    notificacion_fallida: ROJO,
    notificacion_reenviada: AMBAR,
    reporte_pdf: CIAN,
    exportacion_datos: CIAN,
    errata_registrada: AMBAR,
    punto_creado: VERDE,
    punto_actualizado: AZUL,
    punto_eliminado: ROJO,
    puntos_importados: VERDE,
    auditoria_consultada: undefined,
    token_api_emitido: VERDE,
    token_api_revocado: ROJO,
};

const vacios: Filtros = { usuario_id: '', accion: '', desde: '', hasta: '', objeto: '', orden: '', direccion: '' };

const conValor = (o: Filtros): Partial<Filtros> => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v != null));

export default function Auditoria({ registros, filtros, usuarios, acciones }: Props) {
    const [form, setForm] = useState<Filtros>({ ...vacios, ...filtros });
    const primeraVez = useRef(true);

    useEffect(() => {
        if (primeraVez.current) {
            primeraVez.current = false;
            return undefined;
        }

        const espera = setTimeout(
            () =>
                router.get(route('usuarios.auditoria'), conValor(form), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            350,
        );

        return () => clearTimeout(espera);
    }, [form]);

    const cambiar = (campo: Exclude<keyof Filtros, 'direccion'>) => (e: { target: { value: string } }) => setForm((f) => ({ ...f, [campo]: e.target.value }));
    const ordenActual = form.orden || 'fecha';
    const direccionActual = form.direccion || 'desc';
    const ordenarPor = (orden: string) =>
        setForm((f) => ({
            ...f,
            orden,
            direccion: ordenActual === orden && direccionActual === 'desc' ? 'asc' : 'desc',
        }));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Logs de auditoría
                </h2>
            }
        >
            <Head title="Logs de auditoría" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('usuarios.index')}
                        className="text-sm font-medium text-atalaya-cyan hover:text-white"
                    >
                        ← Volver a usuarios
                    </Link>

                    <p className="text-sm text-atalaya-text-muted">
                        Movimientos de los usuarios en el sistema: quién, con qué
                        rol, desde qué IP y qué acción realizó. El log no se
                        puede modificar ni borrar, y cada consulta queda
                        registrada.
                    </p>

                    <div className="grid grid-cols-1 gap-4 border border-atalaya-border bg-atalaya-surface p-4 md:grid-cols-3 lg:grid-cols-6">
                        <div>
                            <InputLabel htmlFor="usuario_id" value="Usuario" />
                            <Select id="usuario_id" className="mt-1" value={form.usuario_id} onChange={cambiar('usuario_id')}>
                                <option value="">Todos</option>
                                {usuarios.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <InputLabel htmlFor="accion" value="Acción" />
                            <Select id="accion" className="mt-1" value={form.accion} onChange={cambiar('accion')}>
                                <option value="">Todas</option>
                                {Object.entries(acciones).map(([clave, etiqueta]) => (
                                    <option key={clave} value={clave}>
                                        {etiqueta}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <InputLabel htmlFor="desde" value="Desde" />
                            <FechaInput id="desde" className="mt-1 block w-full" value={form.desde} onChange={cambiar('desde')} />
                        </div>
                        <div>
                            <InputLabel htmlFor="hasta" value="Hasta" />
                            <FechaInput id="hasta" className="mt-1 block w-full" value={form.hasta} onChange={cambiar('hasta')} />
                        </div>
                        <div className="md:col-span-2 lg:col-span-2">
                            <InputLabel htmlFor="objeto" value="Objeto afectado (ID de novedad, código de PM…)" />
                            <TextInput
                                id="objeto"
                                type="search"
                                placeholder="Ej.: 123 o PM-M-001"
                                className="mt-1 block w-full"
                                value={form.objeto}
                                onChange={cambiar('objeto')}
                            />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 md:col-span-3 lg:col-span-6">
                            <p className="text-xs text-atalaya-text-dim">
                                {registros.total} registro(s) con estos filtros.
                            </p>
                            <div className="flex gap-3">
                                <SecondaryButton type="button" onClick={() => setForm(vacios)}>
                                    Limpiar filtros
                                </SecondaryButton>
                                <a
                                    href={route('usuarios.auditoria.exportar', conValor(form))}
                                    className="inline-flex items-center border border-atalaya-cyan px-4 py-2 font-mono text-xs font-bold uppercase tracking-widest text-atalaya-cyan hover:bg-atalaya-cyan/10"
                                >
                                    Exportar a Excel
                                </a>
                            </div>
                        </div>
                    </div>

                    <Tabla>
                        <Cabecera>
                            <Encabezado orden="fecha" ordenActual={ordenActual} direccion={direccionActual} onOrdenar={ordenarPor}>
                                Fecha y hora
                            </Encabezado>
                            <Encabezado orden="usuario" ordenActual={ordenActual} direccion={direccionActual} onOrdenar={ordenarPor}>
                                Usuario
                            </Encabezado>
                            <Encabezado>Rol</Encabezado>
                            <Encabezado orden="accion" ordenActual={ordenActual} direccion={direccionActual} onOrdenar={ordenarPor}>
                                Acción
                            </Encabezado>
                            <Encabezado>IP</Encabezado>
                            <Encabezado>Detalle</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {registros.data.length === 0 && (
                                <FilaVacia columnas={6}>No hay registros de auditoría con esos filtros.</FilaVacia>
                            )}
                            {registros.data.map((r) => (
                                <Fila key={r.id}>
                                    <Celda tono="tenue" mono nowrap>{r.fecha_hora}</Celda>
                                    <Celda nowrap>
                                        <div className="font-medium text-white">{r.usuario}</div>
                                        {r.email && r.email !== r.usuario && (
                                            <div className="text-xs text-atalaya-text-dim">{r.email}</div>
                                        )}
                                    </Celda>
                                    <Celda tono="tenue" nowrap>{r.rol || '—'}</Celda>
                                    <Celda nowrap>
                                        <Insignia color={accionBadge[r.clave_accion]}>{r.accion}</Insignia>
                                    </Celda>
                                    <Celda tono="tenue" mono nowrap>{r.ip || '—'}</Celda>
                                    <Celda>{r.descripcion || '—'}</Celda>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>

                    <Paginacion paginador={registros} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
