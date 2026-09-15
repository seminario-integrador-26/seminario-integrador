import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            aria-current={active ? 'page' : undefined}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 text-base font-medium transition-colors duration-150 focus:outline-none ${
                active
                    ? 'border-brand bg-surface-2 text-ink'
                    : 'border-transparent text-ink-muted hover:bg-surface-2 hover:text-ink focus:bg-surface-2 focus:text-ink'
            } ${className}`}
        >
            {children}
        </Link>
    );
}
