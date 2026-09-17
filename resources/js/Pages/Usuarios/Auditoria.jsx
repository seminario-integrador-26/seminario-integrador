import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const accionBadge = {
    'Inicio de sesión': 'border-green-500 bg-green-500/10 text-green-400',
    'Cierre de sesión': 'bg-atalaya-canvas text-white',
    'Intento fallido': 'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    'Cuenta bloqueada':
        'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    'Evento registrado': 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
    'Usuario creado': 'border-green-500 bg-green-500/10 text-green-400',
    'Usuario actualizado': 'border-atalaya-sky bg-atalaya-sky/10 text-atalaya-sky',
    'Usuario eliminado':
        'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
};

export default function Auditoria({ registros }) {
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
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('usuarios.index')}
                        className="text-sm font-medium text-atalaya-cyan hover:text-white"
                    >
                        ← Volver a usuarios
                    </Link>

                    <p className="text-sm text-atalaya-text-muted">
                        Movimientos de los usuarios en el sistema: quién, con qué
                        rol, desde qué IP y qué acción realizó.
                    </p>

                    <div className="overflow-x-auto bg-atalaya-surface border border-atalaya-border">
                        <table className="min-w-full divide-y divide-atalaya-border">
                            <thead className="bg-atalaya-canvas">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Fecha y hora
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Usuario
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Rol
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Acción
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        IP
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-atalaya-text-dim">
                                        Detalle
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-atalaya-border bg-atalaya-surface">
                                {registros.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-4 text-center text-sm text-atalaya-text-dim"
                                        >
                                            No hay registros de auditoría.
                                        </td>
                                    </tr>
                                )}
                                {registros.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {r.fecha_hora}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <div className="font-medium text-white">
                                                {r.usuario}
                                            </div>
                                            {r.email && r.email !== r.usuario && (
                                                <div className="text-xs text-atalaya-text-dim">
                                                    {r.email}
                                                </div>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {r.rol || '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm">
                                            <span
                                                className={
                                                    'inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4 ' +
                                                    (accionBadge[r.accion] ||
                                                        'border-atalaya-border bg-atalaya-canvas text-atalaya-text-muted')
                                                }
                                            >
                                                {r.accion}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-atalaya-text-dim">
                                            {r.ip || '—'}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-atalaya-text-muted">
                                            {r.descripcion || '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {registros.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {registros.links.map((link, i) =>
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
