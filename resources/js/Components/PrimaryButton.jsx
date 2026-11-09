export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-xl border border-transparent bg-gradient-to-r from-indigo-600 via-violet-600 to-sky-500 bg-[length:180%_180%] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-lg shadow-indigo-500/25 transition duration-300 ease-out hover:bg-[position:100%_50%] focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 active:scale-[0.98] ${
                    disabled && 'opacity-40'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
