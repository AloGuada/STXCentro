import { PantallaPendiente } from '@/components/qal/pantalla-pendiente';

export default function CalidadObras() {
    return (
        <PantallaPendiente
            titulo="Obras"
            descripcion="Las obras que sigue Calidad, con sus etapas. Cada etapa agrupa las piezas que se inspeccionan."
            tabla="cal_obras · cal_etapas"
            campos={[
                'Obra: número, descripción y si sigue activa',
                'Etapa: descripción, colgada de una obra',
                'Una etapa se borra en cascada con su obra',
            ]}
            pendiente={
                <>
                    <p>
                        <strong>Son obras propias del módulo</strong>, no las de <span className="font-mono">obras</span>{' '}
                        del core: Calidad las dio de alta aparte y hasta hoy no hay puente entre ambas. Hay que decidir
                        si se amarran o siguen separadas.
                    </p>
                    <p>
                        Falta también si las etapas se administran desde aquí o sólo se consultan, dado que la
                        aplicación de inspección ya las crea.
                    </p>
                </>
            }
        />
    );
}
