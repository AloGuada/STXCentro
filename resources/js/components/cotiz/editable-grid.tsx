import { useAppearance } from '@/hooks/use-appearance';
import {
    AllCommunityModule,
    type ColDef,
    colorSchemeDark,
    ModuleRegistry,
    type RowClassParams,
    type RowStyle,
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
    /**
     * Se invoca cuando una celda termina de editarse y cambió de valor. Recibe la fila
     * completa y el `field`/`colId` de la columna editada (para enrutar el guardado).
     */
    onCellEdited?: (row: T, field?: string) => void;
    quickFilterText?: string;
    /** Alto del contenedor; AG-Grid requiere altura explícita. */
    height?: string;
    /** Filas fijadas al pie (p. ej. la fila TOTAL del footer). */
    pinnedBottomRowData?: T[];
    /** Estilo por fila (p. ej. resaltar el footer o filas con problema). */
    getRowStyle?: (params: RowClassParams<T>) => RowStyle | undefined;
    /** Desactiva la paginación (útil cuando hay footer fijo y pocas filas). */
    paginated?: boolean;
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
    pinnedBottomRowData,
    getRowStyle,
    paginated = true,
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
                pinnedBottomRowData={pinnedBottomRowData}
                getRowStyle={getRowStyle}
                singleClickEdit
                stopEditingWhenCellsLoseFocus
                onCellValueChanged={(event) => {
                    if (event.oldValue !== event.newValue) {
                        onCellEdited?.(
                            event.data,
                            event.colDef.field ?? event.colDef.colId,
                        );
                    }
                }}
                animateRows
                pagination={paginated}
                paginationPageSize={50}
                paginationPageSizeSelector={[25, 50, 100, 200]}
            />
        </div>
    );
}
