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
            className={
                'inline-flex items-center border-b-2 px-3 pt-1 text-xs font-semibold uppercase tracking-wider transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-atalaya-cyan text-white bg-atalaya-elevated/40'
                    : 'border-transparent text-atalaya-text-muted hover:border-atalaya-border hover:text-white hover:bg-atalaya-surface/40') +
                ' ' +
                className
            }
        >
            {children}
        </Link>
    );
}
