export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md bg-brand px-4 py-2 text-sm font-semibold text-brand-ink shadow-sm transition duration-150 ease-out hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-canvas active:translate-y-px disabled:pointer-events-none disabled:opacity-50 ' +
                className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
