import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { DocumentoField } from '@/components/costos/documento-field';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Banco, RegimenFiscal } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proveedores', href: '/admin/proveedores' },
    { title: 'Nuevo Proveedor', href: '/admin/proveedores/create' },
];

type Props = {
    regimenes: Pick<RegimenFiscal, 'id' | 'clave' | 'descripcion'>[];
    bancos: Pick<Banco, 'id' | 'nombre' | 'digitos_cuenta' | 'es_pagador'>[];
};

export default function ProveedoresCreate({ regimenes, bancos }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        tipo_proveedor: string;
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
        contacto_correo: string;
        numero_servicio: string;
        referencia_servicio: string;
        forma_pago: string;
        banco_id: string;
        titular_cuenta: string;
        numero_cuenta: string;
        clabe: string;
        tarjeta: string;
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
    }>({
        tipo_proveedor: 'proveedor',
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
        contacto_correo: '',
        numero_servicio: '',
        referencia_servicio: '',
        forma_pago: 'transferencia',
        banco_id: '',
        titular_cuenta: '',
        numero_cuenta: '',
        clabe: '',
        tarjeta: '',
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
    });

    const esServicio = data.tipo_proveedor === 'servicio';
    const esProveedorFormal = data.tipo_proveedor === 'proveedor';
    const transferencia = data.forma_pago === 'transferencia';
    const bancoSel = bancos.find((b) => String(b.id) === data.banco_id);
    const esPagador = bancoSel?.es_pagador ?? false;
    const digitos = bancoSel?.digitos_cuenta ?? 10;

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
                        <FormField label="Tipo" htmlFor="tipo_proveedor" error={errors.tipo_proveedor} required>
                            <Select id="tipo_proveedor" value={data.tipo_proveedor} onValueChange={(value) => setData('tipo_proveedor', value)}>
                                <option value="proveedor">Proveedor</option>
                                <option value="tercero">Tercero</option>
                                <option value="servicio">Servicio (luz, agua, etc.)</option>
                            </Select>
                        </FormField>

                        {esServicio ? (
                            <>
                                <div className="divider" />
                                <h2 className="text-lg font-medium">Datos del Servicio</h2>
                                <FormField label="Nombre o Razón Social" htmlFor="razon_social" error={errors.razon_social} required>
                                    <Input id="razon_social" value={data.razon_social} onChange={(e) => setData('razon_social', e.target.value)} placeholder="Ej: CFE, Agua y Saneamiento" />
                                </FormField>
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Número de Servicio" htmlFor="numero_servicio" error={errors.numero_servicio} required>
                                        <Input id="numero_servicio" value={data.numero_servicio} onChange={(e) => setData('numero_servicio', e.target.value)} />
                                    </FormField>
                                    <FormField label="Referencia" htmlFor="referencia_servicio" error={errors.referencia_servicio} required>
                                        <Input id="referencia_servicio" value={data.referencia_servicio} onChange={(e) => setData('referencia_servicio', e.target.value)} />
                                    </FormField>
                                </div>
                                <FormField label="Email" htmlFor="email" error={errors.email}>
                                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                </FormField>
                            </>
                        ) : (
                            <>
                                <div className="divider" />
                                <h2 className="text-lg font-medium">Datos Fiscales</h2>
                                <FormField label={esProveedorFormal ? 'RFC' : 'RFC (opcional)'} htmlFor="rfc" error={errors.rfc} required={esProveedorFormal}>
                                    <Input id="rfc" value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} placeholder="Ej: ABC123456XY0" />
                                    <p className="mt-1 text-xs text-base-content/60">El código de proveedor se generará automáticamente.</p>
                                </FormField>

                                <FormField label={esProveedorFormal ? 'Razón Social' : 'Nombre o Razón Social'} htmlFor="razon_social" error={errors.razon_social} required>
                                    <Input id="razon_social" value={data.razon_social} onChange={(e) => setData('razon_social', e.target.value)} />
                                </FormField>

                                <FormField label="Nombre Comercial" htmlFor="nombre_comercial" error={errors.nombre_comercial}>
                                    <Input id="nombre_comercial" value={data.nombre_comercial} onChange={(e) => setData('nombre_comercial', e.target.value)} />
                                </FormField>

                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Tipo de Persona" htmlFor="tipo_persona" error={errors.tipo_persona} required={esProveedorFormal}>
                                        <Select id="tipo_persona" value={data.tipo_persona} onValueChange={(value) => setData('tipo_persona', value)}>
                                            <option value="">Seleccionar</option>
                                            <option value="fisica">Persona Física</option>
                                            <option value="moral">Persona Moral</option>
                                        </Select>
                                    </FormField>
                                    <FormField label="Régimen Fiscal" htmlFor="regimen_fiscal_id" error={errors.regimen_fiscal_id} required={esProveedorFormal}>
                                        <Select id="regimen_fiscal_id" value={data.regimen_fiscal_id} onValueChange={(value) => setData('regimen_fiscal_id', value)}>
                                            <option value="">Seleccionar</option>
                                            {regimenes.map((r) => (
                                                <option key={r.id} value={r.id}>{r.clave} - {r.descripcion}</option>
                                            ))}
                                        </Select>
                                    </FormField>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Código Postal" htmlFor="codigo_postal" error={errors.codigo_postal} required={esProveedorFormal}>
                                        <Input id="codigo_postal" value={data.codigo_postal} onChange={(e) => setData('codigo_postal', e.target.value)} />
                                    </FormField>
                                    <FormField label="Giro / Tipo de Servicio" htmlFor="giro" error={errors.giro}>
                                        <Input id="giro" value={data.giro} onChange={(e) => setData('giro', e.target.value)} />
                                    </FormField>
                                </div>

                                <FormField label="Domicilio Fiscal" htmlFor="domicilio_fiscal" error={errors.domicilio_fiscal} required={esProveedorFormal}>
                                    <textarea id="domicilio_fiscal" className="textarea textarea-bordered w-full" value={data.domicilio_fiscal} onChange={(e) => setData('domicilio_fiscal', e.target.value)} />
                                </FormField>

                                <FormField label="Domicilio Interno de Compra" htmlFor="domicilio_compra" error={errors.domicilio_compra}>
                                    <textarea id="domicilio_compra" className="textarea textarea-bordered w-full" value={data.domicilio_compra} onChange={(e) => setData('domicilio_compra', e.target.value)} placeholder="Si es distinto al fiscal" />
                                </FormField>

                                <FormField label={esProveedorFormal ? 'Constancia de Situación Fiscal' : 'Constancia de Situación Fiscal (opcional)'} htmlFor="constancia" error={errors.constancia} required={esProveedorFormal}>
                                    <DocumentoField id="constancia" archivo={data.constancia} onChange={(file) => setData('constancia', file)} />
                                </FormField>

                                <div className="divider" />
                                <h2 className="text-lg font-medium">Contacto</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Teléfono" htmlFor="telefono" error={errors.telefono}>
                                        <Input id="telefono" value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} />
                                    </FormField>
                                    <FormField label="Nombre de Contacto" htmlFor="contacto_nombre" error={errors.contacto_nombre}>
                                        <Input id="contacto_nombre" value={data.contacto_nombre} onChange={(e) => setData('contacto_nombre', e.target.value)} />
                                    </FormField>
                                </div>
                                <FormField label="Correo del Contacto" htmlFor="contacto_correo" error={errors.contacto_correo}>
                                    <Input id="contacto_correo" type="email" value={data.contacto_correo} onChange={(e) => setData('contacto_correo', e.target.value)} />
                                </FormField>

                                <div className="divider" />
                                <h2 className="text-lg font-medium">Cuenta Bancaria</h2>
                                <FormField label="Forma de Pago" htmlFor="forma_pago" error={errors.forma_pago} required>
                                    <div className="join">
                                        <button type="button" className={`btn join-item ${transferencia ? 'btn-primary' : 'btn-outline'}`} onClick={() => setData('forma_pago', 'transferencia')}>Transferencia</button>
                                        <button type="button" className={`btn join-item ${data.forma_pago === 'cheque_efectivo' ? 'btn-primary' : 'btn-outline'}`} onClick={() => setData('forma_pago', 'cheque_efectivo')}>Cheque / Efectivo</button>
                                    </div>
                                </FormField>

                                {transferencia && (
                                    <>
                                        <div className="grid grid-cols-2 gap-4">
                                            <FormField label="Banco" htmlFor="banco_id" error={errors.banco_id} required>
                                                <SearchSelect
                                                    options={bancos.map((b) => ({ value: String(b.id), label: b.es_pagador ? `${b.nombre} (pagador)` : b.nombre }))}
                                                    value={data.banco_id}
                                                    onValueChange={(value) => setData('banco_id', value)}
                                                    placeholder="Buscar banco..."
                                                />
                                            </FormField>
                                            <FormField label={esProveedorFormal ? 'Titular de la Cuenta' : 'Titular de la Cuenta (nombre)'} htmlFor="titular_cuenta" error={errors.titular_cuenta} required>
                                                <Input id="titular_cuenta" value={data.titular_cuenta} onChange={(e) => setData('titular_cuenta', e.target.value)} placeholder={esProveedorFormal ? 'Debe coincidir con la razón social' : 'Nombre del titular'} />
                                            </FormField>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            {esPagador ? (
                                                <FormField label={`Número de Cuenta (${digitos} dígitos)`} htmlFor="numero_cuenta" error={errors.numero_cuenta} required>
                                                    <Input
                                                        id="numero_cuenta"
                                                        inputMode="numeric"
                                                        maxLength={digitos}
                                                        value={data.numero_cuenta}
                                                        onChange={(e) => setData('numero_cuenta', e.target.value.replace(/\D/g, '').slice(0, digitos))}
                                                        placeholder={`${digitos} dígitos`}
                                                    />
                                                </FormField>
                                            ) : (
                                                <FormField label="CLABE (18 dígitos)" htmlFor="clabe" error={errors.clabe} required>
                                                    <Input
                                                        id="clabe"
                                                        inputMode="numeric"
                                                        maxLength={18}
                                                        value={data.clabe}
                                                        onChange={(e) => setData('clabe', e.target.value.replace(/\D/g, '').slice(0, 18))}
                                                        placeholder="18 dígitos"
                                                    />
                                                </FormField>
                                            )}
                                            <FormField label="Tarjeta (opcional)" htmlFor="tarjeta" error={errors.tarjeta}>
                                                <Input id="tarjeta" value={data.tarjeta} onChange={(e) => setData('tarjeta', e.target.value)} placeholder="Número de tarjeta" />
                                            </FormField>
                                        </div>
                                        <FormField label="Moneda" htmlFor="moneda_cuenta" error={errors.moneda_cuenta} required>
                                            <Select id="moneda_cuenta" value={data.moneda_cuenta} onValueChange={(value) => setData('moneda_cuenta', value)}>
                                                <option value="MXN">MXN</option>
                                                <option value="USD">USD</option>
                                                <option value="EUR">EUR</option>
                                            </Select>
                                        </FormField>
                                        <FormField label="Carátula Bancaria" htmlFor="caratula" error={errors.caratula} required>
                                            <DocumentoField id="caratula" archivo={data.caratula} onChange={(file) => setData('caratula', file)} />
                                        </FormField>
                                    </>
                                )}

                                <div className="divider" />
                                <h2 className="text-lg font-medium">Acceso al Portal</h2>
                                <FormField label={data.tiene_acceso_portal ? 'Email (usuario del portal)' : 'Email (opcional)'} htmlFor="email" error={errors.email} required={data.tiene_acceso_portal}>
                                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                </FormField>
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
