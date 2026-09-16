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
                `inline-flex items-center justify-center border border-atalaya-border bg-atalaya-canvas px-4 py-2 font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-muted transition duration-150 ease-in-out hover:border-atalaya-cyan hover:text-white focus:outline-none focus:ring-1 focus:ring-atalaya-cyan disabled:opacity-25 ${
                    disabled && 'cursor-not-allowed opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
