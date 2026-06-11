import axios from 'axios';
import { useEffect, useState } from 'react';

export type CotizEditLockType = 'generadora' | 'tarjeta';

export type CotizEditLockState =
    | { status: 'taking' }
    | { status: 'owned' }
    | {
          status: 'blocked';
          lockedBy: { id: string; name: string } | null;
          lockedAt: string | null;
      }
    | { status: 'error'; message: string };

/** El TTL del lock en el servidor es de 15 min; refrescamos a la mitad para no perderlo. */
const HEARTBEAT_MS = 5 * 60 * 1000;

/**
 * Toma el lock de edición de una generadora al montar y lo libera al desmontar.
 * Mientras la edición sigue abierta, re-postea el lock cada 5 min (heartbeat)
 * para refrescar `locked_at` antes de que expire el TTL del servidor.
 * Si alguien más tiene el lock vigente, retorna status=blocked con el dueño.
 */
export function useCotizEditLock(
    type: CotizEditLockType,
    id: number,
): CotizEditLockState {
    const [state, setState] = useState<CotizEditLockState>({
        status: 'taking',
    });

    useEffect(() => {
        let cancelled = false;
        let owned = false;
        let heartbeat: ReturnType<typeof setInterval> | undefined;

        const lockUrl = `/admin/cotiz/lock/${type}/${id}`;

        axios
            .post(lockUrl)
            .then(() => {
                if (cancelled) {
                    return;
                }
                owned = true;
                setState({ status: 'owned' });
                heartbeat = setInterval(() => {
                    axios.post(lockUrl).catch(() => undefined);
                }, HEARTBEAT_MS);
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
                    setState({
                        status: 'error',
                        message: 'No se pudo iniciar la edición.',
                    });
                }
            });

        const release = () => {
            if (!owned) {
                return;
            }
            // Best-effort. sendBeacon sobrevive al unload.
            const url = `/admin/cotiz/unlock/${type}/${id}`;
            const token =
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ?? '';

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
            if (heartbeat) {
                clearInterval(heartbeat);
            }
            window.removeEventListener('beforeunload', release);
            release();
        };
    }, [type, id]);

    return state;
}
