<?php

namespace Database\Seeders;

use App\Enums\ProveedorEstatus;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProveedoresSeeder extends Seeder
{
    /**
     * Alta de proveedores reales con acceso al portal. Idempotente por RFC.
     * tipo_persona se deriva del RFC (13 caracteres = física, 12 = moral).
     * La contraseña va en texto plano: el cast `hashed` del modelo la hashea.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private array $proveedores = [
        ['ACEROFORM DEL SURESTE', 'ASU1810138P5', 'CJ9NzN1z', 'claudia@aceroform.com.mx'],
        ['ARTICULOS Y MOTORES ELECTRICOS SA DE CV', 'AME8409102M3', '39wk&KG6', 'dayra.flores@grupo-ame.com'],
        ['ATMOGAS SA DE CV', 'ATM050721FX4', '6i#WM8Rq', 'gatoxulha@hotmail.com'],
        ['ARMIN JACOBO PAT AVILA', 'PAAA801026R71', '62o2!ZmD', 'contabilidad@isisureste.com.mx'],
        ['ABASTECEDORA LEAL', 'ALE890523D54', 'BzK#6Xag', 'yohanaaleman@abasleal.com'],
        ['BOLSAPAQ SA DE CV', 'BOL1101285L1', '%5Fnqi#N', 'administracion@pinturasrex.com'],
        ['CASA FERNANDEZ DEL STE SA DE CV', 'CFS8606014IA', '9K5yqd%f', 'katycasafdez@hotmail.com'],
        ['DELTA GAS DEL SURESTE, SA DE CV', 'DGS9404154E4', '5G$kYzvs', 'dgs.cartera@enerkom.com.mx'],
        ['DISTRIBUIDORA MAYORISTA DE OFICINAS', 'DMO940616SQ4', 'Zi&j$Dc2', 'cyc@dimosa1.mx'],
        ['DHE Y FIJACION', 'DFI220726474', 'CcjH38V!', 'distribherra@hotmail.com'],
        ['DISTRIBUIDORA MAYORISTA DE TORNILLOS DE YUC.', 'DMT841107DS9', '6Z5@aRS$', 'credito.industrial@dmt.com.mx'],
        ['FERRECABSA SA DE CV', 'FER8506034X7', 'r6AYU8#b', 'credito.slp@ferrecabsa.com'],
        ['FERRETERIA XAY-HA', 'FXA131029A70', '%%q5goUf', 'ferreteriaxayha@hotmail.com'],
        ['FORTACERO SA DE CV', 'FOR960524JP8', '6@4kf$Sb', 'luisa.guevara@fortacero.com'],
        ['GLOBALIZADORA', 'GPC130308QD7', 'Wjm&645A', 'cxc@lympieza.com'],
        ['INFRA DEL SUR SA DE CV', 'ISU820801FT2', '#nm8vBWB', 'mcasanova@infrasur.com.mx'],
        ['LA FERRE COMERCIALIZADORA', 'FCO0310234W3', 'NqEfn5V@', 'jmatos@laferre.lat'],
        ['ISATOOLS', 'IFP220505QT6', 'dAZ7M%5d', 'fsanjuan2@prodigy.net.mx'],
        ['MERCADO DE LA SOLDADURA', 'MSS080213BQ6', 'x@Sv!U6P', 'cobranza@mersolsureste.com.mx'],
        ['TERNIUM MEXICO SA DE CV', 'TME840710TR4', 'bn!UYDb6', 'dzenteno@ternium.com.mx'],
        ['PINTURAS E IMPERMEABILIZANTES RAMXA', 'PIR0203228J1', 'xv8G%5P3', 'merida_mayoreo@ramxa.com.mx'],
        ['INVENT', 'INV7310087E7', '8KFoA4S&', 'ventas03@inventsa.com'],
        ['ECODELI COMERCIAL', 'ECO061122F78', '$&Yit9HC', 'gloria.martinez@ecodeli.com'],
        ['SEGURIDAD INDUSTRIAL AMIGO', 'SIA9309071A5', 'jY&Yoz85', 'merida@amigosafety.com'],
        ['LAMINADORA MEXICANA DE METALES', 'LMM4108163E0', '#LbjZiC2', 'rosariof.flores@fefm.com.mx'],
        ['PLESA ANAHUAC Y CIAS', 'PAC040303UY6', 'VA!Vo3e!', 'aacosta@plesasteel.com'],
        ['PROVEEDORES INDUSTRIALES CHIMALHUACAN', 'PIC050818AX8', 'F2#064%f', 'diego.carino@pichabrasivos.com.mx'],
        ['PAZTI MAYA', 'PMA190904QH7', 'nA!Bo7e#', 'damaris@pazti.com'],
        ['LAURA JANET GOMEZ DIAZ', 'GODL920724B59', '4z&Yoz%%', 'ventas@esicoin.com.mx'],
        ['INDUSUR (DIEGO CERVANTES QUINTANAR)', 'CEQD060731IL1', '$AZd7m31', 'richii_heredia@hotmail.com'],
        ['PTB (TORNIYUC SA DE CV)', 'TOR1909129L4', '3g%kZzvt', 'construccion@ptb.mx'],
        ['CP AISLAMIENTOS TERMICOS', 'CAT961021LV8', '$Abjxi2c', 'ventasmid@cpaislamientostermicos.com.mx'],
        ['RAMON MANUEL RAMOS VARGUEZ', 'RAVR811110HK4', '5p!VYba#', 'extinguidoresdelgolfo@gmail.com'],
        ['ACEROS OCOTLAN SURESTE', 'AOS221012FU4', 'RsTr@p%@', 'ventas2.merida@acerosocotlan.mx'],
        ['CIA SHERWIN WILLIAMS', 'SWI5210141J5', 'Qn@uYFb1', 'merida_x8@sherwin.com.mx'],
        ['MAURICIO DEL CARMEN CRUZ JIMENEZ', 'CUJM740511KS1', 'jm&645AW', 'contabilidad@asmprotect.com.mx'],
        ['OSEL DEL SURESTE', 'OSU090128A96', 'Y1z&85jY', 'admon@oselsureste.com'],
        ['Compufax', 'COM910508749', 'N3zAJ9nR', 'cobranza@compufax.com.mx'],
        ['PAHUSA', 'PPI0208211U6', 'CJN3z9Nq', 'credito@pahusa.com'],
    ];

    public function run(): void
    {
        foreach ($this->proveedores as $i => [$razonSocial, $rfc, $password, $email]) {
            Proveedor::firstOrCreate(
                ['rfc' => $rfc],
                [
                    'codigo' => 'PRV-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'razon_social' => Str::upper(trim($razonSocial)),
                    'nombre_comercial' => Str::upper(trim($razonSocial)),
                    'email' => trim($email),
                    'password' => $password,
                    'tipo_persona' => strlen($rfc) === 13 ? 'fisica' : 'moral',
                    'tiene_acceso_portal' => true,
                    'activo' => true,
                    'estatus' => ProveedorEstatus::Activo->value,
                    'moneda_cuenta' => 'MXN',
                ],
            );
        }
    }
}
