export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-line-strong bg-surface text-accent shadow-sm focus:ring-accent focus:ring-offset-surface ' +
                className
            }
        />
    );
}
