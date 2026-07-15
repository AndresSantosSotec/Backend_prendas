<?php

namespace Tests\Feature;

use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\CreditoPlanPago;
use App\Models\CreditoPrendario;
use App\Models\PlanInteresCategoria;
use App\Models\Sucursal;
use App\Services\MoraService;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoraProductoCrediticioTest extends TestCase
{
    use RefreshDatabase;

    private function crearSucursal(): Sucursal
    {
        return Sucursal::create([
            'codigo' => 'SUC-MORA',
            'nombre' => 'Sucursal Mora',
            'direccion' => 'Zona 1',
            'telefono' => '11111111',
            'activa' => true,
        ]);
    }

    private function crearCliente(): Cliente
    {
        return Cliente::create([
            'nombres' => 'Cliente',
            'apellidos' => 'Prueba',
            'dpi' => '1234567890101',
            'telefono' => '55550000',
            'direccion' => 'Ciudad',
            'estado' => 'activo',
            'fecha_nacimiento' => '1990-01-01',
            'genero' => 'masculino',
            'nit' => 'CF',
            'estado_civil' => 'soltero',
            'profesion' => 'Comerciante',
            'municipio' => 'Guatemala',
            'tipo_cliente' => 'regular',
        ]);
    }

    private function crearPlanMontoFijo(int $categoriaId, float $moraMontoFijo): PlanInteresCategoria
    {
        return PlanInteresCategoria::create([
            'categoria_producto_id' => $categoriaId,
            'nombre' => 'Plan Mora Fija',
            'codigo' => 'PLAN-MF-01',
            'tipo_periodo' => 'mensual',
            'plazo_numero' => 1,
            'plazo_unidad' => 'meses',
            'plazo_dias_total' => 30,
            'tasa_interes' => 15,
            'tasa_almacenaje' => 0,
            'tasa_moratorios' => 0,
            'tipo_mora' => 'monto_fijo',
            'mora_monto_fijo' => $moraMontoFijo,
            'porcentaje_prestamo' => 70,
            'activo' => true,
            'es_default' => true,
            'orden' => 1,
        ]);
    }

    public function test_recalcula_mora_desde_plan_cuando_credito_no_tiene_configuracion_de_monto_fijo(): void
    {
        $sucursal = $this->crearSucursal();
        $cliente = $this->crearCliente();

        $categoria = CategoriaProducto::create([
            'codigo' => 'CAT-MORA',
            'nombre' => 'Categoria Mora',
            'activa' => true,
        ]);

        $plan = $this->crearPlanMontoFijo($categoria->id, 5.00);

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-MORA-001',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursal->id,
            'plan_interes_id' => $plan->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-07-01',
            'fecha_aprobacion' => '2026-07-01',
            'fecha_desembolso' => '2026-07-01',
            'fecha_vencimiento' => '2026-07-13',
            'monto_solicitado' => 1800,
            'monto_aprobado' => 1800,
            'monto_desembolsado' => 1800,
            'capital_pendiente' => 1800,
            'capital_pagado' => 0,
            'interes_generado' => 0,
            'interes_pagado' => 0,
            'mora_generada' => 0,
            'mora_pagada' => 0,
            // Simula credito historico mal guardado: sin tasa ni monto fijo propio.
            'tipo_mora' => 'porcentaje',
            'tasa_mora' => 0,
            'mora_monto_fijo' => null,
            'tasa_interes' => 15,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'dias_gracia' => 0,
            'numero_cuotas' => 1,
            'monto_cuota' => 2070,
        ]);

        $cuota = CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-07-13',
            'estado' => 'vencida',
            'capital_proyectado' => 1800,
            'interes_proyectado' => 270,
            'mora_proyectada' => 0,
            'monto_cuota_proyectado' => 2070,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'mora_pagada' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 1800,
            'interes_pendiente' => 270,
            'mora_pendiente' => 0,
            'otros_cargos_pendientes' => 0,
            'monto_pendiente' => 2070,
        ]);

        $fechaCalculo = Carbon::parse('2026-07-14');

        app(MoraService::class)->recalcularMoraCredito($credito, $fechaCalculo);

        $cuota->refresh();
        $this->assertEquals(1, (int) $cuota->dias_mora);
        $this->assertEquals(5.0, (float) $cuota->mora_proyectada);
        $this->assertEquals(5.0, (float) $cuota->mora_pendiente);

        $calculo = app(PagoService::class)->calcularDeudaAlDia($credito->fresh(), $fechaCalculo);

        $this->assertEquals(5.0, (float) $calculo['mora_acumulada']);
        $this->assertTrue((bool) $calculo['en_mora']);
    }

    public function test_respeta_monto_fijo_del_credito_si_ya_esta_configurado(): void
    {
        $sucursal = $this->crearSucursal();
        $cliente = $this->crearCliente();

        $categoria = CategoriaProducto::create([
            'codigo' => 'CAT-MORA-2',
            'nombre' => 'Categoria Mora 2',
            'activa' => true,
        ]);

        $plan = $this->crearPlanMontoFijo($categoria->id, 5.00);

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-MORA-002',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursal->id,
            'plan_interes_id' => $plan->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-07-01',
            'fecha_aprobacion' => '2026-07-01',
            'fecha_desembolso' => '2026-07-01',
            'fecha_vencimiento' => '2026-07-13',
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 1000,
            'capital_pagado' => 0,
            'interes_generado' => 0,
            'interes_pagado' => 0,
            'mora_generada' => 0,
            'mora_pagada' => 0,
            // Debe prevalecer sobre el plan.
            'tipo_mora' => 'monto_fijo',
            'mora_monto_fijo' => 10,
            'tasa_mora' => 0,
            'tasa_interes' => 15,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'dias_gracia' => 0,
            'numero_cuotas' => 1,
            'monto_cuota' => 1150,
        ]);

        $cuota = CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-07-13',
            'estado' => 'vencida',
            'capital_proyectado' => 1000,
            'interes_proyectado' => 150,
            'mora_proyectada' => 0,
            'monto_cuota_proyectado' => 1150,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'mora_pagada' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 1000,
            'interes_pendiente' => 150,
            'mora_pendiente' => 0,
            'otros_cargos_pendientes' => 0,
            'monto_pendiente' => 1150,
        ]);

        $fechaCalculo = Carbon::parse('2026-07-14');

        app(MoraService::class)->recalcularMoraCredito($credito, $fechaCalculo);

        $cuota->refresh();
        $this->assertEquals(10.0, (float) $cuota->mora_proyectada);

        $calculo = app(PagoService::class)->calcularDeudaAlDia($credito->fresh(), $fechaCalculo);
        $this->assertEquals(10.0, (float) $calculo['mora_acumulada']);
    }

    public function test_dias_mora_se_mantienen_en_cero_mientras_hay_dias_de_gracia(): void
    {
        $sucursal = $this->crearSucursal();
        $cliente = $this->crearCliente();

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-MORA-003',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-07-01',
            'fecha_aprobacion' => '2026-07-01',
            'fecha_desembolso' => '2026-07-01',
            'fecha_vencimiento' => '2026-07-13',
            'monto_solicitado' => 1800,
            'monto_aprobado' => 1800,
            'monto_desembolsado' => 1800,
            'capital_pendiente' => 1800,
            'capital_pagado' => 0,
            'interes_generado' => 0,
            'interes_pagado' => 0,
            'mora_generada' => 0,
            'mora_pagada' => 0,
            'tipo_mora' => 'monto_fijo',
            'mora_monto_fijo' => 5,
            'tasa_mora' => 0,
            'tasa_interes' => 15,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'dias_gracia' => 3,
            'numero_cuotas' => 1,
            'monto_cuota' => 2170,
        ]);

        CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-07-13',
            'estado' => 'vencida',
            'capital_proyectado' => 1800,
            'interes_proyectado' => 270,
            'mora_proyectada' => 0,
            'otros_cargos_proyectados' => 100,
            'monto_cuota_proyectado' => 2170,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'mora_pagada' => 0,
            'otros_cargos_pagados' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 1800,
            'interes_pendiente' => 270,
            'mora_pendiente' => 0,
            'otros_cargos_pendientes' => 100,
            'monto_pendiente' => 2170,
            'dias_mora' => 0,
        ]);

        $calculo = app(PagoService::class)->calcularDeudaAlDia($credito->fresh(), Carbon::parse('2026-07-15'));

        $this->assertEquals(0.0, (float) $calculo['mora_acumulada']);
        $this->assertEquals(0, (int) $calculo['dias_mora']);
        $this->assertFalse((bool) $calculo['en_mora']);
    }
}
