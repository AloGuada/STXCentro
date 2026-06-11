import type { CustomCellEditorProps } from 'ag-grid-react';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';

/**
 * Editor de celda (popup) para fórmulas de factor: input de texto con validación semántica
 * en vivo contra el backend (Validar). Confirma con Enter/Aplicar, cancela con Escape.
 * Port simplificado de prepsim FormulaCellEditor (sin el VariablePicker en cascada).
 */
export function FormulaCellEditor({
    value,
    onValueChange,
    stopEditing,
}: CustomCellEditorProps<unknown, string | null>) {
    const [texto, setTexto] = useState<string>(value ?? '');
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        inputRef.current?.focus();
        inputRef.current?.select();
    }, []);

    // Validación en vivo (debounce) contra el endpoint de Validar.
    useEffect(() => {
        if (texto.trim() === '') {
            setError(null);
            return;
        }
        const t = setTimeout(() => {
            axios
                .post('/admin/cotiz/tarjetas/validar-formula', { formula: texto })
                .then(({ data }) => setError(data.error ?? null))
                .catch(() => undefined);
        }, 300);
        return () => clearTimeout(t);
    }, [texto]);

    const actualizar = (v: string) => {
        setTexto(v);
        onValueChange(v === '' ? null : v);
    };

    return (
        <div
            className="flex flex-col gap-1 rounded border border-base-300 bg-base-100 p-2 shadow-lg"
            style={{ width: 420 }}
        >
            <input
                ref={inputRef}
                value={texto}
                onChange={(e) => actualizar(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        stopEditing();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        stopEditing(true);
                    }
                }}
                className="input input-sm input-bordered font-mono text-xs"
                placeholder="ej. total.tarjeta.kg * 0.02"
            />
            <div
                className={
                    error
                        ? 'text-[11px] text-error'
                        : 'text-[11px] opacity-50'
                }
            >
                {error
                    ? `⚠ ${error}`
                    : 'Escribe la fórmula. Vacío = usa la fórmula global. Ej: total.tarjeta.kg, total.tarjeta.importe[cc=acero], tarjeta.factor[cod=OXIGENO].'}
            </div>
            <div className="flex justify-end gap-1">
                <button
                    type="button"
                    className="btn btn-xs"
                    onMouseDown={(e) => {
                        e.preventDefault();
                        stopEditing(true);
                    }}
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    className="btn btn-xs btn-primary"
                    onMouseDown={(e) => {
                        e.preventDefault();
                        stopEditing();
                    }}
                >
                    Aplicar
                </button>
            </div>
        </div>
    );
}
