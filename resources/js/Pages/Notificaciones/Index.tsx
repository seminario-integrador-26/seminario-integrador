import DangerButton from '@/Components/DangerButton';
import FlashMessages from '@/Components/FlashMessages';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Accion, Cabecera, Celda, CeldaAcciones, Cuerpo, Encabezado, Fila, FilaVacia, Insignia, Tabla } from '@/Components/Tabla';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import type { Opciones } from '@/types';
import { useMemo, useState } from 'react';

/** Tipo de evento con el código de su GrupoEvento (GrupoInteresadoController::tipos). */
interface TipoDestino {
    id: number;
    nombre: string;
    categoria: string;
}

interface GrupoFila {
    id: number;
    nombre: string;
    identificador_whatsapp: string | null;
    telegram_chat_id: string | null;
    contactos_count: number;
    tipos_evento_count: number;
    notificaciones_count: number;
}

/** Fila de grupo_interesado_tipo_evento. */
interface Correspondencia {
    tipo_evento_id: number;
    grupo_interesado_id: number;
}

const clave = (tipoId: number, grupoId: number) => `${tipoId}:${grupoId}`;

const aConjunto = (pares: Correspondencia[]) => new Set(pares.map((p) => clave(p.tipo_evento_id, p.grupo_interesado_id)));

/**
 * US-028: grupos destinatarios (Telegram / WhatsApp) y matriz de correspondencias tipo de evento → grupo.
 */
export default function Index({
    grupos,
    tipos,
    correspondencias,
    categorias,
}: {
    grupos: GrupoFila[];
    tipos: TipoDestino[];
    correspondencias: Correspondencia[];
    categorias: Opciones;
}) {
    const [marcadas, setMarcadas] = useState(() => aConjunto(correspondencias));
    const [guardando, setGuardando] = useState(false);
    const [porEliminar, setPorEliminar] = useState<GrupoFila | null>(null);

    const originales = useMemo(() => aConjunto(correspondencias), [correspondencias]);
    const cambios = useMemo(() => {
        let n = 0;
        marcadas.forEach((k) => !originales.has(k) && n++);
        originales.forEach((k) => !marcadas.has(k) && n++);
        return n;
    }, [marcadas, originales]);

    const porCategoria = useMemo(
        () =>
            Object.entries(categorias)
                .map(([cat, etiqueta]) => ({ cat, etiqueta, tipos: tipos.filter((t) => t.categoria === cat) }))
                .filter((g) => g.tipos.length > 0),
        [tipos, categorias],
    );

    const alternar = (tipoId: number, grupoId: number) =>
        setMarcadas((actual) => {
            const nuevo = new Set(actual);
            const k = clave(tipoId, grupoId);
            nuevo.has(k) ? nuevo.delete(k) : nuevo.add(k);
            return nuevo;
        });

    const guardar = () => {
        setGuardando(true);
        router.put(
            route('grupos.correspondencias'),
            {
                correspondencias: [...marcadas].map((k) => {
                    const [tipo, grupo] = k.split(':').map(Number);
                    return { tipo_evento_id: tipo, grupo_interesado_id: grupo };
                }),
            },
            {
                preserveScroll: true,
                onSuccess: (page) => setMarcadas(aConjunto(page.props.correspondencias as Correspondencia[])),
                onFinish: () => setGuardando(false),
            },
        );
    };

    const sinDestino = (tipoId: number) => !grupos.some((g) => marcadas.has(clave(tipoId, g.id)));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Notificaciones
                </h2>
            }
        >
            <Head title="Notificaciones" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    <Tabla
                        titulo="Grupos destinatarios"
                        acciones={<PrimaryButton onClick={() => router.visit(route('grupos.create'))}>Nuevo grupo</PrimaryButton>}
                    >
                        <Cabecera>
                            <Encabezado>Grupo</Encabezado>
                            <Encabezado>Destino</Encabezado>
                            <Encabezado alinear="derecha">Contactos</Encabezado>
                            <Encabezado alinear="derecha">Tipos de novedad</Encabezado>
                            <Encabezado alinear="derecha">Notificaciones</Encabezado>
                            <Encabezado alinear="derecha">Acciones</Encabezado>
                        </Cabecera>
                        <Cuerpo>
                            {grupos.length === 0 && <FilaVacia columnas={6}>Todavía no hay grupos.</FilaVacia>}
                            {grupos.map((g) => (
                                <Fila key={g.id}>
                                    <Celda tono="principal">{g.nombre}</Celda>
                                    <Celda>
                                        {!g.telegram_chat_id && !g.identificador_whatsapp && g.contactos_count === 0 ? (
                                            <Insignia color="border-atalaya-amber/60 text-atalaya-amber">Sin destino</Insignia>
                                        ) : (
                                            <div className="space-y-0.5">
                                                {g.telegram_chat_id && (
                                                    <div className="font-mono text-xs">Telegram {g.telegram_chat_id}</div>
                                                )}
                                                {g.identificador_whatsapp ? (
                                                    <div className="font-mono text-xs">WhatsApp {g.identificador_whatsapp}</div>
                                                ) : g.contactos_count > 0 ? (
                                                    <div className="text-xs">WhatsApp: contactos individuales</div>
                                                ) : null}
                                            </div>
                                        )}
                                    </Celda>
                                    <Celda alinear="derecha" mono>{g.contactos_count}</Celda>
                                    <Celda alinear="derecha" mono>{g.tipos_evento_count}</Celda>
                                    <Celda alinear="derecha" mono>{g.notificaciones_count}</Celda>
                                    <CeldaAcciones>
                                        <Accion icono="editar" etiqueta={`Editar ${g.nombre}`} href={route('grupos.edit', g.id)} />
                                        <Accion
                                            icono="eliminar"
                                            etiqueta={g.notificaciones_count === 0 ? `Eliminar ${g.nombre}` : 'Ya recibió notificaciones: no se puede eliminar'}
                                            variante="peligro"
                                            disabled={g.notificaciones_count > 0}
                                            onClick={() => setPorEliminar(g)}
                                        />
                                    </CeldaAcciones>
                                </Fila>
                            ))}
                        </Cuerpo>
                    </Tabla>

                    <Tabla
                        titulo="Correspondencias tipo de novedad → grupo"
                        acciones={
                            <div className="flex items-center gap-3">
                                {cambios > 0 && (
                                    <span className="font-mono text-xs text-atalaya-amber">
                                        {cambios} cambio{cambios === 1 ? '' : 's'} sin guardar
                                    </span>
                                )}
                                <SecondaryButton disabled={cambios === 0 || guardando} onClick={() => setMarcadas(originales)}>
                                    Descartar
                                </SecondaryButton>
                                <PrimaryButton disabled={cambios === 0 || guardando} onClick={guardar}>
                                    {guardando ? 'Guardando…' : 'Guardar'}
                                </PrimaryButton>
                            </div>
                        }
                    >
                        <Cabecera>
                            <Encabezado>Tipo de novedad</Encabezado>
                            {grupos.map((g) => (
                                <Encabezado key={g.id} alinear="centro">{g.nombre}</Encabezado>
                            ))}
                        </Cabecera>
                        <Cuerpo>
                            {grupos.length === 0 && (
                                <FilaVacia columnas={1}>Creá un grupo para asignarle tipos de novedad.</FilaVacia>
                            )}
                            {grupos.length > 0 &&
                                porCategoria.map(({ cat, etiqueta, tipos: delGrupo }) => [
                                    <tr key={cat} className="bg-atalaya-canvas/60">
                                        <td
                                            colSpan={grupos.length + 1}
                                            className="px-4 py-2 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim"
                                        >
                                            {etiqueta}
                                        </td>
                                    </tr>,
                                    ...delGrupo.map((t) => (
                                        <Fila key={t.id}>
                                            <Celda tono="principal">
                                                <span className="flex flex-wrap items-center gap-2">
                                                    {t.nombre}
                                                    {sinDestino(t.id) && (
                                                        <Insignia title="Al registrar este tipo no se notifica a nadie">No se notifica</Insignia>
                                                    )}
                                                </span>
                                            </Celda>
                                            {grupos.map((g) => (
                                                <Celda key={g.id} alinear="centro">
                                                    <input
                                                        type="checkbox"
                                                        checked={marcadas.has(clave(t.id, g.id))}
                                                        onChange={() => alternar(t.id, g.id)}
                                                        aria-label={`${t.nombre} → ${g.nombre}`}
                                                        className="h-4 w-4 rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan"
                                                    />
                                                </Celda>
                                            ))}
                                        </Fila>
                                    )),
                                ])}
                        </Cuerpo>
                    </Tabla>
                    <p className="text-xs text-atalaya-text-dim">
                        Los cambios rigen desde la próxima novedad registrada; las
                        notificaciones ya enviadas no se modifican. Cada cambio
                        queda en el log de auditoría.
                    </p>
                </div>
            </div>

            <Modal show={porEliminar !== null} onClose={() => setPorEliminar(null)} maxWidth="md">
                <div className="space-y-4 p-6">
                    <h3 className="font-mono text-sm font-bold uppercase text-white">
                        ¿Eliminar el grupo {porEliminar?.nombre}?
                    </h3>
                    <p className="text-sm text-atalaya-text-muted">
                        Se borran también sus contactos y sus correspondencias.
                        La acción queda registrada en la auditoría.
                    </p>
                    <div className="flex justify-end gap-3">
                        <SecondaryButton onClick={() => setPorEliminar(null)}>Cancelar</SecondaryButton>
                        <DangerButton
                            onClick={() =>
                                porEliminar &&
                                router.delete(route('grupos.destroy', porEliminar.id), {
                                    preserveScroll: true,
                                    onSuccess: (page) => setMarcadas(aConjunto(page.props.correspondencias as Correspondencia[])),
                                    onFinish: () => setPorEliminar(null),
                                })
                            }
                        >
                            Eliminar
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
