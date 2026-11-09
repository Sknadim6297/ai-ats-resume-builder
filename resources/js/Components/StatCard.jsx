import Card from '@/Components/Card';

export default function StatCard({ label, value, hint }) {
    return (
        <Card className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </p>
            <p className="mt-2 truncate text-3xl font-semibold tracking-tight text-slate-900">
                {Number(value).toLocaleString()}
            </p>
            {hint && <p className="mt-2 text-xs text-slate-500">{hint}</p>}
        </Card>
    );
}
