import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RegimenFiscal } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proveedores', href: '/admin/proveedores' },
    { title: 'Nuevo Proveedor', href: '/admin/proveedores/create' },
];

type Props = {
    regimenes: Pick<RegimenFiscal, 'id' | 'clave' | 'descripcion'>[];
};

const esBanorte = (banco: string) => banco.toLowerCase().includes('banorte');

export default function ProveedoresCreate({ regimenes }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        codigo: string;
        razon_social: string;
        nombre_comercial: string;
        rfc: string;
        tipo_persona: string;
        regimen_fiscal_id: string;
        codigo_postal: string;
        domicilio_fiscal: string;
        domicilio_compra: string;
        giro: string;
        direccion: string;
        telefono: string;
        email: string;
        contacto_nombre: string;
        banco: string;
        titular_cuenta: string;
        numero_cuenta: string;
        clabe: string;
        moneda_cuenta: string;
        constancia: File | null;
        caratula: File | null;
        tiene_acceso_portal: boolean;
        password: string;
        password_confirmation: string;
        maneja_credito: boolean;
        respetar_fecha_factura: boolean;
        limite_credito: string;
        dias_credito_default: string;
        tipo_proveedor: string;
    }>({
        codigo: '',
        razon_social: '',
        nombre_comercial: '',
        rfc: '',
        tipo_persona: '',
        regimen_fiscal_id: '',
        codigo_postal: '',
        domicilio_fiscal: '',
        domicilio_compra: '',
        giro: '',
        direccion: '',
        telefono: '',
        email: '',
        contacto_nombre: '',
        banco: '',
        titular_cuenta: '',
        numero_cuenta: '',
        clabe: '',
        moneda_cuenta: 'MXN',
        constancia: null,
        caratula: null,
        tiene_acceso_portal: false,
        password: '',
        password_confirmation: '',
        maneja_credito: false,
        respetar_fecha_factura: false,
        limite_credito: '0',
        dias_credito_default: '0',
        tipo_proveedor: '',
    });

    const banorte = esBanorte(data.banco);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/proveedores', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Proveedor" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-2 text-2xl font-semibold">Nuevo Proveedor</h1>
                    <div className="alert alert-info mb-6">
                        <span>El proveedor quedará <strong>desactivado</strong> hasta validar su documentación en la aprobación de la requisición. Ya podrá usarse para cotizar.</span>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <h2 className="text-lg font-medium">Datos Fiscales</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="Ej: PROV001" />
                            </FormField>
                            <FormField label="RFC" htmlFor="rfc" error={errors.rfc} required>
                                <Input id="rfc" value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} placeholder="Ej: ABC123456XY0" />
                            </FormField>
                        </div>

                        <FormField label="Razón Social" htmlFor="razon_social" error={errors.razon_social} required>
                            <Input id="razon_social" value={data.razon_social} onChange={(e) => setData('razon_social', e.target.value)} />
                        </FormField>

                        <FormField label="Nombre Comercial" htmlFor="nombre_comercial" error={errors.nombre_comercial}>
                            <Input id="nombre_comercial" value={data.nombre_comercial} onChange={(e) => setData('nombre_comercial', e.target.value)} />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Tipo de Persona" htmlFor="tipo_persona" error={errors.tipo_persona} required>
                                <Select id="tipo_persona" value={data.tipo_persona} onValueChange={(value) => setData('tipo_persona', value)}>
                                    <option value="">Seleccionar</option>
                                    <option value="fisica">Persona Física</option>
                                    <option value="moral">Persona Moral</option>
                                </Select>
                            </FormField>
                            <FormField label="Régimen Fiscal" htmlFor="regimen_fiscal_id" error={errors.regimen_fiscal_id} required>
                                <Select id="regimen_fiscal_id" value={data.regimen_fiscal_id} onValueChange={(value) => setData('regimen_fiscal_id', value)}>
                                    <option value="">Seleccionar</option>
                                    {regimenes.map((r) => (
                                        <option key={r.id} value={r.id}>{r.clave} - {r.descripcion}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código Postal" htmlFor="codigo_postal" error={errors.codigo_postal} required>
                                <Input id="codigo_postal" value={data.codigo_postal} onChange={(e) => setData('codigo_postal', e.target.value)} />
                            </FormField>
                            <FormField label="Giro / Tipo de Servicio" htmlFor="giro" error={errors.giro}>
                                <Input id="giro" value={data.giro} onChange={(e) => setData('giro', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Domicilio Fiscal" htmlFor="domicilio_fiscal" error={errors.domicilio_fiscal} required>
                            <textarea id="domicilio_fiscal" className="textarea textarea-bordered w-full" value={data.domicilio_fiscal} onChange={(e) => setData('domicilio_fiscal', e.target.value)} />
                        </FormField>

                        <FormField label="Domicilio Interno de Compra" htmlFor="domicilio_compra" error={errors.domicilio_compra}>
                            <textarea id="domicilio_compra" className="textarea textarea-bordered w-full" value={data.domicilio_compra} onChange={(e) => setData('domicilio_compra', e.target.value)} placeholder="Si es distinto al fiscal" />
                        </FormField>

                        <FormField label="Constancia de Situación Fiscal" htmlFor="constancia" error={errors.constancia} required>
                            <input id="constancia" type="file" accept=".pdf,.jpg,.jpeg,.png" className="file-input file-input-bordered w-full" onChange={(e) => setData('constancia', e.target.files?.[0] ?? null)} />
                        </FormField>

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Contacto</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Teléfono" htmlFor="telefono" error={errors.telefono}>
                                <Input id="telefono" value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} />
                            </FormField>
                            <FormField label="Email" htmlFor="email" error={errors.email} required>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </FormField>
                        </div>
                        <FormField label="Nombre de Contacto" htmlFor="contacto_nombre" error={errors.contacto_nombre}>
                            <Input id="contacto_nombre" value={data.contacto_nombre} onChange={(e) => setData('contacto_nombre', e.target.value)} />
                        </FormField>

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Cuenta Bancaria</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Banco" htmlFor="banco" error={errors.banco} required>
                                <Input id="banco" value={data.banco} onChange={(e) => setData('banco', e.target.value)} placeholder="Ej: Banorte, BBVA" />
                            </FormField>
                            <FormField label="Titular de la Cuenta" htmlFor="titular_cuenta" error={errors.titular_cuenta} required>
                                <Input id="titular_cuenta" value={data.titular_cuenta} onChange={(e) => setData('titular_cuenta', e.target.value)} placeholder="Debe coincidir con la razón social" />
                            </FormField>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <FormField label={banorte ? 'Número de Cuenta' : 'Número de Cuenta (opcional)'} htmlFor="numero_cuenta" error={errors.numero_cuenta} required={banorte}>
                                <Input id="numero_cuenta" value={data.numero_cuenta} onChange={(e) => setData('numero_cuenta', e.target.value)} />
                            </FormField>
                            <FormField label="CLABE" htmlFor="clabe" error={errors.clabe} required={!banorte}>
                                <Input id="clabe" value={data.clabe} onChange={(e) => setData('clabe', e.target.value)} placeholder="18 dígitos" />
                            </FormField>
                            <FormField label="Moneda" htmlFor="moneda_cuenta" error={errors.moneda_cuenta} required>
                                <Select id="moneda_cuenta" value={data.moneda_cuenta} onValueChange={(value) => setData('moneda_cuenta', value)}>
                                    <option value="MXN">MXN</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                </Select>
                            </FormField>
                        </div>
                        <p className="text-xs text-base-content/60">
                            {banorte ? 'Banorte: basta el número de cuenta interno.' : 'Banco externo a Banorte: la CLABE es obligatoria.'}
                        </p>
                        <FormField label="Carátula Bancaria" htmlFor="caratula" error={errors.caratula} required>
                            <input id="caratula" type="file" accept=".pdf,.jpg,.jpeg,.png" className="file-input file-input-bordered w-full" onChange={(e) => setData('caratula', e.target.files?.[0] ?? null)} />
                        </FormField>

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Configuración</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Tipo de Proveedor" htmlFor="tipo_proveedor" error={errors.tipo_proveedor}>
                                <Select id="tipo_proveedor" value={data.tipo_proveedor} onValueChange={(value) => setData('tipo_proveedor', value)}>
                                    <option value="">Seleccionar</option>
                                    <option value="materiales">Materiales</option>
                                    <option value="servicios">Servicios</option>
                                    <option value="equipos">Equipos</option>
                                    <option value="mixto">Mixto</option>
                                </Select>
                            </FormField>
                        </div>

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Acceso al Portal</h2>
                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.tiene_acceso_portal} onChange={(e) => setData('tiene_acceso_portal', e.target.checked)} />
                            <span className="label-text">Tiene acceso al portal</span>
                        </label>

                        {data.tiene_acceso_portal && (
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Contraseña" htmlFor="password" error={errors.password} required>
                                    <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                                </FormField>
                                <FormField label="Confirmar Contraseña" htmlFor="password_confirmation" error={errors.password_confirmation} required>
                                    <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                                </FormField>
                            </div>
                        )}

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Crédito</h2>
                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.maneja_credito} onChange={(e) => setData('maneja_credito', e.target.checked)} />
                            <span className="label-text">Maneja crédito</span>
                        </label>

                        {data.maneja_credito && (
                            <>
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Límite de Crédito" htmlFor="limite_credito" error={errors.limite_credito}>
                                        <Input id="limite_credito" type="number" step="0.01" min="0" value={data.limite_credito} onChange={(e) => setData('limite_credito', e.target.value)} />
                                    </FormField>
                                    <FormField label="Días de Crédito" htmlFor="dias_credito_default" error={errors.dias_credito_default}>
                                        <Input id="dias_credito_default" type="number" min="0" value={data.dias_credito_default} onChange={(e) => setData('dias_credito_default', e.target.value)} />
                                    </FormField>
                                </div>
                                <label className="label cursor-pointer gap-2 w-fit">
                                    <input type="checkbox" className="checkbox" checked={data.respetar_fecha_factura} onChange={(e) => setData('respetar_fecha_factura', e.target.checked)} />
                                    <span className="label-text">Respetar fecha factura</span>
                                    <span className="label-text text-base-content/50 text-xs">(calcular fecha de pago desde la fecha del CFDI en vez de hoy)</span>
                                </label>
                            </>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/proveedores">Cancelar</Link>
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
