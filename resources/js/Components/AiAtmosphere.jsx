export default function AiAtmosphere({ className = '' }) {
    return (
        <div className={'pointer-events-none absolute inset-0 overflow-hidden ' + className} aria-hidden="true">
            <div className="ai-orb ai-orb-a" />
            <div className="ai-orb ai-orb-b" />
            <div className="ai-orb ai-orb-c" />
            <div className="ai-grid" />
        </div>
    );
}
