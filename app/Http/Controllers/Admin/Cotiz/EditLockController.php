<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\Tarjeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EditLockController extends Controller
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const LOCKABLE_TYPES = [
        'generadora' => Generadora::class,
        'tarjeta' => Tarjeta::class,
    ];

    public function lock(Request $request, string $type, int $id): JsonResponse
    {
        $entidad = $this->resolveEntity($type, $id);
        $userId = $request->user()->id;

        if (! $entidad->lock($userId)) {
            $entidad->loadMissing('lockedBy:id,name');

            return response()->json([
                'message' => 'El registro está siendo editado por otro usuario.',
                'locked_by' => $entidad->lockedBy?->only(['id', 'name']),
                'locked_at' => $entidad->locked_at?->toIso8601String(),
            ], Response::HTTP_LOCKED);
        }

        return response()->json([
            'locked_by' => $entidad->locked_by,
            'locked_at' => $entidad->locked_at?->toIso8601String(),
            'updated_at' => $entidad->updated_at?->toIso8601String(),
        ]);
    }

    public function unlock(Request $request, string $type, int $id): JsonResponse
    {
        $entidad = $this->resolveEntity($type, $id);
        $user = $request->user();

        if ($user->hasAnyRole(['admin-cotiz', 'super-admin'])) {
            $entidad->unlock();
        } else {
            $entidad->unlock($user->id);
        }

        return response()->json(['released' => true]);
    }

    private function resolveEntity(string $type, int $id): Model
    {
        $class = self::LOCKABLE_TYPES[$type]
            ?? abort(Response::HTTP_NOT_FOUND, "Tipo de entidad '{$type}' no es bloqueable.");

        return $class::findOrFail($id);
    }
}
