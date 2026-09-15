export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border border-line-strong bg-surface px-4 py-2 text-sm font-medium text-ink shadow-sm transition duration-150 ease-out hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-canvas active:translate-y-px disabled:pointer-events-none disabled:opacity-50 ' +
                className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
