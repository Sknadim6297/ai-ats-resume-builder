export default function Card({ children, className = '', padding = true }) {
    return (
        <div
            className={
                'overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 shadow-sm shadow-slate-200/70 backdrop-blur-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md hover:shadow-indigo-100 ' +
                (padding ? 'p-5 sm:p-6 ' : '') +
                className
            }
        >
            {children}
        </div>
    );
}
