<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

/**
 * Plan de cuentas modelo para empresas argentinas (estructura RT 8/9 FACPCE).
 * Se copia a cada empresa nueva y luego cada una lo adapta.
 * Formato: [codigo, nombre, imputable, monetaria]
 */
class PlanCuentasModelo
{
    public const PLAN = [
        ['1', 'ACTIVO', false, true],
        ['1.1', 'ACTIVO CORRIENTE', false, true],
        ['1.1.01', 'Caja y Bancos', false, true],
        ['1.1.01.001', 'Caja', true, true],
        ['1.1.01.002', 'Fondo Fijo', true, true],
        ['1.1.01.003', 'Banco Cuenta Corriente', true, true],
        ['1.1.01.004', 'Banco Caja de Ahorro', true, true],
        ['1.1.01.005', 'Valores a Depositar', true, true],
        ['1.1.02', 'Inversiones Temporarias', false, true],
        ['1.1.02.001', 'Plazos Fijos', true, true],
        ['1.1.02.002', 'Fondos Comunes de Inversión', true, false],
        ['1.1.03', 'Créditos por Ventas', false, true],
        ['1.1.03.001', 'Deudores por Ventas', true, true],
        ['1.1.03.002', 'Documentos a Cobrar', true, true],
        ['1.1.03.003', 'Tarjetas de Crédito a Cobrar', true, true],
        ['1.1.03.009', 'Previsión para Deudores Incobrables', true, true],
        ['1.1.04', 'Otros Créditos', false, true],
        ['1.1.04.001', 'IVA Crédito Fiscal', true, true],
        ['1.1.04.002', 'IVA Saldo a Favor Técnico', true, true],
        ['1.1.04.003', 'IVA Saldo a Favor Libre Disponibilidad', true, true],
        ['1.1.04.004', 'Percepciones de IVA Sufridas', true, true],
        ['1.1.04.005', 'Retenciones de IVA Sufridas', true, true],
        ['1.1.04.006', 'Retenciones de Ganancias Sufridas', true, true],
        ['1.1.04.007', 'Anticipos de Ganancias', true, true],
        ['1.1.04.008', 'Percepciones IIBB Sufridas', true, true],
        ['1.1.04.009', 'Retenciones IIBB Sufridas', true, true],
        ['1.1.04.010', 'Retenciones IIBB Bancarias (SIRCREB)', true, true],
        ['1.1.04.011', 'Anticipos IIBB', true, true],
        ['1.1.04.012', 'Impuesto a los Débitos y Créditos Bancarios a Cuenta', true, true],
        ['1.1.04.013', 'Anticipos a Proveedores', true, true],
        ['1.1.04.014', 'Gastos Pagados por Adelantado', true, false],
        ['1.1.05', 'Bienes de Cambio', false, false],
        ['1.1.05.001', 'Mercaderías', true, false],
        ['1.1.05.002', 'Materias Primas', true, false],
        ['1.1.05.003', 'Productos en Proceso', true, false],
        ['1.2', 'ACTIVO NO CORRIENTE', false, false],
        ['1.2.01', 'Bienes de Uso', false, false],
        ['1.2.01.001', 'Inmuebles', true, false],
        ['1.2.01.002', 'Rodados', true, false],
        ['1.2.01.003', 'Muebles y Útiles', true, false],
        ['1.2.01.004', 'Instalaciones', true, false],
        ['1.2.01.005', 'Equipos de Computación', true, false],
        ['1.2.01.006', 'Maquinarias', true, false],
        ['1.2.01.101', 'Amortización Acumulada Inmuebles', true, false],
        ['1.2.01.102', 'Amortización Acumulada Rodados', true, false],
        ['1.2.01.103', 'Amortización Acumulada Muebles y Útiles', true, false],
        ['1.2.01.104', 'Amortización Acumulada Instalaciones', true, false],
        ['1.2.01.105', 'Amortización Acumulada Equipos de Computación', true, false],
        ['1.2.01.106', 'Amortización Acumulada Maquinarias', true, false],
        ['1.2.02', 'Activos Intangibles', false, false],
        ['1.2.02.001', 'Software y Licencias', true, false],
        ['1.2.02.002', 'Marcas y Patentes', true, false],

        ['2', 'PASIVO', false, true],
        ['2.1', 'PASIVO CORRIENTE', false, true],
        ['2.1.01', 'Deudas Comerciales', false, true],
        ['2.1.01.001', 'Proveedores', true, true],
        ['2.1.01.002', 'Documentos a Pagar', true, true],
        ['2.1.01.003', 'Anticipos de Clientes', true, true],
        ['2.1.02', 'Deudas Bancarias y Financieras', false, true],
        ['2.1.02.001', 'Préstamos Bancarios', true, true],
        ['2.1.02.002', 'Tarjetas de Crédito a Pagar', true, true],
        ['2.1.03', 'Remuneraciones y Cargas Sociales', false, true],
        ['2.1.03.001', 'Sueldos a Pagar', true, true],
        ['2.1.03.002', 'Cargas Sociales a Pagar (F.931)', true, true],
        ['2.1.03.003', 'Sindicato a Pagar', true, true],
        ['2.1.03.004', 'Provisión SAC y Vacaciones', true, true],
        ['2.1.04', 'Deudas Fiscales', false, true],
        ['2.1.04.001', 'IVA Débito Fiscal', true, true],
        ['2.1.04.002', 'IVA a Pagar', true, true],
        ['2.1.04.003', 'Retenciones de IVA a Depositar', true, true],
        ['2.1.04.004', 'Percepciones de IVA a Depositar', true, true],
        ['2.1.04.005', 'Retenciones de Ganancias a Depositar', true, true],
        ['2.1.04.006', 'Impuesto a las Ganancias a Pagar', true, true],
        ['2.1.04.007', 'Ingresos Brutos a Pagar', true, true],
        ['2.1.04.008', 'Retenciones IIBB a Depositar', true, true],
        ['2.1.04.009', 'Percepciones IIBB a Depositar', true, true],
        ['2.1.04.010', 'Tasas Municipales a Pagar', true, true],
        ['2.1.04.011', 'Monotributo a Pagar', true, true],
        ['2.1.05', 'Otras Deudas', false, true],
        ['2.1.05.001', 'Acreedores Varios', true, true],
        ['2.1.05.002', 'Honorarios a Pagar', true, true],
        ['2.1.05.003', 'Dividendos a Pagar', true, true],
        ['2.2', 'PASIVO NO CORRIENTE', false, true],
        ['2.2.01', 'Deudas Bancarias a Largo Plazo', false, true],
        ['2.2.01.001', 'Préstamos Bancarios LP', true, true],
        ['2.2.02', 'Previsiones', false, true],
        ['2.2.02.001', 'Previsión para Juicios', true, true],

        ['3', 'PATRIMONIO NETO', false, false],
        ['3.1', 'APORTES DE LOS PROPIETARIOS', false, false],
        ['3.1.01', 'Capital', false, false],
        ['3.1.01.001', 'Capital Social', true, false],
        ['3.1.01.002', 'Ajuste de Capital', true, false],
        ['3.1.01.003', 'Aportes Irrevocables', true, false],
        ['3.2', 'RESULTADOS ACUMULADOS', false, false],
        ['3.2.01', 'Ganancias Reservadas', false, false],
        ['3.2.01.001', 'Reserva Legal', true, false],
        ['3.2.01.002', 'Reserva Facultativa', true, false],
        ['3.2.02', 'Resultados No Asignados', false, false],
        ['3.2.02.001', 'Resultados No Asignados', true, false],
        ['3.2.02.002', 'Resultado del Ejercicio', true, false],
        ['3.2.02.003', 'Ajuste de Resultados de Ejercicios Anteriores', true, false],

        ['4', 'RESULTADOS POSITIVOS', false, false],
        ['4.1', 'INGRESOS OPERATIVOS', false, false],
        ['4.1.01', 'Ventas', false, false],
        ['4.1.01.001', 'Ventas de Mercaderías', true, false],
        ['4.1.01.002', 'Ventas de Servicios', true, false],
        ['4.1.01.009', 'Descuentos y Bonificaciones Otorgadas', true, false],
        ['4.2', 'OTROS INGRESOS', false, false],
        ['4.2.01', 'Resultados Financieros', false, false],
        ['4.2.01.001', 'Intereses Ganados', true, false],
        ['4.2.01.002', 'Diferencias de Cambio Positivas', true, false],
        ['4.2.01.003', 'Resultado por Exposición a la Inflación (RECPAM)', true, false],
        ['4.2.02', 'Otros Ingresos', false, false],
        ['4.2.02.001', 'Descuentos Obtenidos', true, false],
        ['4.2.02.002', 'Resultado Venta Bienes de Uso', true, false],
        ['4.2.02.003', 'Ingresos Varios', true, false],

        ['5', 'RESULTADOS NEGATIVOS', false, false],
        ['5.1', 'COSTOS', false, false],
        ['5.1.01', 'Costo de Ventas', false, false],
        ['5.1.01.001', 'Costo de Mercaderías Vendidas', true, false],
        ['5.1.01.002', 'Costo de Servicios Prestados', true, false],
        ['5.2', 'GASTOS DE COMERCIALIZACIÓN', false, false],
        ['5.2.01', 'Gastos de Comercialización', false, false],
        ['5.2.01.001', 'Publicidad y Propaganda', true, false],
        ['5.2.01.002', 'Fletes y Acarreos', true, false],
        ['5.2.01.003', 'Comisiones sobre Ventas', true, false],
        ['5.2.01.004', 'Impuesto sobre los Ingresos Brutos', true, false],
        ['5.2.01.005', 'Deudores Incobrables', true, false],
        ['5.3', 'GASTOS DE ADMINISTRACIÓN', false, false],
        ['5.3.01', 'Gastos de Administración', false, false],
        ['5.3.01.001', 'Sueldos y Jornales', true, false],
        ['5.3.01.002', 'Cargas Sociales', true, false],
        ['5.3.01.003', 'Honorarios Profesionales', true, false],
        ['5.3.01.004', 'Alquileres', true, false],
        ['5.3.01.005', 'Servicios (Luz, Gas, Agua)', true, false],
        ['5.3.01.006', 'Teléfono e Internet', true, false],
        ['5.3.01.007', 'Papelería y Útiles', true, false],
        ['5.3.01.008', 'Seguros', true, false],
        ['5.3.01.009', 'Mantenimiento y Reparaciones', true, false],
        ['5.3.01.010', 'Amortizaciones Bienes de Uso', true, false],
        ['5.3.01.011', 'Tasas Municipales', true, false],
        ['5.3.01.012', 'Gastos Varios', true, false],
        ['5.4', 'GASTOS FINANCIEROS', false, false],
        ['5.4.01', 'Gastos Financieros', false, false],
        ['5.4.01.001', 'Intereses Pagados', true, false],
        ['5.4.01.002', 'Gastos y Comisiones Bancarias', true, false],
        ['5.4.01.003', 'Impuesto a los Débitos y Créditos Bancarios', true, false],
        ['5.4.01.004', 'Diferencias de Cambio Negativas', true, false],
        ['5.5', 'IMPUESTO A LAS GANANCIAS', false, false],
        ['5.5.01', 'Impuesto a las Ganancias', false, false],
        ['5.5.01.001', 'Impuesto a las Ganancias del Ejercicio', true, false],
    ];

    /** Copia el plan modelo a la empresa. Devuelve la cantidad de cuentas creadas. */
    public static function aplicar(Empresa $empresa): int
    {
        return DB::transaction(function () use ($empresa) {
            $ids = [];

            foreach (self::PLAN as [$codigo, $nombre, $imputable, $monetaria]) {
                [$tipo, $saldo] = Cuenta::tipoPorCodigo($codigo);
                $partes = explode('.', $codigo);
                $padreCodigo = count($partes) > 1 ? implode('.', array_slice($partes, 0, -1)) : null;

                $cuenta = Cuenta::withoutGlobalScope('empresa')->create([
                    'empresa_id'     => $empresa->id,
                    'padre_id'       => $padreCodigo ? ($ids[$padreCodigo] ?? null) : null,
                    'codigo'         => $codigo,
                    'nombre'         => $nombre,
                    'nivel'          => count($partes),
                    'tipo'           => $tipo,
                    'imputable'      => $imputable,
                    'saldo_habitual' => str_ends_with($nombre, 'Acumulada') || str_starts_with($nombre, 'Previsión para Deudores')
                        ? 'acreedor'   // regularizadoras del activo
                        : (str_starts_with($codigo, '4.1.01.009') ? 'deudor' : $saldo),
                    'monetaria'      => $monetaria,
                    'ajustable'      => ! $monetaria,
                ]);

                $ids[$codigo] = $cuenta->id;
            }

            return count($ids);
        });
    }
}
