<?php

namespace App\Support;

/**
 * Tablas de referencia de ARCA (ex AFIP) usadas en comprobantes y en el Libro IVA Digital.
 */
class Afip
{
    /** código => [abreviatura, descripción, letra, clase (factura|debito|credito)] */
    public const TIPOS_COMPROBANTE = [
        1   => ['FA',    'Factura A',                  'A', 'factura'],
        2   => ['NDA',   'Nota de Débito A',           'A', 'debito'],
        3   => ['NCA',   'Nota de Crédito A',          'A', 'credito'],
        6   => ['FB',    'Factura B',                  'B', 'factura'],
        7   => ['NDB',   'Nota de Débito B',           'B', 'debito'],
        8   => ['NCB',   'Nota de Crédito B',          'B', 'credito'],
        11  => ['FC',    'Factura C',                  'C', 'factura'],
        12  => ['NDC',   'Nota de Débito C',           'C', 'debito'],
        13  => ['NCC',   'Nota de Crédito C',          'C', 'credito'],
        51  => ['FM',    'Factura M',                  'M', 'factura'],
        52  => ['NDM',   'Nota de Débito M',           'M', 'debito'],
        53  => ['NCM',   'Nota de Crédito M',          'M', 'credito'],
        81  => ['TFA',   'Tique Factura A',            'A', 'factura'],
        82  => ['TFB',   'Tique Factura B',            'B', 'factura'],
        83  => ['TQ',    'Tique',                      'B', 'factura'],
        201 => ['FCEA',  'Factura de Crédito Electrónica MiPyME A', 'A', 'factura'],
        202 => ['NDFCEA','Nota de Débito FCE MiPyME A',  'A', 'debito'],
        203 => ['NCFCEA','Nota de Crédito FCE MiPyME A', 'A', 'credito'],
        206 => ['FCEB',  'Factura de Crédito Electrónica MiPyME B', 'B', 'factura'],
        207 => ['NDFCEB','Nota de Débito FCE MiPyME B',  'B', 'debito'],
        208 => ['NCFCEB','Nota de Crédito FCE MiPyME B', 'B', 'credito'],
        211 => ['FCEC',  'Factura de Crédito Electrónica MiPyME C', 'C', 'factura'],
        212 => ['NDFCEC','Nota de Débito FCE MiPyME C',  'C', 'debito'],
        213 => ['NCFCEC','Nota de Crédito FCE MiPyME C', 'C', 'credito'],
    ];

    /** código => porcentaje */
    public const ALICUOTAS = [
        5 => 21.0,
        4 => 10.5,
        6 => 27.0,
        8 => 5.0,
        9 => 2.5,
        3 => 0.0,
    ];

    public const CONDICIONES_IVA = [
        1  => 'IVA Responsable Inscripto',
        6  => 'Responsable Monotributo',
        13 => 'Monotributista Social',
        4  => 'IVA Sujeto Exento',
        5  => 'Consumidor Final',
        7  => 'Sujeto No Categorizado',
        8  => 'Proveedor del Exterior',
        9  => 'Cliente del Exterior',
        10 => 'IVA Liberado – Ley 19.640',
        15 => 'IVA No Alcanzado',
    ];

    public const TIPOS_DOCUMENTO = [
        80 => 'CUIT',
        86 => 'CUIL',
        96 => 'DNI',
        94 => 'Pasaporte',
        99 => 'Sin identificar (Consumidor Final)',
    ];

    /** IVA Simple – tipo de operación de las ventas */
    public const OPERACIONES_VENTA = [
        1 => 'Cosas muebles, obras, locaciones y servicios',
        2 => 'Venta de bienes de uso',
    ];

    /** IVA Simple – concepto de las compras (crédito fiscal) */
    public const CONCEPTOS_COMPRA = [
        1 => 'Compra de bienes (excepto bienes de uso)',
        2 => 'Locaciones',
        3 => 'Prestaciones de servicios',
        4 => 'Inversiones en bienes de uso',
    ];

    /** IVA Simple – tipo de sujeto comprador: 1 RI · 2 Monotributo · 3 CF, exento y no alcanzado */
    public static function tipoSujeto(int $condicionIva): int
    {
        return match ($condicionIva) {
            1       => 1,
            6, 13   => 2,
            default => 3,
        };
    }

    public static function abreviatura(int $tipo): string
    {
        return self::TIPOS_COMPROBANTE[$tipo][0] ?? (string) $tipo;
    }

    public static function descripcion(int $tipo): string
    {
        return self::TIPOS_COMPROBANTE[$tipo][1] ?? "Tipo {$tipo}";
    }

    public static function letra(int $tipo): string
    {
        return self::TIPOS_COMPROBANTE[$tipo][2] ?? '';
    }

    public static function esNotaCredito(int $tipo): bool
    {
        return (self::TIPOS_COMPROBANTE[$tipo][3] ?? '') === 'credito';
    }

    /**
     * ¿El comprobante discrimina IVA?
     *  - Ventas: A, B y M informan IVA (en B va incluido en el precio, pero se declara); C no.
     *  - Compras: solo A y M generan crédito fiscal; B y C se registran por el total.
     */
    public static function discriminaIva(int $tipo, string $circuito): bool
    {
        $letra = self::letra($tipo);
        return $circuito === 'venta' ? in_array($letra, ['A', 'B', 'M'], true) : in_array($letra, ['A', 'M'], true);
    }

    /** Tipos permitidos según el circuito y la condición de IVA de la empresa */
    public static function tiposPara(string $circuito, string $condicionEmpresa): array
    {
        $tipos = self::TIPOS_COMPROBANTE;

        if ($circuito === 'venta') {
            $letras = $condicionEmpresa === 'responsable_inscripto' ? ['A', 'B', 'M'] : ['C'];
            $tipos = array_filter($tipos, fn ($t) => in_array($t[2], $letras, true));
        }

        return array_map(fn ($t) => "{$t[0]} - {$t[1]}", $tipos);
    }

    public static function numeroFormateado(int $pv, int $numero): string
    {
        return str_pad((string) $pv, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }
}
