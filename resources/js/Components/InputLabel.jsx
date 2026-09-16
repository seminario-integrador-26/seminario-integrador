export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                `mb-1 block font-mono text-xs font-semibold uppercase tracking-wider text-atalaya-text-muted ` +
                className
            }
        >
            {value ? value : children}
        </label>
    );
}
