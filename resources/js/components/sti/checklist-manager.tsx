import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { StiCheck } from '@/types/models';
import { router } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpIcon, Loader2Icon, PencilIcon, PlusIcon, SaveIcon, TrashIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    planId: number;
    checks: StiCheck[];
};

export function ChecklistManager({ planId, checks }: Props) {
    const [newCheck, setNewCheck] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingValue, setEditingValue] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleAdd = () => {
        if (!newCheck.trim()) return;
        setProcessing(true);
        router.post(
            `/admin/sti/planes/${planId}/checks`,
            { descripcion: newCheck },
            {
                preserveScroll: true,
                onSuccess: () => setNewCheck(''),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleUpdate = (checkId: number) => {
        if (!editingValue.trim()) return;
        setProcessing(true);
        router.put(
            `/admin/sti/planes/${planId}/checks/${checkId}`,
            { descripcion: editingValue },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    setEditingValue('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleDelete = (checkId: number) => {
        if (!confirm('Eliminar este check?')) return;
        setProcessing(true);
        router.delete(`/admin/sti/planes/${planId}/checks/${checkId}`, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const handleReorder = (fromIndex: number, toIndex: number) => {
        if (toIndex < 0 || toIndex >= checks.length) return;
        const newOrder = [...checks];
        const [moved] = newOrder.splice(fromIndex, 1);
        newOrder.splice(toIndex, 0, moved);

        setProcessing(true);
        router.post(
            `/admin/sti/planes/${planId}/reorder-checks`,
            { order: newOrder.map((c) => c.id) },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const startEditing = (check: StiCheck) => {
        setEditingId(check.id);
        setEditingValue(check.descripcion);
    };

    const cancelEditing = () => {
        setEditingId(null);
        setEditingValue('');
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-2">
                <Input
                    value={newCheck}
                    onChange={(e) => setNewCheck(e.target.value)}
                    placeholder="Nuevo check..."
                    className="flex-1"
                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleAdd())}
                />
                <Button onClick={handleAdd} disabled={processing || !newCheck.trim()} size="sm">
                    {processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                    Agregar
                </Button>
            </div>

            {checks.length === 0 ? (
                <p className="py-4 text-center text-sm text-gray-500">No hay checks definidos para este plan.</p>
            ) : (
                <div className="divide-y rounded-lg border">
                    {checks.map((check, index) => (
                        <div key={check.id} className="flex items-center gap-2 p-3">
                            <div className="flex flex-col">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-6"
                                    onClick={() => handleReorder(index, index - 1)}
                                    disabled={index === 0 || processing}
                                >
                                    <ArrowUpIcon className="size-3" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-6"
                                    onClick={() => handleReorder(index, index + 1)}
                                    disabled={index === checks.length - 1 || processing}
                                >
                                    <ArrowDownIcon className="size-3" />
                                </Button>
                            </div>

                            <span className="w-8 text-center text-sm font-medium text-gray-500">{index + 1}.</span>

                            {editingId === check.id ? (
                                <>
                                    <Input
                                        value={editingValue}
                                        onChange={(e) => setEditingValue(e.target.value)}
                                        className="flex-1"
                                        autoFocus
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                handleUpdate(check.id);
                                            }
                                            if (e.key === 'Escape') cancelEditing();
                                        }}
                                    />
                                    <Button variant="ghost" size="icon" onClick={() => handleUpdate(check.id)} disabled={processing}>
                                        <SaveIcon className="size-4 text-green-600" />
                                    </Button>
                                    <Button variant="ghost" size="icon" onClick={cancelEditing}>
                                        <XIcon className="size-4" />
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <span className="flex-1">{check.descripcion}</span>
                                    <Button variant="ghost" size="icon" onClick={() => startEditing(check)} disabled={processing}>
                                        <PencilIcon className="size-4 text-gray-500" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => handleDelete(check.id)}
                                        disabled={processing}
                                        className="text-red-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <TrashIcon className="size-4" />
                                    </Button>
                                </>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
