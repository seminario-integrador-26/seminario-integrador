export default function InputError({ message, className = '', ...props }) {
    return message ? (
        <p
            {...props}
            className={'font-mono text-xs text-atalaya-crimson ' + className}
        >
            {message}
        </p>
    ) : null;
}
