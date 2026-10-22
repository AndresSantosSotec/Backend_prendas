<?php

namespace Tests\Feature;

use App\Models\CajaAperturaCierre;
use App\Models\Cliente;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPrendario;
use App\Models\MovimientoCaja;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnularPagoCajaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;
    private User $user;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'codigo' => 'SUC-AP',
            'nombre' => 'Sucursal Anular Pago',
            'direccion' => 'Zona 1',
            'telefono' => '11111111',
            'activa' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cajero Test',
            'username' => 'cajero_anular',
            'email' => 'cajero.anular@example.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->cliente = Cliente::create([
            'nombres' => 'Ana',
            'apellidos' => 'Lopez',
            'dpi' => '1234567890102',
            'telefono' => '55550001',
            'direccion' => 'Ciudad',
            'estado' => 'activo',
            'fecha_nacimiento' => '1990-01-01',
            'genero' => 'femenino',
            'nit' => 'CF',
            'estado_civil' => 'soltero',
            'profesion' => 'Comerciante',
            'municipio' => 'Guatemala',
            'tipo_cliente' => 'regular',
        ]);
    }

    public function test_anular_pago_no_toca_el_desembolso_aunque_el_monto_sea_igual(): void
    {
        $this->actingAs($this->user);

        $caja = CajaAperturaCierre::create([
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->user->id,
            'fecha_apertura' => now()->toDateString(),
            'hora_apertura' => '08:00:00',
            'saldo_inicial' => 5000,
            'estado' => 'abierta',
        ]);

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-ANULA-001',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => now()->toDateString(),
            'fecha_desembolso' => now()->toDateString(),
            'monto_solicitado' => 1200,
            'monto_aprobado' => 1200,
            'monto_desembolsado' => 1200,
            'capital_pendiente' => 1200,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
        ]);

        $desembolso = MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'egreso_desembolso',
            'monto' => 1200,
            'concepto' => 'Desembolso Crédito #CP-ANULA-001',
            'detalles_movimiento' => [
                'tipo_operacion' => 'desembolso_credito',
                'numero_credito' => 'CP-ANULA-001',
            ],
            'estado' => 'aplicado',
            'user_id' => $this->user->id,
        ]);

        $pagoKardex = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'usuario_id' => $this->user->id,
            'sucursal_id' => $this->sucursal->id,
            'numero_movimiento' => 'PAG-ANULA-001',
            'tipo_movimiento' => 'pago',
            'fecha_movimiento' => now()->toDateString(),
            'fecha_registro' => now(),
            'monto_total' => 1200,
            'capital' => 1200,
            'interes' => 0,
            'mora' => 0,
            'otros_cargos' => 0,
            'saldo_capital' => 0,
            'forma_pago' => 'efectivo',
            'estado' => 'activo',
        ]);

        $ingresoPago = MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'ingreso_pago',
            'monto' => 1200,
            'concepto' => 'Pago Crédito #CP-ANULA-001',
            'detalles_movimiento' => [
                'tipo_operacion' => 'pago_credito',
                'numero_credito' => 'CP-ANULA-001',
                'numero_movimiento' => 'PAG-ANULA-001',
            ],
            'estado' => 'aplicado',
            'user_id' => $this->user->id,
        ]);

        $resultado = CajaService::revertirPagoCredito($pagoKardex, $credito, 'Pago duplicado');

        $this->assertTrue($resultado['aplicado']);
        $this->assertSame('anulado_original', $resultado['accion']);
        $this->assertSame($ingresoPago->id, $resultado['movimiento_caja_id']);

        $this->assertSame('anulado', $ingresoPago->fresh()->estado);
        $this->assertSame('aplicado', $desembolso->fresh()->estado);

        $resumen = CajaService::getResumenCaja($this->user->id);
        $this->assertEquals(3800.0, (float) $resumen['saldo_actual']);
    }
}
