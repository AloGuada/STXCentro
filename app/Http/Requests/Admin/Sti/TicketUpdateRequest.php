<?php

namespace App\Http\Requests\Admin\Sti;

use App\Models\Sti\Status;
use Illuminate\Foundation\Http\FormRequest;

class TicketUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'nombre_solicitante' => ['required', 'string', 'max:255'],
            'comentario' => ['required', 'string'],
            'tecnico_id' => ['nullable', 'integer', 'exists:sti_tecnicos,id'],
            'equipo_id' => ['nullable', 'integer', 'exists:sti_equipos,id'],
            'departamento_id' => ['required', 'integer', 'exists:departamentos,id'],
            'status_id' => ['nullable', 'integer', 'exists:sti_status,id'],
            'firma_completado' => ['nullable', 'string'],
            'calificacion' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];

        if ($this->isStatusChanging()) {
            $rules['tecnico_id'] = ['required', 'integer', 'exists:sti_tecnicos,id'];
        }

        if ($this->isStatusCompletado()) {
            $ticket = $this->route('ticket');

            $rules['calificacion'] = ['required', 'integer', 'min:1', 'max:5'];

            if (! $ticket?->firma_completado) {
                $rules['firma_completado'] = ['required', 'string'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_solicitante.required' => 'El nombre del solicitante es obligatorio.',
            'comentario.required' => 'El comentario es obligatorio.',
            'departamento_id.required' => 'El departamento es obligatorio.',
            'departamento_id.exists' => 'El departamento seleccionado no existe.',
            'tecnico_id.required' => 'Debes asignar un técnico antes de cambiar el estado.',
            'calificacion.required' => 'La calificación es obligatoria cuando el estado es Completado.',
            'firma_completado.required' => 'La firma es obligatoria cuando el estado es Completado.',
        ];
    }

    private function isStatusChanging(): bool
    {
        if (! $this->status_id) {
            return false;
        }

        $ticket = $this->route('ticket');
        $currentStatusId = $ticket?->historial()->latest()->value('status_id');

        return (int) $this->status_id !== (int) $currentStatusId;
    }

    private function isStatusCompletado(): bool
    {
        if (! $this->status_id) {
            return false;
        }

        $status = Status::find($this->status_id);

        return $status && $status->orden >= 8;
    }
}
