import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const categoriaBadge = {
    Prevención: 'border-atalaya-orange bg-atalaya-orange/10 text-atalaya-orange',
    'Convivencia Urbana':
        'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    'Seguridad Pública':
        'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    Informativo: 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

const filtrosVacios = {
    tipo_evento_id: '',
    categoria: '',
    punto_monitoreo_id: '',
    desde: '',
    hasta: '',
};

const selectClass =
    'mt-1 block w-full rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs '
    + 'text-white focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan';

export default function Index({ eventos, filtros, tipos, categorias, puntos }) {
    const { errors } = usePage().props;
    const [form, setForm] = useState({ ...filtrosVacios, ...filtros });

    const cambiar = (campo) => (e) =>
        setForm((actual) => ({ ...actual, [campo]: e.target.value }));

    const buscar = (valores) => {
        const conValor = Object.fromEntries(
            Object.entries(valores).filter(([, v]) => v !== ''),
        );

        router.get(route('eventos.index'), conValor, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const limpiar = () => {
        setForm(filtrosVacios);
        buscar(filtrosVacios);
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Eventos
                </h2>
            }
        >
            <Head title="Eventos" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            buscar(form);
                        }}
                        className="grid grid-cols-1 gap-4 bg-atalaya-surface p-6 border border-atalaya-border md:grid-cols-3 lg:grid-cols-6"
                    >
                        <div className="lg:col-span-2">
                            <InputLabel htmlFor="tipo_evento_id" value="Tipo" />
                            <select
                                id="tipo_evento_id"
                                className={selectClass}
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
                            <InputError message={errors.tipo_evento_id} />
                        </div>

                        <div>
                            <InputLabel htmlFor="categoria" value="Categoría" />
                            <select
                                id="categoria"
                                className={selectClass}
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
                            <InputError message={errors.categoria} />
                        </div>

                        <div className="lg:col-span-3">
                            <InputLabel
                                htmlFor="punto_monitoreo_id"
                                value="Punto de monitoreo"
                            />
                            <select
                                id="punto_monitoreo_id"
                                className={selectClass}
                                value={form.punto_monitoreo_id}
                                onChange={cambiar('punto_monitoreo_id')}
                            >
                                <option value="">Todos</option>
                                {puntos.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.codigo} — {p.nombre}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.punto_monitoreo_id} />
                        </div>

                        <div>
                            <InputLabel htmlFor="desde" value="Desde" />
                            <TextInput
                                id="desde"
                                type="date"
                                className="mt-1 block w-full"
                                value={form.desde}
                                onChange={cambiar('desde')}
                            />
                            <InputError message={errors.desde} />
                        </div>

                        <div>
                            <InputLabel htmlFor="hasta" value="Hasta" />
                            <TextInput
                                id="hasta"
                                type="date"
                                className="mt-1 block w-full"
                                value={form.hasta}
                                onChange={cambiar('hasta')}
                            />
                            <InputError message={errors.hasta} />
                        </div>

                        <div className="flex items-end gap-3 md:col-span-3 lg:col-span-4 lg:justify-end">
                            <SecondaryButton type="button" onClick={limpiar}>
                                Limpiar
                            </SecondaryButton>
                            <PrimaryButton>Buscar</PrimaryButton>
                        </div>
                    </form>

                    <p className="text-sm text-atalaya-text-muted">
                        {eventos.total === 0
                            ? 'Sin resultados.'
                            : `Mostrando ${eventos.from}–${eventos.to} de ${eventos.total} eventos.`}
                    </p>

                    <div className="overflow-x-auto bg-atalaya-surface border border-atalaya-border">
                        <table className="min-w-full divide-y divide-atalaya-border">
                            <thead className="bg-atalaya-canvas">
                                <tr>
                                    {[
                                        '#',
                                        'Fecha y hora',
                                        'Tipo',
                                        'Ubicación',
                                        'Descripción',
                                        'Registró',
                                    ].map((titulo) => (
                                        <th
                                            key={titulo}
                                            className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim"
                                        >
                                            {titulo}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-atalaya-border bg-atalaya-surface">
                                {eventos.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-4 text-center text-sm text-atalaya-text-dim"
                                        >
                                            No hay eventos registrados con esos
                                            filtros.
                                        </td>
                                    </tr>
                                )}
                                {eventos.data.map((e) => (
                                    <tr key={e.id} className="align-top">
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {e.id}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-white">
                                            {e.fecha_hora}
                                            <div className="text-xs text-atalaya-text-dim">
                                                Turno #{e.turno_id}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm">
                                            <div className="font-medium text-white">
                                                {e.tipo}
                                            </div>
                                            <span
                                                className={
                                                    'mt-1 inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4 ' +
                                                    (categoriaBadge[
                                                        e.categoria
                                                    ] ||
                                                        'border-atalaya-border bg-atalaya-canvas text-atalaya-text-muted')
                                                }
                                            >
                                                {e.categoria}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-atalaya-text-dim">
                                            {e.punto ?? '—'}
                                            {e.timestamp_video && (
                                                <div className="text-xs text-atalaya-text-dim">
                                                    Video {e.timestamp_video}
                                                </div>
                                            )}
                                        </td>
                                        <td className="max-w-md px-6 py-4 text-sm text-atalaya-text-muted">
                                            {e.descripcion ?? '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {e.autor}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {eventos.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {eventos.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={
                                            'rounded-none border px-3 py-1 text-sm ' +
                                            (link.active
                                                ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                                : 'border-atalaya-border bg-atalaya-surface text-atalaya-text-muted hover:bg-atalaya-canvas')
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="rounded-none border border-atalaya-border px-3 py-1 text-sm text-atalaya-text-dim"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
