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
                `inline-flex items-center justify-center border border-atalaya-cyan bg-atalaya-cyan px-4 py-2 font-mono text-xs font-bold uppercase tracking-widest text-black transition duration-150 ease-in-out hover:bg-cyan-300 focus:outline-none focus:ring-1 focus:ring-atalaya-cyan active:bg-cyan-400 ${
                    disabled && 'cursor-not-allowed opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
