import type { CustomCellEditorProps } from 'ag-grid-react';
import { useEffect, useRef, useState } from 'react';

let listCounter = 0;

type Props = CustomCellEditorProps<unknown, string> & {
    /** Opciones del autocomplete (lista del `<datalist>`). */
    opciones?: string[];
};

/**
 * Editor de celda de autocomplete (input + `<datalist>` nativo): el usuario escribe para
 * filtrar la lista y elige una opción. Reemplaza al `agSelectCellEditor` cuando hay muchas
 * opciones (p. ej. cientos de insumos). El valor confirmado es el texto elegido; la columna
 * lo resuelve en su `valueSetter`.
 */
export function AutocompleteCellEditor({
    value,
    onValueChange,
    stopEditing,
    opciones = [],
}: Props) {
    const [texto, setTexto] = useState<string>(value ?? '');
    const inputRef = useRef<HTMLInputElement>(null);
    const listId = useRef<string>(`ac-list-${listCounter++}`);

    useEffect(() => {
        inputRef.current?.focus();
        inputRef.current?.select();
    }, []);

    const actualizar = (v: string) => {
        setTexto(v);
        onValueChange(v);
    };

    return (
        <>
            <input
                ref={inputRef}
                list={listId.current}
                value={texto}
                onChange={(e) => actualizar(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === 'Tab') {
                        e.preventDefault();
                        stopEditing();
                    } else if (e.key === 'Escape') {
                        stopEditing(true);
                    }
                }}
                onBlur={() => stopEditing()}
                className="ag-input-field-input"
                style={{
                    width: '100%',
                    height: '100%',
                    boxSizing: 'border-box',
                    padding: '0 8px',
                }}
            />
            <datalist id={listId.current}>
                {opciones.map((o) => (
                    <option key={o} value={o} />
                ))}
            </datalist>
        </>
    );
}
