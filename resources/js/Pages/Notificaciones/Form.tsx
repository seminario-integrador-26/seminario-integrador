import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Accion } from '@/Components/Tabla';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Opciones } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

/** Tipo de evento con el código de su GrupoEvento (GrupoInteresadoController::tipos). */
interface TipoDestino {
    id: number;
    nombre: string;
    categoria: string;
}

interface Contacto {
    nombre: string;
    /** Con código de país, solo dígitos. */
    numero_whatsapp: string;
}

/** Grupo a editar (null = alta). */
interface GrupoEditable {
    id: number;
    nombre: string;
    identificador_whatsapp: string | null;
    telegram_chat_id: string | null;
    contactos: Contacto[];
    tipos_evento: number[];
}

type FormGrupo = {
    nombre: string;
    telegram_chat_id: string;
    identificador_whatsapp: string;
    contactos: Contacto[];
    tipos_evento: number[];
};

function Seccion({ titulo, acciones, children }: { titulo: ReactNode; acciones?: ReactNode; children: ReactNode }) {
    return (
        <section className="space-y-4 border border-atalaya-border bg-atalaya-surface p-6">
            <div className="flex items-center justify-between gap-4">
                <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">{titulo}</h3>
                {acciones}
            </div>
            {children}
        </section>
    );
}

/**
 * US-028: alta / edición de un grupo destinatario (Telegram y/o WhatsApp), sus contactos y los tipos
 * de evento que recibe.
 */
export default function Form({
    grupo,
    tiposPorDefecto,
    tipos,
    categorias,
}: {
    grupo: GrupoEditable | null;
    /** Tipos que un grupo nuevo trae marcados (Apertura, Cierre de turno y Nota). */
    tiposPorDefecto: number[];
    tipos: TipoDestino[];
    categorias: Opciones;
}) {
    const { data, setData, post, put, processing, errors } = useForm<FormGrupo>({
        nombre: grupo?.nombre ?? '',
        telegram_chat_id: grupo?.telegram_chat_id ?? '',
        identificador_whatsapp: grupo?.identificador_whatsapp ?? '',
        contactos: grupo?.contactos ?? [],
        tipos_evento: grupo?.tipos_evento ?? tiposPorDefecto,
    });

    // Errores por contacto (contactos.N.campo): claves que el form no declara.
    const erroresContactos = errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        grupo ? put(route('grupos.update', grupo.id)) : post(route('grupos.store'));
    };

    const cambiarContacto = (i: number, campo: keyof Contacto, valor: string) =>
        setData('contactos', data.contactos.map((c, j) => (j === i ? { ...c, [campo]: valor } : c)));

    const alternarTipo = (id: number) =>
        setData('tipos_evento', data.tipos_evento.includes(id) ? data.tipos_evento.filter((t) => t !== id) : [...data.tipos_evento, id]);

    // Marca todos los ids dados si falta alguno; si ya están todos, los quita.
    const alternarVarios = (ids: number[]) => {
        const todos = ids.every((id) => data.tipos_evento.includes(id));
        setData(
            'tipos_evento',
            todos ? data.tipos_evento.filter((t) => !ids.includes(t)) : [...new Set([...data.tipos_evento, ...ids])],
        );
    };

    const todosLosTipos = tipos.map((t) => t.id);
    const todosMarcados = todosLosTipos.length > 0 && todosLosTipos.every((id) => data.tipos_evento.includes(id));

    const titulo = grupo ? `Editar grupo ${grupo.nombre}` : 'Nuevo grupo';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">{titulo}</h2>
                    <Link href={route('grupos.index')} className="whitespace-nowrap font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline">
                        ← Volver
                    </Link>
                </div>
            }
        >
            <Head title={titulo} />

            <div className="py-12">
                <form onSubmit={submit} className="mx-auto grid max-w-5xl gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                    <div className="space-y-6">
                        <Seccion titulo="Grupo">
                            <div>
                                <InputLabel htmlFor="nombre" value="Nombre (fuerza o área destinataria)" />
                                <TextInput
                                    id="nombre"
                                    className="mt-1 block w-full"
                                    placeholder="Ej.: Bomberos"
                                    value={data.nombre}
                                    onChange={(e) => setData('nombre', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.nombre} />
                            </div>
                            <div>
                                <InputLabel htmlFor="telegram_chat_id" value="Chat del grupo de Telegram" />
                                <TextInput
                                    id="telegram_chat_id"
                                    className="mt-1 block w-full font-mono"
                                    placeholder="-1001234567890"
                                    value={data.telegram_chat_id}
                                    onChange={(e) => setData('telegram_chat_id', e.target.value.trim())}
                                />
                                <InputError className="mt-2" message={errors.telegram_chat_id} />
                                <p className="mt-2 text-xs text-atalaya-text-dim">
                                    Agregá el bot al grupo, escribí un mensaje y obtené
                                    el número con <span className="font-mono">php artisan telegram:chats</span>.
                                </p>
                            </div>
                            <div>
                                <InputLabel htmlFor="identificador_whatsapp" value="Identificador del grupo de WhatsApp" />
                                <TextInput
                                    id="identificador_whatsapp"
                                    className="mt-1 block w-full font-mono"
                                    value={data.identificador_whatsapp}
                                    onChange={(e) => setData('identificador_whatsapp', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.identificador_whatsapp} />
                                <p className="mt-2 text-xs text-atalaya-text-dim">
                                    Si se completa, el mensaje va al grupo; si queda
                                    vacío, se envía a cada contacto por separado.
                                </p>
                            </div>
                        </Seccion>

                        <Seccion titulo="Contactos de WhatsApp">
                            {data.contactos.length === 0 && (
                                <p className="text-xs text-atalaya-text-dim">Sin contactos cargados.</p>
                            )}
                            {data.contactos.map((c, i) => (
                                <div key={i} className="flex items-start gap-2">
                                    <div className="flex-1">
                                        <TextInput
                                            className="block w-full"
                                            placeholder="Nombre"
                                            aria-label={`Nombre del contacto ${i + 1}`}
                                            value={c.nombre}
                                            onChange={(e) => cambiarContacto(i, 'nombre', e.target.value)}
                                        />
                                        <InputError className="mt-1" message={erroresContactos[`contactos.${i}.nombre`]} />
                                    </div>
                                    <div className="flex-1">
                                        <TextInput
                                            className="block w-full font-mono"
                                            inputMode="numeric"
                                            placeholder="5493534000000"
                                            aria-label={`Número del contacto ${i + 1}`}
                                            value={c.numero_whatsapp}
                                            onChange={(e) => cambiarContacto(i, 'numero_whatsapp', e.target.value.replace(/\D/g, ''))}
                                        />
                                        <InputError className="mt-1" message={erroresContactos[`contactos.${i}.numero_whatsapp`]} />
                                    </div>
                                    <div className="pt-1.5">
                                        <Accion
                                            icono="eliminar"
                                            etiqueta={`Quitar contacto ${i + 1}`}
                                            variante="peligro"
                                            onClick={() => setData('contactos', data.contactos.filter((_, j) => j !== i))}
                                        />
                                    </div>
                                </div>
                            ))}
                            <SecondaryButton onClick={() => setData('contactos', [...data.contactos, { nombre: '', numero_whatsapp: '' }])}>
                                Agregar contacto
                            </SecondaryButton>
                            <p className="text-xs text-atalaya-text-dim">
                                Número con código de país, sin "+" ni espacios.
                            </p>
                        </Seccion>
                    </div>

                    <div className="space-y-6">
                        <Seccion
                            titulo={`Tipos de novedad que recibe (${data.tipos_evento.length}/${tipos.length})`}
                            acciones={
                                <button
                                    type="button"
                                    onClick={() => alternarVarios(todosLosTipos)}
                                    className="whitespace-nowrap font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                                >
                                    {todosMarcados ? 'Quitar todos' : 'Seleccionar todos'}
                                </button>
                            }
                        >
                            {Object.entries(categorias).map(([cat, etiqueta]) => {
                                const delGrupo = tipos.filter((t) => t.categoria === cat);
                                const ids = delGrupo.map((t) => t.id);
                                const categoriaCompleta = ids.every((id) => data.tipos_evento.includes(id));

                                return delGrupo.length === 0 ? null : (
                                    <fieldset key={cat}>
                                        <div className="flex items-center justify-between gap-4">
                                            <legend className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-muted">
                                                {etiqueta}
                                            </legend>
                                            <button
                                                type="button"
                                                onClick={() => alternarVarios(ids)}
                                                aria-label={`${categoriaCompleta ? 'Quitar' : 'Seleccionar'} todos los tipos de ${etiqueta}`}
                                                className="font-mono text-[10px] font-bold uppercase text-atalaya-cyan hover:underline"
                                            >
                                                {categoriaCompleta ? 'Ninguno' : 'Todos'}
                                            </button>
                                        </div>
                                        <div className="mt-2 grid gap-1 sm:grid-cols-2">
                                            {delGrupo.map((t) => (
                                                <label key={t.id} className="flex items-center gap-2 text-sm text-atalaya-text-muted">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.tipos_evento.includes(t.id)}
                                                        onChange={() => alternarTipo(t.id)}
                                                        className="h-4 w-4 rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan"
                                                    />
                                                    {t.nombre}
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
                                );
                            })}
                            <InputError message={errors.tipos_evento} />
                        </Seccion>

                        <PrimaryButton disabled={processing}>{grupo ? 'Guardar cambios' : 'Crear grupo'}</PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
