import type { EditLockState } from '@/hooks/use-edit-lock';
import { LockIcon } from 'lucide-react';

type Props = {
    state: EditLockState;
};

function formatDesde(iso: string | null): string {
    if (!iso) {
        return '';
    }
    const diff = (Date.now() - new Date(iso).getTime()) / 60000;
    if (diff < 1) {
        return 'hace un momento';
    }
    if (diff < 60) {
        return `hace ${Math.round(diff)} min`;
    }
    const horas = Math.round(diff / 60);
    return `hace ${horas} h`;
}

export function EditLockBanner({ state }: Props) {
    if (state.status === 'owned' || state.status === 'taking') {
        return null;
    }

    if (state.status === 'error') {
        return (
            <div className="alert alert-error mb-4">
                <LockIcon className="size-5" />
                <span>{state.message}</span>
            </div>
        );
    }

    const nombre = state.lockedBy?.name ?? 'Otro usuario';

    return (
        <div className="alert alert-warning mb-4">
            <LockIcon className="size-5" />
            <div>
                <p className="font-medium">Este registro está siendo editado por {nombre}.</p>
                <p className="text-xs opacity-80">
                    Inició {formatDesde(state.lockedAt)}. Puede ver el formulario pero no guardar cambios hasta que
                    {' '}{nombre} termine o su sesión de edición expire.
                </p>
            </div>
        </div>
    );
}
