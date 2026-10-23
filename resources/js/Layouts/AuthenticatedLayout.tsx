import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { NavegacionEscritorio, NavegacionMovil } from '@/Components/MenuNavegacion';
import type { GrupoNavegacion, ItemNavegacion } from '@/Components/MenuNavegacion';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import type { PageProps, User } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode, useEffect, useState } from 'react';

/** Color del badge según el rol principal del usuario. */
const estiloDeRol = (rol: string) => {
    switch (rol) {
        case 'Supervisor':
            return 'border-atalaya-orange text-atalaya-orange bg-atalaya-orange/10';
        case 'Administrador de sistema':
            return 'border-atalaya-cyan text-atalaya-cyan bg-atalaya-cyan/10';
        default:
            return 'border-atalaya-text-muted text-atalaya-text-muted bg-atalaya-border/20';
    }
};

export default function AuthenticatedLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage<PageProps>();
    // Este layout solo se usa detrás del middleware auth: siempre hay usuario.
    const user = page.props.auth.user as User;
    const roles = page.props.auth.roles ?? [];
    const permisos = page.props.auth.permissions ?? [];
    const notificacionesFallidas = page.props.notificacionesFallidas ?? 0;

    const esAdminSistema = roles.includes('Administrador de sistema');
    const esSupervisor = roles.includes('Supervisor');
    const puedeConsultarEventos = permisos.includes('eventos.consultar');
    const puedeVerEstadisticas = permisos.includes('estadisticas.ver');
    const puedeExportar = permisos.includes('reportes.exportar');
    const puedeVerMapa = permisos.includes('dashboards.ver');
    const puedeGestionarPuntos = permisos.includes('puntos.gestionar');
    const puedeConfigurarNotificaciones = permisos.includes('notificaciones.configurar');
    const puedeConfigurarParametros = permisos.includes('parametros.configurar');
    const rolPrincipal = roles[0] ?? 'Sin rol';

    // Módulos visibles según rol/permiso, agrupados para la navegación.
    const actual = (...rutas: string[]) => rutas.some((r) => route().current(r));
    const panel: ItemNavegacion = { etiqueta: 'Panel', ruta: 'dashboard', activo: actual('dashboard') };
    const grupos: GrupoNavegacion[] = [
        {
            titulo: 'Operación',
            items: [
                puedeConsultarEventos && { etiqueta: 'Eventos', ruta: 'eventos.index', activo: actual('eventos.index', 'eventos.show', 'erratas.*') },
                esSupervisor && { etiqueta: 'Registrar evento', ruta: 'eventos.create', activo: actual('eventos.create') },
            ],
        },
        {
            titulo: 'Análisis',
            items: [
                puedeVerMapa && { etiqueta: 'Análisis', ruta: 'analisis.index', activo: actual('analisis.index') },
                puedeVerMapa && { etiqueta: 'Mapa', ruta: 'mapa.index', activo: actual('mapa.index') },
                puedeVerEstadisticas && { etiqueta: 'Estadísticas', ruta: 'estadisticas.index', activo: actual('estadisticas.index') },
                puedeExportar && { etiqueta: 'Reportes', ruta: 'reportes.index', activo: actual('reportes.index') },
            ],
        },
        {
            titulo: 'Administración',
            items: [
                puedeGestionarPuntos && { etiqueta: 'Puntos de monitoreo', ruta: 'puntos.index', activo: actual('puntos.*') },
                puedeConfigurarNotificaciones && { etiqueta: 'Notificaciones', ruta: 'grupos.index', activo: actual('grupos.*') },
                esAdminSistema && { etiqueta: 'Usuarios', ruta: 'usuarios.index', activo: actual('usuarios.index', 'usuarios.create', 'usuarios.edit') },
                esAdminSistema && { etiqueta: 'Roles y permisos', ruta: 'roles.index', activo: actual('roles.*') },
                esAdminSistema && { etiqueta: 'Logs de auditoría', ruta: 'usuarios.auditoria', activo: actual('usuarios.auditoria') },
                esAdminSistema && { etiqueta: 'Tokens de la API', ruta: 'tokens.index', activo: actual('tokens.*') },
                puedeConfigurarParametros && { etiqueta: 'Parámetros', ruta: 'parametros.index', activo: actual('parametros.*') },
            ],
        },
    ]
        .map((g) => ({ ...g, items: g.items.filter((i): i is ItemNavegacion => Boolean(i)) }))
        .filter((g) => g.items.length > 0);

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);
    const [hora, setHora] = useState('');

    // Reloj de sala: el centro de monitoreo opera 24/7 sobre hora local (UTC-3).
    useEffect(() => {
        const actualizar = () =>
            setHora(
                new Date().toLocaleTimeString('es-AR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                }),
            );

        actualizar();
        const intervalo = setInterval(actualizar, 1000);

        return () => clearInterval(intervalo);
    }, []);

    return (
        <div className="flex min-h-screen flex-col bg-atalaya-canvas font-sans text-atalaya-text-primary">
            <nav className="sticky top-0 z-30 border-b border-atalaya-border bg-atalaya-surface">
                <div className="w-full px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        {/* Identidad y navegación */}
                        <div className="flex items-center gap-8">
                            <Link href="/" className="group flex items-center gap-3">
                                <ApplicationLogo className="h-10 w-10 transition-transform duration-200 group-hover:scale-105" />
                                <div className="flex flex-col">
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-base font-black tracking-widest text-white">
                                            ATALAYA
                                        </span>
                                        <span className="border border-atalaya-border bg-atalaya-canvas px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wider text-atalaya-cyan">
                                            SOC v1.0
                                        </span>
                                    </div>
                                    <span className="font-mono text-[10px] uppercase tracking-wider text-atalaya-text-muted">
                                        Centro de Monitoreo Urbano · Villa María
                                    </span>
                                </div>
                            </Link>

                            <div className="hidden h-8 w-px bg-atalaya-border lg:block" />

                            <NavegacionEscritorio panel={panel} grupos={grupos} />
                        </div>

                        {/* Telemetría de sala */}
                        <div className="hidden items-center gap-3 font-mono text-xs 2xl:flex">
                            <div className="flex items-center gap-2 border border-atalaya-border bg-atalaya-canvas px-2.5 py-1">
                                <span className="h-2 w-2 bg-atalaya-cyan"></span>
                                <span className="text-[11px] uppercase tracking-wider text-atalaya-text-muted">
                                    Sistema:{' '}
                                    <span className="font-semibold text-white">
                                        En línea
                                    </span>
                                </span>
                            </div>

                            <div className="border border-atalaya-border bg-atalaya-canvas px-2.5 py-1 font-semibold text-atalaya-cyan">
                                <span className="mr-1.5 text-atalaya-text-dim">
                                    UTC-3
                                </span>
                                <span>{hora || '00:00:00'}</span>
                            </div>
                        </div>

                        {/* Operador y rol */}
                        <div className="hidden sm:flex sm:items-center sm:gap-4">
                            <span
                                className={`border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-widest ${estiloDeRol(
                                    rolPrincipal,
                                )}`}
                            >
                                {rolPrincipal}
                            </span>

                            <div className="relative">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-2 border border-atalaya-border bg-atalaya-canvas px-3 py-1.5 font-mono text-xs text-atalaya-text-primary transition duration-150 ease-in-out hover:border-atalaya-cyan hover:text-white focus:outline-none"
                                        >
                                            <span className="max-w-[140px] truncate">
                                                {user.name}
                                            </span>
                                            <svg
                                                className="h-3.5 w-3.5 text-atalaya-text-muted"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content width="48">
                                        <div className="border-b border-atalaya-border px-4 py-2 font-mono text-[11px] text-atalaya-text-muted">
                                            <div className="truncate font-semibold text-white">
                                                {user.name}
                                            </div>
                                            <div className="truncate text-atalaya-text-dim">
                                                {user.username}
                                            </div>
                                        </div>
                                        <Dropdown.Link href={route('profile.edit')}>
                                            Mi perfil
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="text-atalaya-crimson hover:text-red-400"
                                        >
                                            Cerrar sesión
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        {/* Menú móvil */}
                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (anterior) => !anterior,
                                    )
                                }
                                className="inline-flex items-center justify-center border border-atalaya-border p-2 text-atalaya-text-muted transition duration-150 ease-in-out hover:border-atalaya-cyan hover:text-white focus:outline-none"
                            >
                                <svg
                                    className="h-5 w-5"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="square"
                                        strokeLinejoin="miter"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="square"
                                        strokeLinejoin="miter"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Navegación responsive */}
                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' border-t border-atalaya-border bg-atalaya-surface sm:hidden'
                    }
                >
                    <NavegacionMovil panel={panel} grupos={grupos} />

                    <div className="border-t border-atalaya-border bg-atalaya-canvas px-4 py-3">
                        <div className="font-mono text-xs text-white">
                            {user.name}
                        </div>
                        <div className="font-mono text-[11px] text-atalaya-text-muted">
                            {user.username}
                        </div>
                        <div className="mt-1">
                            <span
                                className={`border px-2 py-0.5 font-mono text-[10px] font-bold uppercase ${estiloDeRol(
                                    rolPrincipal,
                                )}`}
                            >
                                {rolPrincipal}
                            </span>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Mi perfil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Cerrar sesión
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <div className="border-b border-atalaya-border bg-atalaya-surface/50 px-4 py-3 sm:px-6 lg:px-8">
                    {header}
                </div>
            )}

            {/* US-007: alerta de notificaciones que no llegaron tras los reintentos. */}
            {notificacionesFallidas > 0 && (
                <div
                    role="alert"
                    className="flex flex-wrap items-center gap-3 border-b border-atalaya-crimson/50 bg-atalaya-crimson/10 px-4 py-2 text-sm text-red-100 sm:px-6 lg:px-8"
                >
                    <span className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-crimson">
                        [ Notificación ]
                    </span>
                    {notificacionesFallidas === 1
                        ? 'Una notificación no pudo enviarse.'
                        : `${notificacionesFallidas} notificaciones no pudieron enviarse.`}
                    {puedeConsultarEventos && (
                        <Link
                            href={route('eventos.index')}
                            className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                        >
                            Ver y reenviar
                        </Link>
                    )}
                </div>
            )}

            <main className="w-full flex-1">{children}</main>
        </div>
    );
}
