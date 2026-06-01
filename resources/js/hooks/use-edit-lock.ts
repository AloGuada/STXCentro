import axios from 'axios';
import { useEffect, useState } from 'react';

export type EditLockType = 'orden-compra' | 'factura' | 'pago' | 'solicitud-pago' | 'afectacion';

export type EditLockState =
    | { status: 'taking' }
    | { status: 'owned' }
    | { status: 'blocked'; lockedBy: { id: string; name: string } | null; lockedAt: string | null }
    | { status: 'error'; message: string };

/**
 * Toma el lock de edición al montar y lo libera al desmontar.
 * Si alguien más tiene el lock vigente, retorna status=blocked con el dueño.
 */
export function useEditLock(type: EditLockType, id: number): EditLockState {
    const [state, setState] = useState<EditLockState>({ status: 'taking' });

    useEffect(() => {
        let cancelled = false;

        axios
            .post(`/admin/costos/lock/${type}/${id}`)
            .then(() => {
                if (! cancelled) {
                    setState({ status: 'owned' });
                }
            })
            .catch((error) => {
                if (cancelled) {
                    return;
                }
                if (error.response?.status === 423) {
                    setState({
                        status: 'blocked',
                        lockedBy: error.response.data.locked_by ?? null,
                        lockedAt: error.response.data.locked_at ?? null,
                    });
                } else {
                    setState({ status: 'error', message: 'No se pudo iniciar la edición.' });
                }
            });

        const release = () => {
            // Best-effort. sendBeacon sobrevive al unload.
            const url = `/admin/costos/unlock/${type}/${id}`;
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

            if (typeof navigator !== 'undefined' && 'sendBeacon' in navigator) {
                const payload = new FormData();
                payload.append('_token', token);
                navigator.sendBeacon(url, payload);
            } else {
                axios.post(url).catch(() => undefined);
            }
        };

        window.addEventListener('beforeunload', release);

        return () => {
            cancelled = true;
            window.removeEventListener('beforeunload', release);
            release();
        };
    }, [type, id]);

    return state;
}
