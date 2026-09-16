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
            className={`flex w-full items-start border-l-2 py-2 pe-4 ps-3 text-xs font-semibold uppercase tracking-wider ${
                active
                    ? 'border-atalaya-cyan bg-atalaya-elevated text-white'
                    : 'border-transparent text-atalaya-text-muted hover:border-atalaya-border hover:bg-atalaya-surface hover:text-white'
            } transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
