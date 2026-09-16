import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Color del badge según el rol principal del usuario. */
const estiloDeRol = (rol) => {
    switch (rol) {
        case 'Supervisor':
            return 'border-atalaya-orange text-atalaya-orange bg-atalaya-orange/10';
        case 'Administrador de sistema':
            return 'border-atalaya-cyan text-atalaya-cyan bg-atalaya-cyan/10';
        default:
            return 'border-atalaya-text-muted text-atalaya-text-muted bg-atalaya-border/20';
    }
};

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const user = page.props.auth.user;
    const roles = page.props.auth.roles ?? [];
    const permisos = page.props.auth.permissions ?? [];

    const esAdminSistema = roles.includes('Administrador de sistema');
    const esSupervisor = roles.includes('Supervisor');
    const puedeConsultarEventos = permisos.includes('eventos.consultar');
    const rolPrincipal = roles[0] ?? 'Sin rol';

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
                        <div className="flex items-center gap-6">
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

                            <div className="hidden items-center space-x-1 md:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Panel
                                </NavLink>
                                {puedeConsultarEventos && (
                                    <NavLink
                                        href={route('eventos.index')}
                                        active={route().current('eventos.index')}
                                    >
                                        Eventos
                                    </NavLink>
                                )}
                                {esSupervisor && (
                                    <NavLink
                                        href={route('eventos.create')}
                                        active={route().current('eventos.create')}
                                    >
                                        Registrar
                                    </NavLink>
                                )}
                                {esAdminSistema && (
                                    <NavLink
                                        href={route('usuarios.index')}
                                        active={route().current('usuarios.*')}
                                    >
                                        Usuarios
                                    </NavLink>
                                )}
                            </div>
                        </div>

                        {/* Telemetría de sala */}
                        <div className="hidden items-center gap-4 font-mono text-xs xl:flex">
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
                                        {esAdminSistema && (
                                            <Dropdown.Link
                                                href={route('usuarios.auditoria')}
                                            >
                                                Bitácora
                                            </Dropdown.Link>
                                        )}
                                        {esAdminSistema && (
                                            <Dropdown.Link href={route('roles.index')}>
                                                Roles
                                            </Dropdown.Link>
                                        )}
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
                    <div className="space-y-1 py-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Panel
                        </ResponsiveNavLink>
                        {puedeConsultarEventos && (
                            <ResponsiveNavLink
                                href={route('eventos.index')}
                                active={route().current('eventos.index')}
                            >
                                Eventos
                            </ResponsiveNavLink>
                        )}
                        {esSupervisor && (
                            <ResponsiveNavLink
                                href={route('eventos.create')}
                                active={route().current('eventos.create')}
                            >
                                Registrar evento
                            </ResponsiveNavLink>
                        )}
                        {esAdminSistema && (
                            <ResponsiveNavLink
                                href={route('usuarios.index')}
                                active={route().current('usuarios.*')}
                            >
                                Usuarios
                            </ResponsiveNavLink>
                        )}
                    </div>

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
                            {esAdminSistema && (
                                <ResponsiveNavLink
                                    href={route('usuarios.auditoria')}
                                >
                                    Bitácora
                                </ResponsiveNavLink>
                            )}
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

            <main className="w-full flex-1">{children}</main>
        </div>
    );
}
