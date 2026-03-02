import { useEffect, useRef, useState } from 'react';

type UseFormCacheOptions<T extends Record<string, unknown>> = {
    key: string;
    data: T;
    setData: (key: keyof T, value: T[keyof T]) => void;
    exclude?: (keyof T)[];
};

/**
 * Persists form data in localStorage to prevent data loss on network failures.
 * Restores cached data on mount if available, and syncs changes on every update.
 * Returns a flag to show a "datos no guardados" banner and a function to clear the cache.
 */
export function useFormCache<T extends Record<string, unknown>>({ key, data, setData, exclude = [] }: UseFormCacheOptions<T>) {
    const storageKey = `form-cache:${key}`;
    const [hasCachedData, setHasCachedData] = useState(false);
    const restoredRef = useRef(false);
    const initialDataRef = useRef(data);

    // Restore cached data on mount (once)
    useEffect(() => {
        if (restoredRef.current) return;
        restoredRef.current = true;

        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return;

            const cached = JSON.parse(raw) as Partial<T>;
            const hasValues = Object.entries(cached).some(([k, v]) => !exclude.includes(k as keyof T) && v !== '' && v !== null);

            if (!hasValues) {
                localStorage.removeItem(storageKey);
                return;
            }

            for (const [field, value] of Object.entries(cached)) {
                if (!exclude.includes(field as keyof T) && value !== undefined) {
                    setData(field as keyof T, value as T[keyof T]);
                }
            }
            setHasCachedData(true);
        } catch {
            localStorage.removeItem(storageKey);
        }
    }, [storageKey]); // eslint-disable-line react-hooks/exhaustive-deps

    // Save to localStorage on every data change (skip the initial render)
    useEffect(() => {
        if (!restoredRef.current) return;

        const toCache: Partial<T> = {};
        let hasValues = false;

        for (const [field, value] of Object.entries(data)) {
            if (exclude.includes(field as keyof T)) continue;
            toCache[field as keyof T] = value as T[keyof T];
            if (value !== '' && value !== null && value !== initialDataRef.current[field as keyof T]) {
                hasValues = true;
            }
        }

        if (hasValues) {
            localStorage.setItem(storageKey, JSON.stringify(toCache));
            setHasCachedData(true);
        } else {
            localStorage.removeItem(storageKey);
            setHasCachedData(false);
        }
    }, [data, storageKey]); // eslint-disable-line react-hooks/exhaustive-deps

    const clearCache = () => {
        localStorage.removeItem(storageKey);
        setHasCachedData(false);
    };

    return { hasCachedData, clearCache };
}
