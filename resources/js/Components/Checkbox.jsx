export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan focus:ring-offset-0 ' +
                className
            }
        />
    );
}
