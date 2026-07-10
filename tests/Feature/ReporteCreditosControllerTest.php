<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\CreditoPrendario;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteCreditosControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporte_creditos_vigentes_respeta_fecha_corte_y_excluye_pagos_posteriores(): void
    {
        $sucursal = Sucursal::create([
            'codigo' => 'SUC-01',
            'nombre' => 'Sucursal Centro',
            'direccion' => 'Zona 1',
            'telefono' => '11111111',
            'activa' => true,
        ]);

        $user = User::create([
            'name' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin.reportes@example.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $sucursal->id,
        ]);

        $cliente = Cliente::create([
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

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-1001',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-06-01',
            'fecha_aprobacion' => '2026-06-01',
            'fecha_desembolso' => '2026-06-05',
            'fecha_vencimiento' => '2026-07-05',
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 200,
            'capital_pagado' => 800,
            'interes_generado' => 120,
            'interes_pagado' => 80,
            'tasa_interes' => 12,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'numero_cuotas' => 2,
            'monto_cuota' => 560,
        ]);

        CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-06-20',
            'estado' => 'pagada',
            'capital_proyectado' => 500,
            'interes_proyectado' => 60,
            'monto_cuota_proyectado' => 560,
            'capital_pagado' => 500,
            'interes_pagado' => 60,
            'monto_total_pagado' => 560,
            'capital_pendiente' => 0,
            'interes_pendiente' => 0,
            'monto_pendiente' => 0,
        ]);

        CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 2,
            'fecha_vencimiento' => '2026-07-20',
            'estado' => 'pendiente',
            'capital_proyectado' => 500,
            'interes_proyectado' => 60,
            'monto_cuota_proyectado' => 560,
            'capital_pagado' => 0,
            'interes_pagado' => 0,
            'monto_total_pagado' => 0,
            'capital_pendiente' => 500,
            'interes_pendiente' => 60,
            'monto_pendiente' => 560,
        ]);

        CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'usuario_id' => $user->id,
            'sucursal_id' => $sucursal->id,
            'numero_movimiento' => 'MOV-1',
            'tipo_movimiento' => 'pago',
            'fecha_movimiento' => '2026-06-20',
            'fecha_registro' => '2026-06-20 10:00:00',
            'monto_total' => 560,
            'capital' => 500,
            'interes' => 60,
            'mora' => 0,
            'otros_cargos' => 0,
            'saldo_capital' => 500,
            'saldo_interes' => 0,
            'saldo_mora' => 0,
            'forma_pago' => 'efectivo',
            'concepto' => 'Pago cuota 1',
            'estado' => 'activo',
            'moneda' => 'GTQ',
            'tipo_cambio' => 1,
        ]);

        CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'usuario_id' => $user->id,
            'sucursal_id' => $sucursal->id,
            'numero_movimiento' => 'MOV-2',
            'tipo_movimiento' => 'pago',
            'fecha_movimiento' => '2026-07-03',
            'fecha_registro' => '2026-07-03 10:00:00',
            'monto_total' => 560,
            'capital' => 500,
            'interes' => 60,
            'mora' => 0,
            'otros_cargos' => 0,
            'saldo_capital' => 0,
            'saldo_interes' => 0,
            'saldo_mora' => 0,
            'forma_pago' => 'efectivo',
            'concepto' => 'Pago posterior al corte',
            'estado' => 'activo',
            'moneda' => 'GTQ',
            'tipo_cambio' => 1,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/reportes/creditos/vigentes/vista-previa?fecha_corte=2026-06-30');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fecha_corte', '2026-06-30')
            ->assertJsonPath('data.total_registros', 1)
            ->assertJsonPath('data.creditos.0.numero_credito', 'CP-1001')
            ->assertJsonPath('data.creditos.0.capital_cobrado', 500)
            ->assertJsonPath('data.creditos.0.capital_pendiente', 500)
            ->assertJsonPath('data.creditos.0.interes_generado', 120)
            ->assertJsonPath('data.creditos.0.interes_cobrado', 60)
            ->assertJsonPath('data.estadisticas.capital_pendiente', 500);
    }

    public function test_reporte_creditos_vigentes_respeta_rango_de_fechas(): void
    {
        $sucursal = Sucursal::create([
            'codigo' => 'SUC-01',
            'nombre' => 'Sucursal Centro',
            'direccion' => 'Zona 1',
            'telefono' => '11111111',
            'activa' => true,
        ]);

        $user = User::create([
            'name' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin.reportes@example.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $sucursal->id,
        ]);

        $cliente = Cliente::create([
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'dpi' => '1234567890101',
            'telefono' => '55550000',
            'direccion' => 'Ciudad',
            'estado' => 'activo',
            'fecha_nacimiento' => '1990-01-01',
            'nit' => 'CF',
            'genero' => 'masculino',
        ]);

        // Crédito 1: Desembolsado el 2026-06-05
        CreditoPrendario::create([
            'numero_credito' => 'CP-1001',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-06-01',
            'fecha_aprobacion' => '2026-06-01',
            'fecha_desembolso' => '2026-06-05',
            'fecha_vencimiento' => '2026-07-05',
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 1000,
        ]);

        // Crédito 2: Desembolsado el 2026-06-15
        CreditoPrendario::create([
            'numero_credito' => 'CP-1002',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => '2026-06-10',
            'fecha_aprobacion' => '2026-06-10',
            'fecha_desembolso' => '2026-06-15',
            'fecha_vencimiento' => '2026-07-15',
            'monto_solicitado' => 2000,
            'monto_aprobado' => 2000,
            'monto_desembolsado' => 2000,
            'capital_pendiente' => 2000,
        ]);

        // Consultamos con rango de fecha_desde = 2026-06-10 hasta 2026-06-20
        $response = $this->actingAs($user)->getJson('/api/v1/reportes/creditos/vigentes/vista-previa?fecha_inicio=2026-06-10&fecha_fin=2026-06-20');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_registros', 1)
            ->assertJsonPath('data.creditos.0.numero_credito', 'CP-1002');
    }
}