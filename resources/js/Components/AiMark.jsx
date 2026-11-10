export default function AiMark({ className = '' }) {
    return (
        <span className={'ai-mark ' + className} aria-hidden="true">
            <span className="ai-mark-core">
                <svg viewBox="0 0 24 24" className="h-4 w-4 text-white" fill="none">
                    <path
                        d="M12 3l1.6 4.8L18.5 9.5 13.6 11.1 12 16l-1.6-4.9L6 9.5l4.4-1.7L12 3z"
                        fill="currentColor"
                    />
                    <path
                        d="M18 14.5l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7.7-2z"
                        fill="currentColor"
                        opacity="0.85"
                    />
                </svg>
            </span>
        </span>
    );
}
