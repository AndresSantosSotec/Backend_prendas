<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\CreditoPrendario;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Prenda;
use App\Models\Venta;
use App\Models\VentaDetalle;
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

    public function test_reporte_creditos_vigentes_incluye_recuperaciones_por_ventas(): void
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
            'username' => 'admin_test_2',
            'email' => 'admin.ventas@example.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $sucursal->id,
        ]);

        $cliente = Cliente::create([
            'nombres' => 'Maria',
            'apellidos' => 'Lopez',
            'dpi' => '9876543210101',
            'telefono' => '44440000',
            'direccion' => 'Ciudad',
            'estado' => 'activo',
            'fecha_nacimiento' => '1992-05-10',
            'nit' => 'CF',
            'genero' => 'femenino',
        ]);

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-3001',
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursal->id,
            'estado' => 'vencido',
            'fecha_solicitud' => '2026-05-01',
            'fecha_aprobacion' => '2026-05-01',
            'fecha_desembolso' => '2026-05-02',
            'fecha_vencimiento' => '2026-06-02',
            'monto_solicitado' => 1500,
            'monto_aprobado' => 1500,
            'monto_desembolsado' => 1500,
            'capital_pendiente' => 1500,
        ]);

        $prenda = Prenda::create([
            'credito_prendario_id' => $credito->id,
            'sucursal_id' => $sucursal->id,
            'codigo_prenda' => 'PR-3001',
            'descripcion' => 'Anillo de oro 14k',
            'estado' => 'vendido',
            'valor_tasacion' => 1800,
            'valor_prestamo' => 1500,
            'precio_venta' => 1600,
        ]);

        $venta = Venta::create([
            'codigo_venta' => 'VEN-TEST-1',
            'cliente_nombre' => 'Comprador Test',
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $user->id,
            'tipo_venta' => 'contado',
            'subtotal' => 1600,
            'total_descuentos' => 0,
            'total_final' => 1600,
            'total_pagado' => 1600,
            'saldo_pendiente' => 0,
            'estado' => 'pagada',
            'fecha_venta' => '2026-06-15',
        ]);

        VentaDetalle::create([
            'venta_id' => $venta->id,
            'prenda_id' => $prenda->id,
            'codigo' => $prenda->codigo_prenda,
            'descripcion' => $prenda->descripcion,
            'cantidad' => 1,
            'precio_unitario' => 1600,
            'descuento' => 0,
            'subtotal' => 1600,
            'total' => 1600,
        ]);

        // Consulta con fecha de corte ANTES de la venta (2026-06-10): el crédito sigue con capital pendiente 1500 y recuperado ventas 0
        $resAntes = $this->actingAs($user)->getJson('/api/v1/reportes/creditos/vigentes/vista-previa?fecha_corte=2026-06-10');
        $resAntes->assertStatus(200)
            ->assertJsonPath('data.total_registros', 1)
            ->assertJsonPath('data.creditos.0.recuperado_ventas', 0)
            ->assertJsonPath('data.creditos.0.capital_pendiente', 1500);

        // Consulta con fecha de corte DESPUÉS de la venta (2026-06-20): la venta de 1600 cubre los 1500, por lo que capital_pendiente queda en 0
        $resDespues = $this->actingAs($user)->getJson('/api/v1/reportes/creditos/vigentes/vista-previa?fecha_corte=2026-06-20');
        $resDespues->assertStatus(200)
            ->assertJsonPath('data.total_registros', 0); // Al estar totalmente liquidado por la venta, ya no figura con saldo pendiente
    }
}