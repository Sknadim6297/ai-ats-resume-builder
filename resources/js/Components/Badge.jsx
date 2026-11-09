const VARIANTS = {
    default: 'bg-slate-100 text-slate-600',
    success: 'bg-emerald-50 text-emerald-700',
    warning: 'bg-amber-50 text-amber-700',
    info: 'bg-indigo-50 text-indigo-700',
};

export default function Badge({ children, variant = 'default' }) {
    return (
        <span
            className={
                'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ' +
                (VARIANTS[variant] || VARIANTS.default)
            }
        >
            {children}
        </span>
    );
}
