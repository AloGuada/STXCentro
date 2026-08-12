import { PantallaPendiente } from '@/components/qal/pantalla-pendiente';

export default function CalidadPiezas() {
    return (
        <PantallaPendiente
            titulo="Piezas y planos"
            descripcion="La pieza que se inspecciona y los planos sobre los que se marca el reporte."
            tabla="cal_piezas · cal_piezas_planos"
            campos={[
                'Pieza: marca y cantidad, colgada de una etapa',
                'Plano: PDF, imagen del plano y archivo DWG',
                'El plano lleva versión, así que una pieza puede tener varias revisiones',
            ]}
            pendiente={
                <>
                    <p>
                        La <strong>marca</strong> es la misma nomenclatura que usa Producción en su catálogo de piezas,
                        pero son tablas distintas y hoy nadie las cruza. Si se amarran, se podría preguntar desde
                        Producción si una pieza ya está liberada.
                    </p>
                    <p>Falta definir si desde la web se suben planos o sólo se ven los que ya subió la aplicación.</p>
                </>
            }
        />
    );
}
