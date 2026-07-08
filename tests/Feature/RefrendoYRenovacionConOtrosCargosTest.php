<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\CreditoPrendario;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\CajaAperturaCierre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefrendoYRenovacionConOtrosCargosTest extends TestCase
{
    use RefreshDatabase;

    private $sucursal;
    private $user;
    private $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'codigo' => 'SUC-01',
            'nombre' => 'Sucursal Centro',
            'direccion' => 'Zona 1',
            'telefono' => '11111111',
            'activa' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin.test@example.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->cliente = Cliente::create([
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'dpi' => '1234567890101',
            'telefono' => '55550000',
            'direccion' => 'Ciudad',
            'estado' => 'activo',
            'fecha_nacimiento' => '1990-01-01',
            'genero' => 'masculino',
            'nit' => 'CF',
            'estado_civil' => 'soltero',
            'profesion' => 'Estudiante',
            'municipio' => 'Guatemala',
            'tipo_cliente' => 'regular',
        ]);
    }

    public function test_calcular_montos_refrendo_incluye_otros_cargos(): void
    {
        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-1001',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-06-01',
            'fecha_aprobacion' => '2026-06-01',
            'fecha_desembolso' => '2026-06-05',
            'fecha_vencimiento' => '2026-07-05',
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 1000,
            'capital_pagado' => 0,
            'interes_generado' => 100,
            'interes_pagado' => 0,
            'mora_generada' => 20,
            'mora_pagada' => 0,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'numero_cuotas' => 1,
            'monto_cuota' => 1100,
        ]);

        CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-07-05',
            'estado' => 'vencida',
            'capital_proyectado' => 1000,
            'interes_proyectado' => 100,
            'monto_cuota_proyectado' => 1100,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 1000,
            'interes_pendiente' => 100,
            'mora_pendiente' => 20,
            'otros_cargos_pendientes' => 50,
            'monto_pendiente' => 1170,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson("/api/v1/creditos-prendarios/{$credito->id}/refrendos/calcular", [
            'tipo_refrendo' => 'parcial',
            'abono_capital' => 0,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.interes_adeudado', 100);
        $response->assertJsonPath('data.mora_adeudada', 20);
        $response->assertJsonPath('data.otros_adeudados', 50);
        $response->assertJsonPath('data.monto_minimo', 170); // 100 (interes) + 20 (mora) + 50 (otros)
    }

    public function test_procesar_refrendo_actualiza_plan_de_pagos(): void
    {
        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-1002',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-06-01',
            'fecha_aprobacion' => '2026-06-01',
            'fecha_desembolso' => '2026-06-05',
            'fecha_vencimiento' => '2026-07-05',
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 1000,
            'capital_pagado' => 0,
            'interes_generado' => 100,
            'interes_pagado' => 0,
            'mora_generada' => 20,
            'mora_pagada' => 0,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'numero_cuotas' => 1,
            'monto_cuota' => 1100,
        ]);

        CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-07-05',
            'estado' => 'vencida',
            'capital_proyectado' => 1000,
            'interes_proyectado' => 100,
            'monto_cuota_proyectado' => 1100,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 1000,
            'interes_pendiente' => 100,
            'mora_pendiente' => 20,
            'otros_cargos_pendientes' => 50,
            'monto_pendiente' => 1170,
        ]);

        CajaAperturaCierre::create([
            'sucursal_id' => $this->sucursal->id,
            'cajero_id' => $this->user->id,
            'user_id' => $this->user->id,
            'fecha_apertura' => now(),
            'hora_apertura' => '08:00:00',
            'saldo_inicial' => 5000,
            'saldo_actual' => 5000,
            'estado' => 'abierta',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson("/api/v1/creditos-prendarios/{$credito->id}/refrendos", [
            'tipo_refrendo' => 'parcial',
            'monto_pagado' => 170,
            'metodo_pago' => 'efectivo',
        ]);

        $response->dd();
        $response->assertStatus(201);

        // Verificar cuota original marcada como renovada
        $cuota1 = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 1)->first();
        $this->assertEquals('renovada', $cuota1->estado);
        $this->assertEquals(0, $cuota1->capital_pendiente);
        $this->assertEquals(0, $cuota1->interes_pendiente);
        $this->assertEquals(0, $cuota1->mora_pendiente);
        $this->assertEquals(0, $cuota1->otros_cargos_pendientes);
        $this->assertEquals(100, $cuota1->interes_pagado);
        $this->assertEquals(20, $cuota1->mora_pagada);
        $this->assertEquals(50, $cuota1->otros_cargos_pagados);

        // Verificar nueva cuota creada
        $cuota2 = CreditoPlanPago::where('credito_prendario_id', $credito->id)->where('numero_cuota', 2)->first();
        $this->assertNotNull($cuota2);
        $this->assertEquals('pendiente', $cuota2->estado);
        $this->assertEquals(1000, $cuota2->capital_pendiente);
        $this->assertEquals(100, $cuota2->interes_pendiente);
        $this->assertEquals(0, $cuota2->mora_pendiente);
        $this->assertEquals(0, $cuota2->otros_cargos_pendientes);
    }
}
