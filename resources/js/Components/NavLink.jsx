import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            aria-current={active ? 'page' : undefined}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors duration-150 focus-visible:text-ink focus-visible:outline-none ' +
                (active
                    ? 'border-brand text-ink'
                    : 'border-transparent text-ink-muted hover:border-line-strong hover:text-ink') +
                ' ' +
                className
            }
        >
            {children}
        </Link>
    );
}
