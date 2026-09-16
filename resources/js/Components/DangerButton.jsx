export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center border border-atalaya-crimson bg-atalaya-crimson/10 px-4 py-2 font-mono text-xs font-bold uppercase tracking-widest text-atalaya-crimson transition duration-150 ease-in-out hover:bg-atalaya-crimson hover:text-black focus:outline-none focus:ring-1 focus:ring-atalaya-crimson ${
                    disabled && 'cursor-not-allowed opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
