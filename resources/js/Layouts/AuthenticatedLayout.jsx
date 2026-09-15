import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import SelectorTema from '@/Components/SelectorTema';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const user = page.props.auth.user;
    const roles = page.props.auth.roles ?? [];
    const esAdministrativo = roles.includes('Administrativo');
    const esSupervisor = roles.includes('Supervisor');
    const puedeConsultarEventos = (page.props.auth.permissions ?? []).includes(
        'eventos.consultar',
    );

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const enlaces = [
        { texto: 'Panel', ruta: 'dashboard', activo: 'dashboard', visible: true },
        {
            texto: 'Eventos',
            ruta: 'eventos.index',
            activo: 'eventos.index',
            visible: puedeConsultarEventos,
        },
        {
            texto: 'Registrar evento',
            ruta: 'eventos.create',
            activo: 'eventos.create',
            visible: esSupervisor,
        },
        {
            texto: 'Usuarios',
            ruta: 'usuarios.index',
            activo: 'usuarios.*',
            visible: esAdministrativo,
        },
    ].filter((e) => e.visible);

    return (
        <div className="min-h-[100dvh] bg-canvas">
            <a
                href="#contenido"
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-surface focus:px-3 focus:py-2 focus:text-sm focus:text-ink focus:shadow-panel"
            >
                Saltar al contenido
            </a>

            <nav className="sticky top-0 z-40 border-b border-line bg-surface/90 backdrop-blur">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <Link
                                href="/"
                                className="flex shrink-0 items-center gap-2.5 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                            >
                                <ApplicationLogo className="h-8 w-8" />
                                <span className="hidden whitespace-nowrap text-sm font-semibold text-ink lg:block">
                                    Centro de Monitoreo
                                </span>
                            </Link>

                            <div className="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                                {enlaces.map((e) => (
                                    <NavLink
                                        key={e.ruta}
                                        href={route(e.ruta)}
                                        active={route().current(e.activo)}
                                    >
                                        {e.texto}
                                    </NavLink>
                                ))}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center sm:gap-3">
                            <SelectorTema />

                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-start transition-colors duration-150 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                                    >
                                        <span className="leading-tight">
                                            <span className="block text-sm font-medium text-ink">
                                                {user.name}
                                            </span>
                                            {roles[0] && (
                                                <span className="block text-xs text-ink-subtle">
                                                    {roles[0]}
                                                </span>
                                            )}
                                        </span>

                                        <svg
                                            className="h-4 w-4 text-ink-subtle"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content>
                                    <Dropdown.Link
                                        href={route('profile.edit')}
                                    >
                                        Perfil
                                    </Dropdown.Link>
                                    <Dropdown.Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                    >
                                        Cerrar sesión
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                type="button"
                                aria-label="Abrir menú"
                                aria-expanded={showingNavigationDropdown}
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink focus:bg-surface-2 focus:text-ink focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' border-t border-line sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        {enlaces.map((e) => (
                            <ResponsiveNavLink
                                key={e.ruta}
                                href={route(e.ruta)}
                                active={route().current(e.activo)}
                            >
                                {e.texto}
                            </ResponsiveNavLink>
                        ))}
                    </div>

                    <div className="border-t border-line pb-2 pt-4">
                        <div className="flex items-center justify-between gap-4 px-4">
                            <div className="min-w-0">
                                <div className="truncate text-base font-medium text-ink">
                                    {user.name}
                                </div>
                                <div className="truncate text-sm text-ink-muted">
                                    {user.email}
                                </div>
                            </div>
                            <SelectorTema />
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Perfil
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
                <header className="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
                    {header}
                </header>
            )}

            <main id="contenido" tabIndex={-1} className="focus:outline-none">
                {children}
            </main>
        </div>
    );
}
