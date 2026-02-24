<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ContactoStoreRequest;
use App\Http\Requests\Admin\Cob\ContactoUpdateRequest;
use App\Models\Cliente;
use App\Models\Cob\Contacto;
use Illuminate\Http\RedirectResponse;

class ContactoController extends Controller
{
    public function store(ContactoStoreRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->contactos()->create($request->validated());

        return back();
    }

    public function update(ContactoUpdateRequest $request, Cliente $cliente, Contacto $contacto): RedirectResponse
    {
        $contacto->update($request->validated());

        return back();
    }

    public function destroy(Cliente $cliente, Contacto $contacto): RedirectResponse
    {
        if ($cliente->contacto_principal_id === $contacto->id) {
            $cliente->update(['contacto_principal_id' => null]);
        }

        $contacto->delete();

        return back();
    }
}
