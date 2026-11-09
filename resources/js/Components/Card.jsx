export default function Card({ children, className = '', padding = true }) {
    return (
        <div
            className={
                'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ' +
                (padding ? 'p-5 sm:p-6 ' : '') +
                className
            }
        >
            {children}
        </div>
    );
}
