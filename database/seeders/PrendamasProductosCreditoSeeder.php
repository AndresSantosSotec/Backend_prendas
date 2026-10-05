<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CategoriaProducto;
use App\Models\PlanInteresCategoria;
use App\Models\ParametrizacionMora;
use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrendamasProductosCreditoSeeder extends Seeder
{
    /**
     * Parametrización oficial de productos de crédito y categorías para PRENDAMÁS+.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // 1. DEFINICIÓN Y CONFIGURACIÓN DE LAS 6 CATEGORÍAS DE PRENDAS
            $categoriasDefinicion = [
                'CAT-001' => [
                    'nombre' => 'Joyería',
                    'descripcion' => 'Prendas de oro, plata y pedrería fina',
                    'icono' => 'gem',
                    'color' => '#f59e0b',
                    'orden' => 1,
                ],
                'CAT-002' => [
                    'nombre' => 'Electrónicos',
                    'descripcion' => 'Teléfonos celulares, computadoras, tablets, consolas y accesorios',
                    'icono' => 'device-mobile',
                    'color' => '#3b82f6',
                    'orden' => 2,
                ],
                'CAT-004' => [
                    'nombre' => 'Electrodomésticos',
                    'descripcion' => 'Refrigeradoras, lavadoras, estufas, microondas, televisores y aparatos del hogar',
                    'icono' => 'television',
                    'color' => '#10b981',
                    'orden' => 3,
                ],
                'CAT-005' => [
                    'nombre' => 'Vehículos (Motos / Carros)',
                    'descripcion' => 'Motocicletas, automóviles, camionetas y vehículos automotores',
                    'icono' => 'car',
                    'color' => '#ef4444',
                    'orden' => 4,
                ],
                'CAT-006' => [
                    'nombre' => 'Muebles',
                    'descripcion' => 'Comedores, salas, roperos y mobiliario del hogar u oficina',
                    'icono' => 'couch',
                    'color' => '#8b5cf6',
                    'orden' => 5,
                ],
                'CAT-007' => [
                    'nombre' => 'Ropa Típica',
                    'descripcion' => 'Cortes, güipiles, fajas y textiles tradicionales de valor comercial',
                    'icono' => 't-shirt',
                    'color' => '#ec4899',
                    'orden' => 6,
                ],
            ];

            $categoriasIds = [];

            foreach ($categoriasDefinicion as $codigo => $datos) {
                $cat = CategoriaProducto::withTrashed()->where('codigo', $codigo)->first();
                if (!$cat) {
                    $cat = CategoriaProducto::withTrashed()
                        ->where('nombre', 'like', "%{$datos['nombre']}%")
                        ->first();
                }

                $camposUpdate = [
                    'codigo'                       => $codigo,
                    'nombre'                       => $datos['nombre'],
                    'descripcion'                  => $datos['descripcion'],
                    'icono'                        => $datos['icono'],
                    'color'                        => $datos['color'],
                    'orden'                        => $datos['orden'],
                    'activa'                       => true,
                    // Parámetros por defecto Prendamas
                    'tasa_interes_default'         => 15.05,
                    'tasa_mora_default'            => 0.572,
                    'tipo_mora_default'            => 'monto_fijo',
                    'mora_monto_fijo_default'      => 10.01,
                    'plazo_maximo_dias'            => 30,
                    'porcentaje_prestamo_maximo'   => 70.00,
                    'afecta_interes_mensual'       => true,
                    'permite_pago_capital_diferente'=> true,
                    'requiere_pago_capital_refrendo'=> false,
                    'deleted_at'                   => null,
                ];

                if ($cat) {
                    $cat->update($camposUpdate);
                } else {
                    $cat = CategoriaProducto::create($camposUpdate);
                }

                $categoriasIds[] = $cat->id;
            }

            // 2. CONFIGURACIÓN DE MORA Y DÍAS DE GRACIA (SI EXISTE TABLA PARAMETRIZACION_MORA)
            if (\Illuminate\Support\Facades\Schema::hasTable('parametrizacion_mora')) {
                $parametrizacionesMora = ParametrizacionMora::all();
                if ($parametrizacionesMora->isEmpty()) {
                    ParametrizacionMora::create([
                        'sucursal_id'             => null,
                        'lunes'                   => true,
                        'martes'                  => true,
                        'miercoles'               => true,
                        'jueves'                  => true,
                        'viernes'                 => true,
                        'sabado'                  => false,
                        'domingo'                 => true,
                        'max_dias_mora'           => null,
                        'aplicar_tope_mora'       => false,
                        'dias_tope_mora'          => null,
                        'aplicar_mora_completa'   => false,
                        'dias_para_mora_completa' => 8, // Días para enajenación de la prenda
                        'dias_gracia'             => 4, // 4 días de gracia antes de cobrar mora
                        'frecuencia_defecto'      => 'mensual',
                        'tasa_interes_defecto'    => 15.05,
                        'apartado_habilitado'     => true,
                        'activo'                  => true,
                        'notas'                   => 'Parametrización oficial Prendamás: 4 días de gracia, mora Q10.01 fija por día sin tope, enajenación a los 8 días.',
                    ]);
                } else {
                    foreach ($parametrizacionesMora as $pm) {
                        $pm->update([
                            'dias_gracia'             => 4,
                            'aplicar_tope_mora'       => false,
                            'dias_tope_mora'          => null,
                            'dias_para_mora_completa' => 8,
                            'frecuencia_defecto'      => 'mensual',
                            'tasa_interes_defecto'    => 15.05,
                            'activo'                  => true,
                        ]);
                    }
                }
            }

            // 3. PRODUCTOS DE CRÉDITO PRENDAMÁS+
            // Definición de las 3 modalidades con sus tasas y plazos oficiales
            $productos = [
                [
                    'buscar_codigos'       => ['PM-MENSUAL', 'M01M3'],
                    'codigo'               => 'PM-MENSUAL',
                    'nombre'               => 'Plan Mensual PRENDAMÁS+',
                    'descripcion'          => 'Crédito prendario mensual al 15.05% de interés. Mora fija de Q10.01 por día tras 4 días de gracia. Préstamo hasta el 70% del avalúo (artículo nuevo) o 40-50% según estado físico. Enajenación a los 8 días de atraso.',
                    'tipo_periodo'         => 'mensual',
                    'plazo_numero'         => 1,
                    'plazo_unidad'         => 'meses',
                    'plazo_dias_total'     => 30,
                    'tasa_interes'         => 15.05,
                    'tasa_almacenaje'      => 0.00,
                    'tasa_moratorios'      => 0.572,
                    'tipo_mora'            => 'monto_fijo',
                    'mora_monto_fijo'      => 10.01,
                    'porcentaje_prestamo'  => 70.00,
                    'monto_minimo'         => 100.00,
                    'monto_maximo'         => 20000.00,
                    'dias_gracia'          => 4,
                    'dias_enajenacion'     => 8,
                    'cat'                  => 180.60,
                    'interes_anual'        => 180.60,
                    'permite_refrendos'    => true,
                    'numero_refrendos_permitidos' => 6,
                    'activo'               => true,
                    'es_default'           => true,
                    'orden'                => 1,
                ],
                [
                    'buscar_codigos'       => ['PM-QUINCENAL', 'Q02Q3'],
                    'codigo'               => 'PM-QUINCENAL',
                    'nombre'               => 'Plan Quincenal PRENDAMÁS+',
                    'descripcion'          => 'Crédito prendario quincenal al 7.64% de interés. Mora fija de Q10.01 por día tras 4 días de gracia. Préstamo hasta el 70% del avalúo (artículo nuevo) o 40-50% según estado físico. Enajenación a los 8 días de atraso.',
                    'tipo_periodo'         => 'quincenal',
                    'plazo_numero'         => 1,
                    'plazo_unidad'         => 'quincenas',
                    'plazo_dias_total'     => 15,
                    'tasa_interes'         => 7.64,
                    'tasa_almacenaje'      => 0.00,
                    'tasa_moratorios'      => 0.572,
                    'tipo_mora'            => 'monto_fijo',
                    'mora_monto_fijo'      => 10.01,
                    'porcentaje_prestamo'  => 70.00,
                    'monto_minimo'         => 100.00,
                    'monto_maximo'         => 20000.00,
                    'dias_gracia'          => 4,
                    'dias_enajenacion'     => 8,
                    'cat'                  => 183.36,
                    'interes_anual'        => 183.36,
                    'permite_refrendos'    => true,
                    'numero_refrendos_permitidos' => 6,
                    'activo'               => true,
                    'es_default'           => false,
                    'orden'                => 2,
                ],
                [
                    'buscar_codigos'       => ['PM-SEMANAL', 'S04S4'],
                    'codigo'               => 'PM-SEMANAL',
                    'nombre'               => 'Plan Semanal PRENDAMÁS+',
                    'descripcion'          => 'Crédito prendario semanal al 4.03% de interés. Mora fija de Q10.01 por día tras 4 días de gracia. Préstamo hasta el 70% del avalúo (artículo nuevo) o 40-50% según estado físico. Enajenación a los 8 días de atraso.',
                    'tipo_periodo'         => 'semanal',
                    'plazo_numero'         => 1,
                    'plazo_unidad'         => 'semanas',
                    'plazo_dias_total'     => 7,
                    'tasa_interes'         => 4.03,
                    'tasa_almacenaje'      => 0.00,
                    'tasa_moratorios'      => 0.572,
                    'tipo_mora'            => 'monto_fijo',
                    'mora_monto_fijo'      => 10.01,
                    'porcentaje_prestamo'  => 70.00,
                    'monto_minimo'         => 100.00,
                    'monto_maximo'         => 20000.00,
                    'dias_gracia'          => 4,
                    'dias_enajenacion'     => 8,
                    'cat'                  => 209.56,
                    'interes_anual'        => 209.56,
                    'permite_refrendos'    => true,
                    'numero_refrendos_permitidos' => 6,
                    'activo'               => true,
                    'es_default'           => false,
                    'orden'                => 3,
                ],
            ];

            // Desactivar es_default de planes antiguos para evitar colisiones
            PlanInteresCategoria::whereNotIn('codigo', ['PM-MENSUAL'])->update(['es_default' => false]);

            foreach ($productos as $prodData) {
                $buscarCodigos = $prodData['buscar_codigos'];
                unset($prodData['buscar_codigos']);

                // Buscar plan existente por sus códigos anteriores o actuales
                $plan = PlanInteresCategoria::withTrashed()
                    ->whereIn('codigo', $buscarCodigos)
                    ->first();

                if (!$plan) {
                    $plan = PlanInteresCategoria::withTrashed()
                        ->where('nombre', 'like', "%{$prodData['tipo_periodo']}%")
                        ->where('nombre', 'not like', '%apartado%')
                        ->first();
                }

                if ($plan) {
                    $plan->restore();
                    $plan->update($prodData);
                } else {
                    $plan = PlanInteresCategoria::create($prodData);
                }

                // Sincronizar categorías en la tabla pivote plan_interes_categorias
                // Para el Plan Mensual, marcarlo como default en cada una de las 6 categorías
                $syncData = [];
                $esDefaultPlan = (bool) $prodData['es_default'];

                if ($esDefaultPlan) {
                    DB::table('plan_interes_categorias')
                        ->whereIn('categoria_id', $categoriasIds)
                        ->where('plan_id', '!=', $plan->id)
                        ->update(['es_default' => false]);
                }

                foreach ($categoriasIds as $catId) {
                    $syncData[$catId] = [
                        'es_default' => $esDefaultPlan,
                        'orden'      => $prodData['orden'],
                    ];
                }

                $plan->categorias()->sync($syncData);
                $plan->update(['categoria_producto_id' => $categoriasIds[0] ?? null]);
            }

            // 4. GUARDAR LÍMITES Y PARÁMETROS EN CONFIGURACIONES_SISTEMA
            ConfiguracionSistema::updateOrCreate(
                ['clave' => 'credito_monto_minimo'],
                [
                    'valor' => '100.00',
                    'tipo' => 'decimal',
                    'grupo' => 'creditos',
                    'descripcion' => 'Monto mínimo de crédito prendario permitido',
                    'editable_por_usuario' => true,
                ]
            );

            ConfiguracionSistema::updateOrCreate(
                ['clave' => 'credito_monto_maximo'],
                [
                    'valor' => '20000.00',
                    'tipo' => 'decimal',
                    'grupo' => 'creditos',
                    'descripcion' => 'Monto máximo de crédito prendario permitido sin autorización especial',
                    'editable_por_usuario' => true,
                ]
            );

            ConfiguracionSistema::updateOrCreate(
                ['clave' => 'credito_monto_requiere_autorizacion'],
                [
                    'valor' => '20000.00',
                    'tipo' => 'decimal',
                    'grupo' => 'creditos',
                    'descripcion' => 'Montos superiores a este valor requieren código de autorización del Gerente de Agencia',
                    'editable_por_usuario' => true,
                ]
            );

            ConfiguracionSistema::updateOrCreate(
                ['clave' => 'credito_mora_monto_fijo_diario'],
                [
                    'valor' => '10.01',
                    'tipo' => 'decimal',
                    'grupo' => 'creditos',
                    'descripcion' => 'Valor fijo de mora por día de atraso tras superar los días de gracia',
                    'editable_por_usuario' => true,
                ]
            );

            ConfiguracionSistema::updateOrCreate(
                ['clave' => 'credito_dias_gracia'],
                [
                    'valor' => '4',
                    'tipo' => 'integer',
                    'grupo' => 'creditos',
                    'descripcion' => 'Días de gracia antes de comenzar a generar mora',
                    'editable_por_usuario' => true,
                ]
            );

            ConfiguracionSistema::updateOrCreate(
                ['clave' => 'credito_dias_enajenacion'],
                [
                    'valor' => '8',
                    'tipo' => 'integer',
                    'grupo' => 'creditos',
                    'descripcion' => 'Días de atraso tras los cuales se inicia el proceso de enajenación / venta de la prenda',
                    'editable_por_usuario' => true,
                ]
            );

            DB::commit();

            $this->command?->info("Parametrización de productos de crédito PRENDAMÁS+ completada exitosamente.");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error en PrendamasProductosCreditoSeeder: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
