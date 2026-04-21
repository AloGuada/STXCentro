import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Periodos Laborales', href: '/admin/rh/periodos-laborales' },
    { title: 'Nuevo Periodo Laboral', href: '/admin/rh/periodos-laborales/create' },
];

type Props = {
    personas: { id: number; nombre: string; apellido: string }[];
    puestos: { id: number; nombre: string }[];
    requisiciones: { id: number; folio: string; puesto_id: number; tipo_contrato_generado: string | null; salario: string | null }[];
};

export default function PeriodoLaboralCreate({ personas, puestos, requisiciones }: Props) {
    const params = new URLSearchParams(window.location.search);
    const reqId = params.get('requisicion_id');
    const reqInicial = reqId ? requisiciones.find((r) => String(r.id) === reqId) : null;

    const { data, setData, post, processing, errors } = useForm({
        persona_id: params.get('persona_id') ?? '',
        puesto_id: params.get('puesto_id') ?? '',
        requisicion_id: reqId ?? '',
        fecha_inicio: '',
        fecha_fin: '',
        salario_diario: reqInicial?.salario ?? '',
        sueldo_mensual: '',
        sueldo_real: '',
        periodicidad_pago: '',
        tipo_salario: '',
        tipo_contrato: reqInicial?.tipo_contrato_generado ?? '',
        numero_empleado: '',
        numero_locker: '',
        tipo_empleado: '',
        estado: 'activo' as 'activo' | 'terminado' | 'baja',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/rh/periodos-laborales');
    };

    const personaOptions = personas.map((p) => ({ value: String(p.id), label: `${p.nombre} ${p.apellido}` }));
    const puestoOptions = puestos.map((p) => ({ value: String(p.id), label: p.nombre }));
    const requisicionOptions = requisiciones.map((r) => ({ value: String(r.id), label: r.folio }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Periodo Laboral" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Periodo Laboral</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Persona" htmlFor="persona_id" error={errors.persona_id} required>
                            <SearchSelect
                                options={personaOptions}
                                value={data.persona_id}
                                onValueChange={(v) => setData('persona_id', v)}
                                placeholder="Buscar persona..."
                            />
                        </FormField>

                        <FormField label="Puesto" htmlFor="puesto_id" error={errors.puesto_id} required>
                            <SearchSelect
                                options={puestoOptions}
                                value={data.puesto_id}
                                onValueChange={(v) => setData('puesto_id', v)}
                                placeholder="Buscar puesto..."
                            />
                        </FormField>

                        <FormField label="Requisición" htmlFor="requisicion_id" error={errors.requisicion_id}>
                            <SearchSelect
                                options={requisicionOptions}
                                value={data.requisicion_id}
                                onValueChange={(v) => setData('requisicion_id', v)}
                                placeholder="Buscar requisición..."
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Fecha de Inicio" htmlFor="fecha_inicio" error={errors.fecha_inicio} required>
                                <Input id="fecha_inicio" type="date" value={data.fecha_inicio} onChange={(e) => setData('fecha_inicio', e.target.value)} />
                            </FormField>

                            <FormField label="Fecha de Fin" htmlFor="fecha_fin" error={errors.fecha_fin}>
                                <Input id="fecha_fin" type="date" value={data.fecha_fin} onChange={(e) => setData('fecha_fin', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Salario Diario" htmlFor="salario_diario" error={errors.salario_diario} required>
                                <Input id="salario_diario" type="number" step="0.01" value={data.salario_diario} onChange={(e) => setData('salario_diario', e.target.value)} placeholder="0.00" />
                            </FormField>

                            <FormField label="Sueldo Mensual" htmlFor="sueldo_mensual" error={errors.sueldo_mensual} required>
                                <Input id="sueldo_mensual" value={data.sueldo_mensual} onChange={(e) => setData('sueldo_mensual', e.target.value)} placeholder="Sueldo mensual" />
                            </FormField>

                            <FormField label="Sueldo Real" htmlFor="sueldo_real" error={errors.sueldo_real}>
                                <Input id="sueldo_real" type="number" step="0.01" value={data.sueldo_real} onChange={(e) => setData('sueldo_real', e.target.value)} placeholder="0.00" />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Periodicidad de Pago" htmlFor="periodicidad_pago" error={errors.periodicidad_pago}>
                                <Select value={data.periodicidad_pago} onValueChange={(v) => setData('periodicidad_pago', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="semanal">Semanal</SelectItem>
                                        <SelectItem value="catorcenal">Catorcenal</SelectItem>
                                        <SelectItem value="quincenal">Quincenal</SelectItem>
                                        <SelectItem value="mensual">Mensual</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Tipo de Salario" htmlFor="tipo_salario" error={errors.tipo_salario}>
                                <Select value={data.tipo_salario} onValueChange={(v) => setData('tipo_salario', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="fijo">Fijo</SelectItem>
                                        <SelectItem value="destajo">Destajo</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Tipo de Contrato" htmlFor="tipo_contrato" error={errors.tipo_contrato}>
                                <Select value={data.tipo_contrato} onValueChange={(v) => setData('tipo_contrato', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="indeterminado">Indeterminado</SelectItem>
                                        <SelectItem value="determinado">Determinado</SelectItem>
                                        <SelectItem value="obra_determinada">Obra determinada</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="No. Empleado" htmlFor="numero_empleado" error={errors.numero_empleado}>
                                <Input id="numero_empleado" value={data.numero_empleado} onChange={(e) => setData('numero_empleado', e.target.value)} placeholder="Ej: P-001" />
                            </FormField>

                            <FormField label="No. Locker" htmlFor="numero_locker" error={errors.numero_locker}>
                                <Input id="numero_locker" value={data.numero_locker} onChange={(e) => setData('numero_locker', e.target.value)} placeholder="Ej: L-042" />
                            </FormField>

                            <FormField label="Tipo de Empleado" htmlFor="tipo_empleado" error={errors.tipo_empleado}>
                                <Select value={data.tipo_empleado} onValueChange={(v) => setData('tipo_empleado', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="planta">Planta</SelectItem>
                                        <SelectItem value="contratista">Contratista</SelectItem>
                                        <SelectItem value="becario">Becario</SelectItem>
                                        <SelectItem value="foraneo">Foraneo</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <FormField label="Estado" htmlFor="estado" error={errors.estado} required>
                            <Select value={data.estado} onValueChange={(v) => setData('estado', v as 'activo' | 'baja')}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="activo">Activo</SelectItem>
                                    <SelectItem value="baja">Baja</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/rh/periodos-laborales">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
