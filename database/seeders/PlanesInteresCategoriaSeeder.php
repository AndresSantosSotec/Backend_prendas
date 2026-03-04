<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlanInteresCategoria;
use App\Models\CategoriaProducto;
use Illuminate\Support\Facades\DB;

class PlanesInteresCategoriaSeeder extends Seeder
{
    /**
     * Seed de planes de interés por categoría
     * Similar a la configuración de PrendaFlex
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->command->info('🔧 Creando planes de interés por categoría...');

            // Obtener categorías existentes
            $categorias = CategoriaProducto::all();

            if ($categorias->isEmpty()) {
                $this->command->warn('⚠️  No hay categorías creadas. Crea categorías primero.');
                return;
            }

            // Para cada categoría, crear planes similares a PrendaFlex
            foreach ($categorias as $categoria) {
                $this->crearPlanesPorCategoria($categoria);
            }

            DB::commit();

            $this->command->info('✅ Planes de interés creados exitosamente');
            $this->mostrarResumen();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error al crear planes: ' . $e->getMessage());
        }
    }

    /**
     * Crear planes para una categoría específica
     */
    private function crearPlanesPorCategoria(CategoriaProducto $categoria): void
    {
        $this->command->info("  📁 Creando planes para: {$categoria->nombre}");

        // Definir planes según tipo de categoría
        $planes = $this->obtenerPlanesPorTipo($categoria);

        foreach ($planes as $index => $planData) {
            PlanInteresCategoria::create(array_merge($planData, [
                'categoria_producto_id' => $categoria->id,
                'orden' => $index + 1,
            ]));
        }
    }

    /**
     * Obtener configuración de planes según el tipo de categoría
     */
    private function obtenerPlanesPorTipo(CategoriaProducto $categoria): array
    {
        $nombreCategoria = strtolower($categoria->nombre);

        // Planes para METALES (oro, plata, joyas)
        if (str_contains($nombreCategoria, 'metal') ||
            str_contains($nombreCategoria, 'oro') ||
            str_contains($nombreCategoria, 'joya')) {
            return $this->planesMetales();
        }

        // Planes para ELECTRÓNICOS
        if (str_contains($nombreCategoria, 'electr') ||
            str_contains($nombreCategoria, 'celular') ||
            str_contains($nombreCategoria, 'laptop')) {
            return $this->planesElectronicos();
        }

        // Planes para VEHÍCULOS
        if (str_contains($nombreCategoria, 'vehiculo') ||
            str_contains($nombreCategoria, 'moto') ||
            str_contains($nombreCategoria, 'carro')) {
            return $this->planesVehiculos();
        }

        // Planes para INMUEBLES
        if (str_contains($nombreCategoria, 'inmueble') ||
            str_contains($nombreCategoria, 'terreno') ||
            str_contains($nombreCategoria, 'casa')) {
            return $this->planesInmuebles();
        }

        // Planes genéricos por defecto
        return $this->planesGenericos();
    }

    /**
     * Planes para categoría METALES (alto valor)
     */
    private function planesMetales(): array
    {
        return [
            [
                'nombre' => 'Plan Semanal Corto',
                'codigo' => 'S04S',
                'descripcion' => 'Plan de 4 semanas para metales preciosos',
                'tipo_periodo' => 'semanal',
                'plazo_numero' => 4,
                'plazo_unidad' => 'semanas',
                'tasa_interes' => 1.875,
                'tasa_almacenaje' => 0.625,
                'tasa_moratorios' => 0.357,
                'porcentaje_prestamo' => 54.00,
                'dias_gracia' => 0,
                'dias_enajenacion' => 14,
                'cat' => 240.60,
                'interes_anual' => 96.43,
                'porcentaje_precio_venta' => 10.00,
                'numero_refrendos_permitidos' => 2,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => true,
            ],
            [
                'nombre' => 'Plan Semanal Medio',
                'codigo' => 'S08S',
                'descripcion' => 'Plan de 8 semanas para metales preciosos',
                'tipo_periodo' => 'semanal',
                'plazo_numero' => 8,
                'plazo_unidad' => 'semanas',
                'tasa_interes' => 1.875,
                'tasa_almacenaje' => 0.625,
                'tasa_moratorios' => 0.357,
                'porcentaje_prestamo' => 52.50,
                'dias_gracia' => 0,
                'dias_enajenacion' => 14,
                'cat' => 240.60,
                'interes_anual' => 96.43,
                'porcentaje_precio_venta' => 10.00,
                'numero_refrendos_permitidos' => 2,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => false,
            ],
            [
                'nombre' => 'Plan Semanal Largo',
                'codigo' => 'S12S',
                'descripcion' => 'Plan de 12 semanas para metales preciosos',
                'tipo_periodo' => 'semanal',
                'plazo_numero' => 12,
                'plazo_unidad' => 'semanas',
                'tasa_interes' => 1.875,
                'tasa_almacenaje' => 0.625,
                'tasa_moratorios' => 0.357,
                'porcentaje_prestamo' => 50.00,
                'dias_gracia' => 2,
                'dias_enajenacion' => 14,
                'cat' => 240.60,
                'interes_anual' => 96.43,
                'porcentaje_precio_venta' => 10.00,
                'numero_refrendos_permitidos' => 2,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => false,
            ],
        ];
    }

    /**
     * Planes para categoría ELECTRÓNICOS (valor medio)
     */
    private function planesElectronicos(): array
    {
        return [
            [
                'nombre' => 'Plan Semanal 4 semanas',
                'codigo' => 'S04S',
                'descripcion' => 'Plan de 4 semanas para electrónicos',
                'tipo_periodo' => 'semanal',
                'plazo_numero' => 4,
                'plazo_unidad' => 'semanas',
                'tasa_interes' => 2.50,
                'tasa_almacenaje' => 0.50,
                'tasa_moratorios' => 0.50,
                'porcentaje_prestamo' => 45.00,
                'monto_minimo' => 500.00,
                'monto_maximo' => 50000.00,
                'dias_gracia' => 0,
                'dias_enajenacion' => 10,
                'cat' => 312.00,
                'interes_anual' => 120.00,
                'porcentaje_precio_venta' => 15.00,
                'numero_refrendos_permitidos' => 3,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => true,
            ],
            [
                'nombre' => 'Plan Quincenal 2 quincenas',
                'codigo' => 'Q02Q',
                'descripcion' => 'Plan de 2 quincenas para electrónicos',
                'tipo_periodo' => 'quincenal',
                'plazo_numero' => 2,
                'plazo_unidad' => 'quincenas',
                'tasa_interes' => 5.00,
                'tasa_almacenaje' => 1.00,
                'tasa_moratorios' => 0.50,
                'porcentaje_prestamo' => 50.00,
                'monto_minimo' => 500.00,
                'monto_maximo' => 50000.00,
                'dias_gracia' => 0,
                'dias_enajenacion' => 10,
                'cat' => 312.00,
                'interes_anual' => 120.00,
                'porcentaje_precio_venta' => 15.00,
                'numero_refrendos_permitidos' => 2,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => false,
            ],
        ];
    }

    /**
     * Planes para categoría VEHÍCULOS (alto valor, plazos largos)
     */
    private function planesVehiculos(): array
    {
        return [
            [
                'nombre' => 'Plan Mensual 1 mes',
                'codigo' => 'M01M',
                'descripcion' => 'Plan de 1 mes para vehículos',
                'tipo_periodo' => 'mensual',
                'plazo_numero' => 1,
                'plazo_unidad' => 'meses',
                'tasa_interes' => 8.00,
                'tasa_almacenaje' => 2.00,
                'tasa_moratorios' => 0.25,
                'porcentaje_prestamo' => 60.00,
                'monto_minimo' => 10000.00,
                'monto_maximo' => 500000.00,
                'dias_gracia' => 3,
                'dias_enajenacion' => 30,
                'cat' => 240.00,
                'interes_anual' => 120.00,
                'porcentaje_precio_venta' => 20.00,
                'numero_refrendos_permitidos' => 6,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => true,
            ],
            [
                'nombre' => 'Plan Mensual 3 meses',
                'codigo' => 'M03M',
                'descripcion' => 'Plan de 3 meses para vehículos',
                'tipo_periodo' => 'mensual',
                'plazo_numero' => 3,
                'plazo_unidad' => 'meses',
                'tasa_interes' => 7.50,
                'tasa_almacenaje' => 1.50,
                'tasa_moratorios' => 0.25,
                'porcentaje_prestamo' => 65.00,
                'monto_minimo' => 10000.00,
                'monto_maximo' => 500000.00,
                'dias_gracia' => 3,
                'dias_enajenacion' => 30,
                'cat' => 216.00,
                'interes_anual' => 108.00,
                'porcentaje_precio_venta' => 20.00,
                'numero_refrendos_permitidos' => 4,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => false,
            ],
        ];
    }

    /**
     * Planes para categoría INMUEBLES (muy alto valor, plazos largos)
     */
    private function planesInmuebles(): array
    {
        return [
            [
                'nombre' => 'Plan Mensual 3 meses',
                'codigo' => 'M03M',
                'descripcion' => 'Plan de 3 meses para inmuebles',
                'tipo_periodo' => 'mensual',
                'plazo_numero' => 3,
                'plazo_unidad' => 'meses',
                'tasa_interes' => 5.00,
                'tasa_almacenaje' => 0.00,
                'tasa_moratorios' => 0.15,
                'porcentaje_prestamo' => 70.00,
                'monto_minimo' => 50000.00,
                'monto_maximo' => null,
                'dias_gracia' => 5,
                'dias_enajenacion' => 60,
                'cat' => 180.00,
                'interes_anual' => 60.00,
                'porcentaje_precio_venta' => 25.00,
                'numero_refrendos_permitidos' => null,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => true,
            ],
            [
                'nombre' => 'Plan Mensual 6 meses',
                'codigo' => 'M06M',
                'descripcion' => 'Plan de 6 meses para inmuebles',
                'tipo_periodo' => 'mensual',
                'plazo_numero' => 6,
                'plazo_unidad' => 'meses',
                'tasa_interes' => 4.50,
                'tasa_almacenaje' => 0.00,
                'tasa_moratorios' => 0.15,
                'porcentaje_prestamo' => 75.00,
                'monto_minimo' => 50000.00,
                'monto_maximo' => null,
                'dias_gracia' => 5,
                'dias_enajenacion' => 60,
                'cat' => 162.00,
                'interes_anual' => 54.00,
                'porcentaje_precio_venta' => 25.00,
                'numero_refrendos_permitidos' => null,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => false,
            ],
        ];
    }

    /**
     * Planes genéricos para otras categorías
     */
    private function planesGenericos(): array
    {
        return [
            [
                'nombre' => 'Plan Semanal',
                'codigo' => 'S04S',
                'descripcion' => 'Plan estándar semanal',
                'tipo_periodo' => 'semanal',
                'plazo_numero' => 4,
                'plazo_unidad' => 'semanas',
                'tasa_interes' => 2.00,
                'tasa_almacenaje' => 0.50,
                'tasa_moratorios' => 0.40,
                'porcentaje_prestamo' => 50.00,
                'dias_gracia' => 0,
                'dias_enajenacion' => 14,
                'cat' => 260.00,
                'interes_anual' => 100.00,
                'porcentaje_precio_venta' => 12.00,
                'numero_refrendos_permitidos' => 3,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => true,
            ],
            [
                'nombre' => 'Plan Mensual',
                'codigo' => 'M01M',
                'descripcion' => 'Plan estándar mensual',
                'tipo_periodo' => 'mensual',
                'plazo_numero' => 1,
                'plazo_unidad' => 'meses',
                'tasa_interes' => 8.00,
                'tasa_almacenaje' => 1.50,
                'tasa_moratorios' => 0.30,
                'porcentaje_prestamo' => 55.00,
                'dias_gracia' => 2,
                'dias_enajenacion' => 15,
                'cat' => 228.00,
                'interes_anual' => 95.00,
                'porcentaje_precio_venta' => 12.00,
                'numero_refrendos_permitidos' => 4,
                'permite_refrendos' => true,
                'activo' => true,
                'es_default' => false,
            ],
        ];
    }

    /**
     * Mostrar resumen de planes creados
     */
    private function mostrarResumen(): void
    {
        $this->command->newLine();
        $this->command->info('📊 RESUMEN DE PLANES CREADOS:');
        $this->command->newLine();

        $categorias = CategoriaProducto::with('planesInteres')->get();

        foreach ($categorias as $categoria) {
            $planesCount = $categoria->planesInteres->count();
            $planDefault = $categoria->planesInteres->where('es_default', true)->first();

            $this->command->line("  📁 {$categoria->nombre}:");
            $this->command->line("     • Total planes: {$planesCount}");

            if ($planDefault) {
                $this->command->line("     • Plan default: {$planDefault->nombre}");
            }

            $this->command->newLine();
        }

        $totalPlanes = PlanInteresCategoria::count();
        $this->command->info("✅ Total de planes creados: {$totalPlanes}");
    }
}
