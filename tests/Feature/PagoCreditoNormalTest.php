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

class PagoCreditoNormalTest extends TestCase
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

        $this->seed(\Database\Seeders\TipoPolizaSeeder::class);
        $this->seed(\Database\Seeders\PlanCuentasSeeder::class);
        $this->seed(\Database\Seeders\ParametrizacionCuentasContablesSeeder::class);
    }

    public function test_pago_cuota_completa_con_cuota_vencida(): void
    {
        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-2001',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-05-01',
            'fecha_aprobacion' => '2026-05-01',
            'fecha_desembolso' => '2026-05-05',
            'fecha_vencimiento' => '2026-06-05',
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 1000,
            'capital_pagado' => 0,
            'interes_generado' => 100,
            'interes_pagado' => 0,
            'mora_generada' => 0,
            'mora_pagada' => 0,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'numero_cuotas' => 2,
            'monto_cuota' => 550,
        ]);

        // Cuota 1: Vencida
        $cuota1 = CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-06-05',
            'estado' => 'vencida',
            'capital_proyectado' => 500,
            'interes_proyectado' => 50,
            'monto_cuota_proyectado' => 550,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 500,
            'interes_pendiente' => 50,
            'mora_pendiente' => 0,
            'otros_cargos_pendientes' => 10,
            'monto_pendiente' => 560,
        ]);

        // Cuota 2: Pendiente
        $cuota2 = CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 2,
            'fecha_vencimiento' => '2026-07-05',
            'estado' => 'pendiente',
            'capital_proyectado' => 500,
            'interes_proyectado' => 50,
            'monto_cuota_proyectado' => 550,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 500,
            'interes_pendiente' => 50,
            'mora_pendiente' => 0,
            'otros_cargos_pendientes' => 0,
            'monto_pendiente' => 550,
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

        // Intentar pagar la cuota vencida completa (560)
        $response = $this->postJson("/api/v1/creditos-prendarios/{$credito->id}/pagos", [
            'tipo' => 'CUOTA',
            'monto' => 560,
            'metodo_pago' => 'efectivo',
            'idempotency_key' => 'da2a13cc-ee7f-4b0d-b8d4-d576a084bc60',
        ]);

        $response->assertStatus(200);

        // Verificar que la cuota vencida quedó pagada
        $cuota1->refresh();
        $this->assertEquals('pagada', $cuota1->estado);
        $this->assertEquals(0, $cuota1->capital_pendiente);
        $this->assertEquals(500, $cuota1->capital_pagado);

        // Verificar saldos del crédito
        $credito->refresh();
        $this->assertEquals(500, $credito->capital_pendiente);
        $this->assertEquals(500, $credito->capital_pagado);
    }

    public function test_pago_parcial_excluye_cuotas_renovadas(): void
    {
        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-2002',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-05-01',
            'fecha_aprobacion' => '2026-05-01',
            'fecha_desembolso' => '2026-05-05',
            'fecha_vencimiento' => '2026-06-05',
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 1000,
            'capital_pagado' => 0,
            'interes_generado' => 100,
            'interes_pagado' => 0,
            'mora_generada' => 0,
            'mora_pagada' => 0,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'numero_cuotas' => 2,
            'monto_cuota' => 550,
        ]);

        // Cuota 1: Renovada (su capital se movió al resto, no debe sumarse como pendiente)
        $cuota1 = CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-06-05',
            'estado' => 'renovada',
            'capital_proyectado' => 500,
            'interes_proyectado' => 50,
            'monto_cuota_proyectado' => 550,
            'capital_pagado' => 0,
            'interes_pagado' => 50,
            'monto_total_pagado' => 50,
            'capital_pendiente' => 0,
            'interes_pendiente' => 0,
            'mora_pendiente' => 0,
            'otros_cargos_pendientes' => 0,
            'monto_pendiente' => 0,
        ]);

        // Cuota 2: Pendiente
        $cuota2 = CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 2,
            'fecha_vencimiento' => '2026-07-05',
            'estado' => 'pendiente',
            'capital_proyectado' => 1000,
            'interes_proyectado' => 100,
            'monto_cuota_proyectado' => 1100,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 1000,
            'interes_pendiente' => 100,
            'mora_pendiente' => 0,
            'otros_cargos_pendientes' => 0,
            'monto_pendiente' => 1100,
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

        // Hacer un abono parcial de 200 (100 a interes, 100 a capital de la cuota 2)
        $response = $this->postJson("/api/v1/creditos-prendarios/{$credito->id}/pagos", [
            'tipo' => 'PARCIAL',
            'monto' => 200,
            'metodo_pago' => 'efectivo',
            'idempotency_key' => 'ec2b4f99-9cb1-4475-b6d3-242f360ef3c8',
        ]);

        $response->assertStatus(200);

        // Cuota 2 debe quedar como pagada_parcial
        $cuota2->refresh();
        $this->assertEquals('pagada_parcial', $cuota2->estado);
        $this->assertEquals(900, $cuota2->capital_pendiente); // 1000 - 100
        $this->assertEquals(0, $cuota2->interes_pendiente);   // 100 - 100

        // El crédito debe reflejar el abono a capital
        $credito->refresh();
        $this->assertEquals(900, $credito->capital_pendiente);
        $this->assertEquals(100, $credito->capital_pagado);
    }
}
