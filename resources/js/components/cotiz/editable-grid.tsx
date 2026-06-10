import { useAppearance } from '@/hooks/use-appearance';
import {
    AllCommunityModule,
    type ColDef,
    colorSchemeDark,
    ModuleRegistry,
    themeQuartz,
} from 'ag-grid-community';
import { AgGridReact } from 'ag-grid-react';
import { useMemo } from 'react';

ModuleRegistry.registerModules([AllCommunityModule]);

const themeLight = themeQuartz.withParams({
    accentColor: 'oklch(0.55 0.2 264)',
    borderRadius: 6,
    fontFamily: 'inherit',
    headerFontWeight: 600,
});
const themeDark = themeLight.withPart(colorSchemeDark);

export type EditableGridProps<T> = {
    rowData: T[];
    columnDefs: ColDef<T>[];
    getRowId: (row: T) => string;
    /** Se invoca con la fila completa cuando una celda termina de editarse y cambió de valor. */
    onCellEdited?: (row: T) => void;
    quickFilterText?: string;
    /** Alto del contenedor; AG-Grid requiere altura explícita. */
    height?: string;
};

/**
 * Wrapper base de AG-Grid Community (MIT) para las pantallas tipo hoja de cálculo
 * del módulo Cotización. Tema alineado al claro/oscuro de la app vía Theming API
 * (sin imports de CSS). No usa features Enterprise.
 */
export function EditableGrid<T>({
    rowData,
    columnDefs,
    getRowId,
    onCellEdited,
    quickFilterText,
    height = '70vh',
}: EditableGridProps<T>) {
    const { resolvedAppearance } = useAppearance();

    const defaultColDef = useMemo<ColDef<T>>(
        () => ({
            sortable: true,
            resizable: true,
            filter: true,
            flex: 1,
            minWidth: 120,
        }),
        [],
    );

    return (
        <div style={{ height }}>
            <AgGridReact<T>
                theme={resolvedAppearance === 'dark' ? themeDark : themeLight}
                rowData={rowData}
                columnDefs={columnDefs}
                defaultColDef={defaultColDef}
                getRowId={(params) => getRowId(params.data)}
                quickFilterText={quickFilterText}
                singleClickEdit
                stopEditingWhenCellsLoseFocus
                onCellValueChanged={(event) => {
                    if (event.oldValue !== event.newValue) {
                        onCellEdited?.(event.data);
                    }
                }}
                animateRows
                pagination
                paginationPageSize={50}
                paginationPageSizeSelector={[25, 50, 100, 200]}
            />
        </div>
    );
}
