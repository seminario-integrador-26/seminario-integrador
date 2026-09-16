export default function ApplicationLogo({ className = 'h-9 w-auto', ...props }) {
    return (
        <img
            src="/screen.png"
            alt="Atalaya — Centro de Monitoreo Urbano"
            className={`select-none object-contain drop-shadow-[0_0_8px_rgba(0,210,255,0.2)] ${className}`}
            {...props}
        />
    );
}
