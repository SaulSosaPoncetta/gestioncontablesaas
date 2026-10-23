<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IndiceInflacion;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Índices de precios FACPCE para el ajuste por inflación (comunes a todas las empresas).
 */
class IndiceInflacionController extends Controller
{
    public function index()
    {
        $indices = IndiceInflacion::orderByDesc('periodo')->paginate(24);
        $ultimo = IndiceInflacion::orderByDesc('periodo')->first();

        return view('admin.indices.index', compact('indices', 'ultimo'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'periodo' => ['required', 'date_format:Y-m'],
            'indice'  => ['required', 'string', 'max:30'],
            'fuente'  => ['nullable', 'string', 'max:60'],
        ]);

        $indice = self::numero($datos['indice']);
        if ($indice === null || $indice <= 0) {
            return back()->withInput()->with('error', 'El índice tiene que ser un número mayor a cero.');
        }

        IndiceInflacion::updateOrCreate(
            ['periodo' => Carbon::createFromFormat('Y-m', $datos['periodo'])->startOfMonth()->toDateString()],
            ['indice' => $indice, 'fuente' => $datos['fuente'] ?: 'FACPCE']
        );

        return back()->with('success', "Índice de {$datos['periodo']} guardado.");
    }

    /**
     * Importación por texto o archivo CSV: una línea por mes, "periodo;indice".
     * Periodo: 2026-01, 01/2026, 01-2026 o 01/01/2026. Índice con coma o punto decimal.
     */
    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => ['nullable', 'file', 'mimes:csv,txt', 'max:1024'],
            'texto'   => ['nullable', 'string', 'max:100000'],
            'fuente'  => ['nullable', 'string', 'max:60'],
        ]);

        $contenido = $request->hasFile('archivo')
            ? file_get_contents($request->file('archivo')->getRealPath())
            : (string) $request->input('texto');

        if (trim($contenido) === '') {
            return back()->with('error', 'Pegue los índices o elija un archivo.');
        }

        $guardados = 0;
        $errores = [];

        foreach (preg_split('/\r\n|\r|\n/', $contenido) as $n => $linea) {
            $linea = trim($linea, " \t\xEF\xBB\xBF");
            if ($linea === '') {
                continue;
            }

            $partes = preg_split('/[;\t]/', $linea);
            if (count($partes) < 2) {
                $partes = explode(',', $linea, 2); // "2026-01,1234.56"
            }

            $periodo = self::periodo($partes[0] ?? '');
            $indice = self::numero($partes[1] ?? '');

            if (! $periodo || ! $indice || $indice <= 0) {
                if ($n > 0 || ! preg_match('/[a-z]/i', $linea)) {   // la primera línea puede ser el encabezado
                    $errores[] = 'Línea ' . ($n + 1) . ": «{$linea}»";
                }
                continue;
            }

            IndiceInflacion::updateOrCreate(
                ['periodo' => $periodo],
                ['indice' => $indice, 'fuente' => $request->input('fuente') ?: 'FACPCE']
            );
            $guardados++;
        }

        $mensaje = "Se guardaron {$guardados} índice(s).";
        if ($errores) {
            $mensaje .= ' No se pudieron leer: ' . implode(', ', array_slice($errores, 0, 10)) . (count($errores) > 10 ? '…' : '');
        }

        return back()->with($errores ? 'warning' : 'success', $mensaje);
    }

    public function destroy(IndiceInflacion $indice)
    {
        $indice->delete();

        return back()->with('success', 'Índice eliminado.');
    }

    /** "2026-01", "01/2026", "1-2026", "01/01/2026" → "2026-01-01" */
    private static function periodo(string $texto): ?string
    {
        $t = trim($texto, " \"'");

        foreach ([
            '/^(\d{4})[-\/](\d{1,2})$/'               => fn ($m) => [$m[1], $m[2]],
            '/^(\d{1,2})[-\/](\d{4})$/'               => fn ($m) => [$m[2], $m[1]],
            '/^\d{1,2}[-\/](\d{1,2})[-\/](\d{4})$/'   => fn ($m) => [$m[2], $m[1]],
            '/^(\d{4})-(\d{1,2})-\d{1,2}$/'           => fn ($m) => [$m[1], $m[2]],
        ] as $regex => $partes) {
            if (preg_match($regex, $t, $m)) {
                [$anio, $mes] = $partes($m);
                if ((int) $mes >= 1 && (int) $mes <= 12) {
                    return sprintf('%04d-%02d-01', $anio, $mes);
                }
            }
        }

        return null;
    }

    /** "1.234,567890" o "1234.56789" → float */
    private static function numero(string $texto): ?float
    {
        $t = trim($texto, " \"'");
        if ($t === '') {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace(['.', ','], ['', '.'], $t);
        }

        return is_numeric($t) ? (float) $t : null;
    }
}
