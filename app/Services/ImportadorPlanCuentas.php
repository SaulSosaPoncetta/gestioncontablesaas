<?php

namespace App\Services;

use App\Models\Cuenta;
use Illuminate\Support\Facades\DB;

/**
 * Importa / actualiza el plan de cuentas desde un CSV.
 *
 * Columnas (con encabezado): codigo ; nombre ; imputable ; saldo_habitual ; monetaria
 *  - separador ; o , (se detecta solo)
 *  - imputable / monetaria: S/N, SI/NO, 1/0
 *  - saldo_habitual: D/A, deudor/acreedor (opcional: si falta se toma del rubro)
 * Las cuentas existentes (mismo código) se actualizan; las nuevas se crean.
 * La cuenta padre se deduce del código y debe existir o venir antes en el archivo.
 */
class ImportadorPlanCuentas
{
    public array $errores = [];
    public int $creadas = 0;
    public int $actualizadas = 0;

    public function importar(string $rutaArchivo): bool
    {
        $filas = $this->leerCsv($rutaArchivo);

        if (empty($filas)) {
            $this->errores[] = 'El archivo está vacío o no tiene el formato esperado.';
            return false;
        }

        // Ordena por código natural para que los padres se procesen antes que las hijas
        usort($filas, fn ($a, $b) => $this->claveOrden($a['codigo']) <=> $this->claveOrden($b['codigo']));

        DB::beginTransaction();

        try {
            $existentes = Cuenta::get()->keyBy('codigo');

            foreach ($filas as $fila) {
                $this->procesarFila($fila, $existentes);
            }

            if ($this->errores) {
                DB::rollBack();
                $this->creadas = $this->actualizadas = 0;
                return false;
            }

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->errores[] = 'Error inesperado: ' . $e->getMessage();
            return false;
        }
    }

    private function procesarFila(array $fila, $existentes): void
    {
        $linea = $fila['_linea'];
        $codigo = $fila['codigo'];

        if (! preg_match(Cuenta::REGEX_CODIGO, $codigo)) {
            $this->errores[] = "Línea {$linea}: código «{$codigo}» inválido.";
            return;
        }
        if ($fila['nombre'] === '') {
            $this->errores[] = "Línea {$linea}: falta el nombre de la cuenta {$codigo}.";
            return;
        }

        $partes = explode('.', $codigo);
        $codigoPadre = count($partes) > 1 ? implode('.', array_slice($partes, 0, -1)) : null;
        $padre = $codigoPadre ? $existentes->get($codigoPadre) : null;

        if ($codigoPadre && ! $padre) {
            $this->errores[] = "Línea {$linea}: no existe la cuenta padre {$codigoPadre} para {$codigo}.";
            return;
        }

        [$tipo, $saldoRubro] = Cuenta::tipoPorCodigo($codigo);
        $imputable = $this->booleano($fila['imputable'] ?? '', false);

        $datos = [
            'padre_id'       => $padre?->id,
            'nombre'         => mb_substr($fila['nombre'], 0, 255),
            'nivel'          => count($partes),
            'tipo'           => $tipo,
            'imputable'      => $imputable,
            'saldo_habitual' => $this->saldo($fila['saldo_habitual'] ?? '') ?? $saldoRubro,
            'monetaria'      => $this->booleano($fila['monetaria'] ?? '', true),
        ];
        $datos['ajustable'] = ! $datos['monetaria'];

        if ($cuenta = $existentes->get($codigo)) {
            if ($cuenta->imputable && ! $imputable && $cuenta->tieneMovimientos()) {
                $this->errores[] = "Línea {$linea}: la cuenta {$codigo} tiene movimientos y no puede dejar de ser imputable.";
                return;
            }
            $cuenta->update($datos);
            $this->actualizadas++;
        } else {
            $cuenta = Cuenta::create($datos + ['codigo' => $codigo, 'activa' => true]);
            $existentes->put($codigo, $cuenta);
            $this->creadas++;
        }

        // Si el padre era imputable, deja de serlo (tiene una subcuenta)
        if ($padre && $padre->imputable) {
            if ($padre->tieneMovimientos()) {
                $this->errores[] = "Línea {$linea}: la cuenta padre {$padre->codigo} es imputable y tiene movimientos; no admite subcuentas.";
                return;
            }
            $padre->update(['imputable' => false]);
        }
    }

    private function leerCsv(string $ruta): array
    {
        $contenido = file_get_contents($ruta);
        if ($contenido === false || trim($contenido) === '') {
            return [];
        }

        // Quita BOM y convierte a UTF-8 si viene en ANSI (Excel en Windows)
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        $separador = substr_count($lineas[0], ';') >= substr_count($lineas[0], ',') ? ';' : ',';

        $encabezado = array_map(fn ($h) => mb_strtolower(trim($h)), str_getcsv(array_shift($lineas), $separador));
        $encabezado = array_map(fn ($h) => str_replace(['código', ' '], ['codigo', '_'], $h), $encabezado);

        if (! in_array('codigo', $encabezado, true) || ! in_array('nombre', $encabezado, true)) {
            $this->errores[] = 'El archivo debe tener encabezado con al menos las columnas "codigo" y "nombre".';
            return [];
        }

        $filas = [];
        foreach ($lineas as $i => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $valores = str_getcsv($linea, $separador);
            $fila = [];
            foreach ($encabezado as $j => $col) {
                $fila[$col] = trim($valores[$j] ?? '');
            }
            $fila['_linea'] = $i + 2;
            $filas[] = $fila;
        }

        return $filas;
    }

    private function claveOrden(string $codigo): string
    {
        return implode('.', array_map(fn ($p) => str_pad($p, 5, '0', STR_PAD_LEFT), explode('.', $codigo)));
    }

    private function booleano(string $valor, bool $porDefecto): bool
    {
        $v = mb_strtolower(trim($valor));
        if ($v === '') {
            return $porDefecto;
        }
        return in_array($v, ['s', 'si', 'sí', '1', 'true', 'x', 'y', 'yes'], true);
    }

    private function saldo(string $valor): ?string
    {
        $v = mb_strtolower(trim($valor));
        return match (true) {
            $v === '' => null,
            str_starts_with($v, 'd') => 'deudor',
            str_starts_with($v, 'a') => 'acreedor',
            default => null,
        };
    }
}
