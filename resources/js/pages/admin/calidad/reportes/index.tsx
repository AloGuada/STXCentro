import { PantallaPendiente } from '@/components/qal/pantalla-pendiente';

export default function CalidadReportes() {
    return (
        <PantallaPendiente
            titulo="Reportes de inspección"
            descripcion="El reporte de inspección visual: sobre el plano de la pieza se marcan las juntas revisadas, cada una con su soldador."
            tabla="cal_reportes · cal_flechas"
            campos={[
                'Folio automático IV{año}{mes}{consecutivo}; las plantillas no llevan folio',
                'Plano inspeccionado, inspector, soldador, línea, módulo y comentario',
                'Aprobado y rechazado son fechas, no un estatus: queda cuándo pasó',
                'Flecha: coordenadas de inicio y fin sobre el plano, tipo, soldador y si es doble',
                'Un reporte se puede copiar, y hay reportes marcados como plantilla',
            ]}
            pendiente={
                <>
                    <p>
                        La pieza cara: <strong>marcar flechas sobre el plano</strong> es un editor gráfico, no una
                        tabla. Hay que decidir si la web lo lleva o si sólo consulta lo que marcó la aplicación de
                        inspección y descarga el PDF.
                    </p>
                    <p>
                        Aprobar y rechazar ya existen en la API. Si se van a hacer desde la web, hay que definir quién
                        firma: hoy no hay un permiso de aprobación separado del de editar.
                    </p>
                </>
            }
        />
    );
}
