<?php

namespace App\Http\Requests\Admin\Qal;

use App\Services\Qal\Dosier\ArbolDeSecciones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * El árbol de secciones completo, como lo manda el editor: cada nodo con su
 * título, su nota y sus hijas.
 *
 * Las reglas de Laravel no bajan por un árbol de profundidad variable, así que
 * cada nodo se revisa aquí recorriéndolo; el error dice el número de la
 * sección para que se encuentre en el editor.
 */
class ArbolDeSeccionesRequest extends FormRequest
{
    private const MAXIMO_DE_SECCIONES = 400;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'arbol' => ['present', 'array'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $arbol = $this->input('arbol');

            if (! is_array($arbol)) {
                return;
            }

            $errores = [];
            $total = 0;
            $this->revisar($arbol, '', 1, $errores, $total);

            if ($total > self::MAXIMO_DE_SECCIONES) {
                $errores[] = 'Son demasiadas secciones ('.$total.'); el máximo es '.self::MAXIMO_DE_SECCIONES.'.';
            }

            foreach (array_slice($errores, 0, 5) as $error) {
                $validator->errors()->add('arbol', $error);
            }
        }];
    }

    /**
     * @param  array<mixed>  $nodos
     * @param  list<string>  $errores
     */
    private function revisar(array $nodos, string $prefijo, int $nivel, array &$errores, int &$total): void
    {
        foreach (array_values($nodos) as $i => $nodo) {
            $numero = $prefijo === '' ? (string) ($i + 1) : "{$prefijo}.".($i + 1);
            $total++;

            if (! is_array($nodo)) {
                $errores[] = "La sección {$numero} no es válida.";

                continue;
            }

            $titulo = trim((string) ($nodo['titulo'] ?? ''));

            if ($titulo === '') {
                $errores[] = "La sección {$numero} no tiene título.";
            } elseif (mb_strlen($titulo) > 160) {
                $errores[] = "El título de la sección {$numero} pasa de 160 caracteres.";
            }

            if (mb_strlen((string) ($nodo['nota'] ?? '')) > 2000) {
                $errores[] = "La nota de la sección {$numero} pasa de 2000 caracteres.";
            }

            $hijos = $nodo['hijos'] ?? [];

            if (! is_array($hijos)) {
                $errores[] = "Las subsecciones de la {$numero} no son válidas.";

                continue;
            }

            if ($hijos !== [] && $nivel >= ArbolDeSecciones::PROFUNDIDAD_MAXIMA) {
                $errores[] = "La sección {$numero} ya está en el nivel más hondo (".ArbolDeSecciones::PROFUNDIDAD_MAXIMA.'): no admite subsecciones.';

                continue;
            }

            $this->revisar($hijos, $numero, $nivel + 1, $errores, $total);
        }
    }
}
