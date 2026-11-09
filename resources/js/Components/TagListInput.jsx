import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { useState } from 'react';

export default function TagListInput({
    label,
    values = [],
    onChange,
    placeholder = 'Type and press Enter',
    error,
}) {
    const [draft, setDraft] = useState('');

    const addValue = () => {
        const next = draft.trim();
        if (!next) {
            return;
        }
        if (values.includes(next)) {
            setDraft('');
            return;
        }
        onChange([...values, next]);
        setDraft('');
    };

    return (
        <div>
            <InputLabel value={label} />
            <div className="mt-1 flex gap-2">
                <TextInput
                    className="block w-full"
                    value={draft}
                    placeholder={placeholder}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            addValue();
                        }
                    }}
                />
                <button
                    type="button"
                    onClick={addValue}
                    className="rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Add
                </button>
            </div>
            {values.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {values.map((value) => (
                        <span
                            key={value}
                            className="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700"
                        >
                            {value}
                            <button
                                type="button"
                                className="text-indigo-500 hover:text-indigo-800"
                                onClick={() => onChange(values.filter((item) => item !== value))}
                                aria-label={`Remove ${value}`}
                            >
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}
            <InputError message={error} className="mt-2" />
        </div>
    );
}
