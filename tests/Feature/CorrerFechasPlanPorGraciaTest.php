<?php

namespace Tests\Feature;

use App\Models\CajaAperturaCierre;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\CreditoPlanPago;
use App\Models\CreditoPrendario;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrerFechasPlanPorGraciaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;
    private User $user;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'codigo' => 'SUC-CFG',
            'nombre' => 'Sucursal Gracia',
            'direccion' => 'Zona 1',
            'telefono' => '22222222',
            'activa' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cajero Gracia',
            'username' => 'cajero_gracia',
            'email' => 'cajero.gracia@example.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->cliente = Cliente::create([
            'nombres' => 'Pedro',
            'apellidos' => 'Ramirez',
            'dpi' => '1234567890199',
            'telefono' => '55551111',
            'direccion' => 'Ciudad',
            'estado' => 'activo',
            'fecha_nacimiento' => '1985-01-01',
            'genero' => 'masculino',
            'nit' => 'CF',
            'estado_civil' => 'soltero',
            'profesion' => 'Comerciante',
            'municipio' => 'Guatemala',
            'tipo_cliente' => 'regular',
        ]);

        CajaAperturaCierre::create([
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->user->id,
            'fecha_apertura' => now()->toDateString(),
            'hora_apertura' => '08:00:00',
            'saldo_inicial' => 10000,
            'estado' => 'abierta',
        ]);

        $this->actingAs($this->user);
        Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearCreditoConTresCuotas(): CreditoPrendario
    {
        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-GRACIA-001',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-04-10',
            'fecha_desembolso' => '2026-04-10',
            'fecha_vencimiento' => '2026-07-10',
            'monto_solicitado' => 3000,
            'monto_aprobado' => 3000,
            'monto_desembolsado' => 3000,
            'capital_pendiente' => 3000,
            'capital_pagado' => 0,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 90,
            'dias_gracia' => 3,
            'numero_cuotas' => 3,
            'monto_cuota' => 1100,
        ]);

        foreach ([1, 2, 3] as $n) {
            CreditoPlanPago::create([
                'credito_prendario_id' => $credito->id,
                'numero_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-0' . (4 + $n) . '-10')->format('Y-m-d'),
                'estado' => 'pendiente',
                'capital_proyectado' => 1000,
                'interes_proyectado' => 100,
                'mora_proyectada' => 0,
                'monto_cuota_proyectado' => 1100,
                'capital_pagado' => 0,
                'interes_pagado' => 0,
                'mora_pagada' => 0,
                'monto_total_pagado' => 0,
                'capital_pendiente' => 1000,
                'interes_pendiente' => 100,
                'mora_pendiente' => 0,
                'otros_cargos_pendientes' => 0,
                'monto_pendiente' => 1100,
            ]);
        }

        // Primera cuota vencida el 10/05; hoy es 17/07 — forzamos estado
        $primera = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 1)->first();
        $primera->update([
            'fecha_vencimiento' => '2026-07-10',
            'estado' => 'vencida',
        ]);
        CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 2)
            ->update(['fecha_vencimiento' => '2026-08-10']);
        CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 3)
            ->update(['fecha_vencimiento' => '2026-09-10']);

        return $credito->fresh();
    }

    public function test_apagado_no_mueve_fechas_del_plan(): void
    {
        ConfiguracionSistema::updateOrCreate(
            ['clave' => 'correr_fechas_plan_por_gracia'],
            ['valor' => 'false', 'tipo' => 'boolean', 'grupo' => 'creditos', 'editable_por_usuario' => true]
        );

        $credito = $this->crearCreditoConTresCuotas();

        app(PagoService::class)->ejecutarPago([
            'credito_id' => $credito->id,
            'tipo' => 'CUOTA',
            'monto' => 5000,
            'metodo_pago' => 'efectivo',
            'idempotency_key' => 'gracia-off-1',
        ]);

        $c2 = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 2)->first();
        $c3 = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 3)->first();

        $this->assertEquals('2026-08-10', $c2->fecha_vencimiento->format('Y-m-d'));
        $this->assertEquals('2026-09-10', $c3->fecha_vencimiento->format('Y-m-d'));
    }

    public function test_activado_recalcula_cuotas_pendientes_desde_fecha_pago(): void
    {
        ConfiguracionSistema::updateOrCreate(
            ['clave' => 'correr_fechas_plan_por_gracia'],
            ['valor' => 'true', 'tipo' => 'boolean', 'grupo' => 'creditos', 'editable_por_usuario' => true]
        );

        $credito = $this->crearCreditoConTresCuotas();

        app(PagoService::class)->ejecutarPago([
            'credito_id' => $credito->id,
            'tipo' => 'CUOTA',
            'monto' => 5000,
            'metodo_pago' => 'efectivo',
            'idempotency_key' => 'gracia-on-1',
        ]);

        $c1 = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 1)->first();
        $c2 = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 2)->first();
        $c3 = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 3)->first();

        $this->assertEquals('pagada', $c1->estado);
        // Pago el 17/07; periodo mensual 30 días → 16/08 y 15/09
        $this->assertEquals('2026-08-16', $c2->fecha_vencimiento->format('Y-m-d'));
        $this->assertEquals('2026-09-15', $c3->fecha_vencimiento->format('Y-m-d'));
        $this->assertEquals('2026-09-15', $credito->fresh()->fecha_vencimiento->format('Y-m-d'));
    }
}
