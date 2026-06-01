import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    open: boolean;
    onClose: () => void;
    url: string;
    title: string;
    description?: string;
    submitLabel?: string;
};

export function CancelarModal({ open, onClose, url, title, description, submitLabel = 'Confirmar cancelación' }: Props) {
    const [motivo, setMotivo] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!open) {
        return null;
    }

    const submit = () => {
        if (motivo.trim().length < 10) {
            setError('El motivo debe tener al menos 10 caracteres.');
            return;
        }

        setProcessing(true);
        setError(null);
        router.post(url, { motivo }, {
            preserveScroll: true,
            onError: (errors) => {
                setProcessing(false);
                const first = errors.motivo ?? errors.estatus ?? 'No se pudo completar la cancelación.';
                setError(first);
            },
            onSuccess: () => {
                setMotivo('');
                onClose();
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box">
                <h3 className="font-bold text-lg mb-3">{title}</h3>
                {description && <p className="mb-4 text-sm text-base-content/60">{description}</p>}

                <label className="form-control w-full">
                    <div className="label">
                        <span className="label-text">Motivo (mínimo 10 caracteres)</span>
                    </div>
                    <textarea
                        className="textarea textarea-bordered w-full"
                        rows={3}
                        maxLength={500}
                        value={motivo}
                        onChange={(e) => setMotivo(e.target.value)}
                        disabled={processing}
                        placeholder="Describa el motivo de la cancelación"
                    />
                </label>

                {error && <p className="mt-2 text-sm text-error">{error}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Volver
                    </Button>
                    <Button variant="destructive" onClick={submit} disabled={processing || motivo.trim().length < 10}>
                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                        {submitLabel}
                    </Button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={processing ? undefined : onClose}></div>
        </dialog>
    );
}
