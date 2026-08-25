type ProveedorEtiquetable = {
    razon_social: string;
    nombre_comercial?: string | null;
};

/**
 * Etiqueta de beneficiario: razón social y nombre comercial concatenados,
 * omitiendo el segundo cuando falta o repite a la razón social.
 */
export function etiquetaProveedor(proveedor: ProveedorEtiquetable): string {
    const comercial = proveedor.nombre_comercial?.trim();

    if (!comercial || comercial === proveedor.razon_social.trim()) {
        return proveedor.razon_social;
    }

    return `${proveedor.razon_social} — ${comercial}`;
}
