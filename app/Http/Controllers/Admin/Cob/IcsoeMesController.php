<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\IcsoeMesesRequest;
use App\Models\Cob\IcsoeMes;
use App\Models\Cob\IcsoeSeguimiento;
use App\Services\Cob\IcsoeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class IcsoeMesController extends Controller
{
    public function __construct(private readonly IcsoeService $icsoe) {}

    /**
     * Captura mensual por lote: la tabla manda todos los renglones de una vez y
     * los totales se recalculan una sola vez al final.
     */
    public function update(IcsoeMesesRequest $request, IcsoeSeguimiento $seguimiento): RedirectResponse
    {
        DB::transaction(function () use ($request, $seguimiento) {
            foreach ($request->validated()['meses'] as $fila) {
                IcsoeMes::query()
                    ->where('seguimiento_id', $seguimiento->id)
                    ->whereKey($fila['id'])
                    ->update([
                        'dias_cotizados' => $fila['dias_cotizados'],
                        'sbc_aplicado' => $fila['sbc_aplicado'],
                    ]);
            }

            $this->icsoe->recalcularTotales($seguimiento);
        });

        return back()->with('success', 'Días cotizados guardados.');
    }
}
