import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const categoriaBadge = {
    Prevención: 'bg-accent-soft text-accent',
    'Convivencia Urbana': 'bg-warning-soft text-warning',
    'Seguridad Pública': 'bg-danger-soft text-danger',
    Informativo: 'bg-surface-2 text-ink-muted',
};

const filtrosVacios = {
    tipo_evento_id: '',
    categoria: '',
    punto_monitoreo_id: '',
    desde: '',
    hasta: '',
};

export default function Index({ eventos, filtros, tipos, categorias, puntos }) {
    const { errors } = usePage().props;
    const [form, setForm] = useState({ ...filtrosVacios, ...filtros });
    const [cargando, setCargando] = useState(false);

    const hayFiltros = Object.values(filtros ?? {}).some(
        (v) => v !== '' && v !== null && v !== undefined,
    );

    const cambiar = (campo) => (e) =>
        setForm((actual) => ({ ...actual, [campo]: e.target.value }));

    const buscar = (valores) => {
        const conValor = Object.fromEntries(
            Object.entries(valores).filter(([, v]) => v !== ''),
        );

        router.get(route('eventos.index'), conValor, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    };

    const limpiar = () => {
        setForm(filtrosVacios);
        buscar(filtrosVacios);
    };

    return (
        <AuthenticatedLayout header={<h2 className="titulo-pagina">Eventos</h2>}>
            <Head title="Eventos" />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        buscar(form);
                    }}
                    className="panel grid grid-cols-1 gap-4 p-4 sm:p-5 md:grid-cols-3 lg:grid-cols-6"
                >
                    <div className="lg:col-span-2">
                        <InputLabel htmlFor="tipo_evento_id" value="Tipo" />
                        <select
                            id="tipo_evento_id"
                            className="campo mt-1"
                            value={form.tipo_evento_id}
                            onChange={cambiar('tipo_evento_id')}
                        >
                            <option value="">Todos</option>
                            {tipos.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.nombre}
                                </option>
                            ))}
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.tipo_evento_id}
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="categoria" value="Categoría" />
                        <select
                            id="categoria"
                            className="campo mt-1"
                            value={form.categoria}
                            onChange={cambiar('categoria')}
                        >
                            <option value="">Todas</option>
                            {Object.entries(categorias).map(
                                ([clave, etiqueta]) => (
                                    <option key={clave} value={clave}>
                                        {etiqueta}
                                    </option>
                                ),
                            )}
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.categoria}
                        />
                    </div>

                    <div className="lg:col-span-3">
                        <InputLabel
                            htmlFor="punto_monitoreo_id"
                            value="Punto de monitoreo"
                        />
                        <select
                            id="punto_monitoreo_id"
                            className="campo mt-1"
                            value={form.punto_monitoreo_id}
                            onChange={cambiar('punto_monitoreo_id')}
                        >
                            <option value="">Todos</option>
                            {puntos.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.codigo} · {p.nombre}
                                </option>
                            ))}
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.punto_monitoreo_id}
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="desde" value="Desde" />
                        <TextInput
                            id="desde"
                            type="date"
                            className="mt-1 font-mono"
                            value={form.desde}
                            onChange={cambiar('desde')}
                        />
                        <InputError className="mt-2" message={errors.desde} />
                    </div>

                    <div>
                        <InputLabel htmlFor="hasta" value="Hasta" />
                        <TextInput
                            id="hasta"
                            type="date"
                            className="mt-1 font-mono"
                            value={form.hasta}
                            onChange={cambiar('hasta')}
                        />
                        <InputError className="mt-2" message={errors.hasta} />
                    </div>

                    <div className="flex items-end gap-3 md:col-span-3 lg:col-span-4 lg:justify-end">
                        <SecondaryButton type="button" onClick={limpiar}>
                            Limpiar
                        </SecondaryButton>
                        <PrimaryButton disabled={cargando}>Buscar</PrimaryButton>
                    </div>
                </form>

                <p className="text-sm text-ink-muted" aria-live="polite">
                    {eventos.total === 0
                        ? 'Sin resultados.'
                        : `Mostrando ${eventos.from} a ${eventos.to} de ${eventos.total} eventos.`}
                </p>

                <div className="panel overflow-hidden">
                    <div className="overflow-x-auto">
                        <table
                            aria-busy={cargando}
                            className={
                                'tabla transition-opacity duration-150 ' +
                                (cargando ? 'opacity-60' : '')
                            }
                        >
                            <thead>
                                <tr>
                                    {[
                                        '#',
                                        'Fecha y hora',
                                        'Tipo',
                                        'Ubicación',
                                        'Descripción',
                                        'Registró',
                                    ].map((titulo) => (
                                        <th key={titulo} scope="col">
                                            {titulo}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {eventos.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-12 text-center"
                                        >
                                            <p className="font-medium text-ink">
                                                No hay eventos registrados con
                                                esos filtros.
                                            </p>
                                            {hayFiltros && (
                                                <button
                                                    type="button"
                                                    onClick={limpiar}
                                                    className="enlace mt-2 text-sm"
                                                >
                                                    Limpiar filtros
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                )}
                                {eventos.data.map((e) => (
                                    <tr key={e.id}>
                                        <td className="whitespace-nowrap font-mono text-xs text-ink-subtle tabular-nums">
                                            {e.id}
                                        </td>
                                        <td className="whitespace-nowrap">
                                            <div className="font-mono text-ink tabular-nums">
                                                {e.fecha_hora}
                                            </div>
                                            <div className="mt-0.5 text-xs text-ink-subtle">
                                                Turno #{e.turno_id}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="font-medium text-ink">
                                                {e.tipo}
                                            </div>
                                            <span
                                                className={
                                                    'badge mt-1 ' +
                                                    (categoriaBadge[
                                                        e.categoria
                                                    ] ??
                                                        'bg-surface-2 text-ink-muted')
                                                }
                                            >
                                                {e.categoria}
                                            </span>
                                        </td>
                                        <td className="text-ink-muted">
                                            {e.punto ?? (
                                                <span className="text-ink-subtle">
                                                    Sin ubicación
                                                </span>
                                            )}
                                            {e.timestamp_video && (
                                                <div className="mt-0.5 font-mono text-xs text-ink-subtle tabular-nums">
                                                    Video {e.timestamp_video}
                                                </div>
                                            )}
                                        </td>
                                        <td className="min-w-[16rem] max-w-md text-ink-muted">
                                            {e.descripcion ?? (
                                                <span className="text-ink-subtle">
                                                    Sin descripción
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap text-ink-muted">
                                            {e.autor}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {eventos.last_page > 1 && (
                    <nav
                        aria-label="Paginación"
                        className="flex flex-wrap gap-1"
                    >
                        {eventos.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    preserveState
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className={
                                        'rounded-md border px-3 py-1.5 text-sm transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ' +
                                        (link.active
                                            ? 'border-brand bg-brand text-brand-ink'
                                            : 'border-line bg-surface text-ink-muted hover:bg-surface-2 hover:text-ink')
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="rounded-md border border-line px-3 py-1.5 text-sm text-ink-subtle opacity-60"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
