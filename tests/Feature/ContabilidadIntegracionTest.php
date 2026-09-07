<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CreditoPlanPago;
use App\Models\CreditoPrendario;
use App\Models\CajaAperturaCierre;
use App\Models\Contabilidad\CtbDiario;
use App\Models\Contabilidad\CtbTipoPoliza;
use App\Models\Contabilidad\CtbNomenclatura;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Permission;
use App\Models\OtroGastoTipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContabilidadIntegracionTest extends TestCase
{
    use RefreshDatabase;

    private $sucursal;
    private $adminUser;
    private $contadorUser;
    private $cajeroUser;
    private $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'codigo' => 'SUC-01',
            'nombre' => 'Sucursal Central',
            'direccion' => 'Zona 1',
            'telefono' => '22223333',
            'activa' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin Contable',
            'username' => 'admin_contable',
            'email' => 'admin.contable@example.com',
            'password' => bcrypt('password123'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->contadorUser = User::create([
            'name' => 'Contador General',
            'username' => 'contador_general',
            'email' => 'contador@example.com',
            'password' => bcrypt('password123'),
            'rol' => 'contador',
            'activo' => true,
            'sucursal_id' => null, // Contador global
        ]);

        $this->cajeroUser = User::create([
            'name' => 'Cajero Test',
            'username' => 'cajero_test',
            'email' => 'cajero@example.com',
            'password' => bcrypt('password123'),
            'rol' => 'cajero',
            'activo' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);

        $this->cliente = Cliente::create([
            'nombres' => 'Carlos',
            'apellidos' => 'Mendoza',
            'dpi' => '1234567890102',
            'telefono' => '55551122',
            'direccion' => 'Guatemala',
            'estado' => 'activo',
            'fecha_nacimiento' => '1985-05-15',
            'genero' => 'masculino',
            'nit' => 'CF',
            'estado_civil' => 'soltero',
            'profesion' => 'Comerciante',
            'municipio' => 'Guatemala',
            'tipo_cliente' => 'regular',
        ]);

        // Seeders contables y permisos
        $this->seed(\Database\Seeders\TipoPolizaSeeder::class);
        $this->seed(\Database\Seeders\PlanCuentasSeeder::class);
        $this->seed(\Database\Seeders\ParametrizacionCuentasContablesSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->contadorUser->assignDefaultPermissions();
        $this->cajeroUser->assignDefaultPermissions();
    }

    /**
     * Test 1: Desembolso de crédito genera asiento contable cuadrado (Debe = Haber)
     */
    public function test_desembolso_credito_genera_asiento_cuadrado(): void
    {
        CajaAperturaCierre::create([
            'sucursal_id' => $this->sucursal->id,
            'cajero_id' => $this->adminUser->id,
            'user_id' => $this->adminUser->id,
            'fecha_apertura' => now(),
            'hora_apertura' => '08:00:00',
            'saldo_inicial' => 10000,
            'saldo_actual' => 10000,
            'estado' => 'abierta',
        ]);

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-TEST-100',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'aprobado',
            'fecha_solicitud' => now()->toDateString(),
            'fecha_aprobacion' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'monto_solicitado' => 1500,
            'monto_aprobado' => 1500,
            'capital_pendiente' => 1500,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'numero_cuotas' => 1,
            'monto_cuota' => 1650,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->postJson("/api/v1/creditos-prendarios/{$credito->id}/desembolsar", [
            'forma_desembolso' => 'efectivo',
            'referencia' => 'DES-TEST-1',
            'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);

        // Verificar que existe el asiento contable de desembolso
        $asiento = CtbDiario::where('credito_prendario_id', $credito->id)
            ->where('tipo_origen', 'credito_prendario')
            ->where('estado', '!=', 'anulado')
            ->with('movimientos')
            ->first();

        $this->assertNotNull($asiento, 'El asiento contable de desembolso no fue generado');
        $this->assertGreaterThanOrEqual(2, $asiento->movimientos->count(), 'El asiento debe tener al menos 2 movimientos');
        
        $totalDebe = round((float) $asiento->movimientos->sum('debe'), 2);
        $totalHaber = round((float) $asiento->movimientos->sum('haber'), 2);

        $this->assertEquals($totalDebe, $totalHaber, 'El asiento de desembolso no cuadra (Debe != Haber)');
        $this->assertEquals(1500.00, $totalDebe, 'El monto del asiento debe coincidir con el monto desembolsado');
    }

    /**
     * Test 2: Pago de crédito con capital + interés genera asiento contable cuadrado
     */
    public function test_pago_credito_genera_asiento_cuadrado_con_desglose(): void
    {
        CajaAperturaCierre::create([
            'sucursal_id' => $this->sucursal->id,
            'cajero_id' => $this->adminUser->id,
            'user_id' => $this->adminUser->id,
            'fecha_apertura' => now(),
            'hora_apertura' => '08:00:00',
            'saldo_inicial' => 10000,
            'saldo_actual' => 10000,
            'estado' => 'abierta',
        ]);

        $credito = CreditoPrendario::create([
            'numero_credito' => 'CP-TEST-101',
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'vigente',
            'fecha_solicitud' => now()->subDays(30)->toDateString(),
            'fecha_aprobacion' => now()->subDays(30)->toDateString(),
            'fecha_desembolso' => now()->subDays(30)->toDateString(),
            'fecha_vencimiento' => now()->toDateString(),
            'monto_solicitado' => 1000,
            'monto_aprobado' => 1000,
            'monto_desembolsado' => 1000,
            'capital_pendiente' => 1000,
            'capital_pagado' => 0,
            'interes_generado' => 100,
            'interes_pagado' => 0,
            'tasa_interes' => 10,
            'tipo_interes' => 'mensual',
            'plazo_dias' => 30,
            'numero_cuotas' => 1,
            'monto_cuota' => 1100,
        ]);

        CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => now()->toDateString(),
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

        $this->actingAs($this->adminUser);

        $response = $this->postJson("/api/v1/creditos-prendarios/{$credito->id}/pagos", [
            'tipo' => 'CUOTA',
            'monto' => 1100,
            'metodo_pago' => 'efectivo',
            'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);

        // Verificar el asiento generado para el pago
        $asiento = CtbDiario::where('credito_prendario_id', $credito->id)
            ->where('tipo_origen', 'credito_prendario')
            ->where('estado', '!=', 'anulado')
            ->with('movimientos')
            ->latest('id')
            ->first();

        $this->assertNotNull($asiento, 'El asiento contable de pago no fue generado');
        $totalDebe = round((float) $asiento->movimientos->sum('debe'), 2);
        $totalHaber = round((float) $asiento->movimientos->sum('haber'), 2);

        $this->assertEquals($totalDebe, $totalHaber, 'El asiento de pago no cuadra (Debe != Haber)');
        $this->assertEquals(1100.00, $totalDebe, 'El total debe ser exactamente 1100');
    }

    /**
     * Test 3: Creación de partida manual con validación de balance estricto
     */
    public function test_crear_partida_manual_valida_cuadre_estricto(): void
    {
        $this->actingAs($this->contadorUser);

        $tipoPoliza = CtbTipoPoliza::where('codigo', 'PD')->first();
        $cuentaCaja = CtbNomenclatura::where('codigo_cuenta', '1101.01.001')->first();
        $cuentaBanco = CtbNomenclatura::where('codigo_cuenta', '1101.01.003')->first();

        // 1. Intento con partida DESCUADRADA (Debe 500 != Haber 400) -> Debe fallar con 422
        $responseInvalido = $this->postJson('/api/v1/contabilidad/asientos', [
            'tipo_poliza_id' => $tipoPoliza->id,
            'fecha_contabilizacion' => now()->toDateString(),
            'glosa' => 'Partida descuadrada de prueba',
            'numero_documento' => 'DOC-ERR-1',
            'sucursal_id' => $this->sucursal->id,
            'movimientos' => [
                ['cuenta_contable_id' => $cuentaBanco->id, 'debe' => 500, 'haber' => 0, 'detalle' => 'Depósito banco'],
                ['cuenta_contable_id' => $cuentaCaja->id, 'debe' => 0, 'haber' => 400, 'detalle' => 'Salida caja errónea'],
            ],
        ]);

        $responseInvalido->assertStatus(422);
        $this->assertFalse($responseInvalido->json('success'));

        // 2. Intento con partida CUADRADA (Debe 500 == Haber 500) -> Debe ser exitoso 201
        $responseValido = $this->postJson('/api/v1/contabilidad/asientos', [
            'tipo_poliza_id' => $tipoPoliza->id,
            'fecha_contabilizacion' => now()->toDateString(),
            'glosa' => 'Partida cuadrada de prueba',
            'numero_documento' => 'DOC-OK-1',
            'sucursal_id' => $this->sucursal->id,
            'movimientos' => [
                ['cuenta_contable_id' => $cuentaBanco->id, 'debe' => 500, 'haber' => 0, 'detalle' => 'Depósito banco'],
                ['cuenta_contable_id' => $cuentaCaja->id, 'debe' => 0, 'haber' => 500, 'detalle' => 'Salida caja exacta'],
            ],
        ]);

        $responseValido->assertStatus(201);
        $this->assertTrue($responseValido->json('success'));
        $this->assertEquals(500.00, $responseValido->json('data.total_debe'));
        $this->assertEquals(500.00, $responseValido->json('data.total_haber'));
        $this->assertTrue($responseValido->json('data.cuadrado'));
    }

    /**
     * Test 4: Editar y eliminar partida contable por usuario con rol Contador
     */
    public function test_editar_y_eliminar_partida_con_rol_contador(): void
    {
        $this->actingAs($this->contadorUser);

        $tipoPoliza = CtbTipoPoliza::where('codigo', 'PD')->first();
        $cuentaCaja = CtbNomenclatura::where('codigo_cuenta', '1101.01.001')->first();
        $cuentaBanco = CtbNomenclatura::where('codigo_cuenta', '1101.01.003')->first();

        // Crear partida inicial
        $creada = $this->postJson('/api/v1/contabilidad/asientos', [
            'tipo_poliza_id' => $tipoPoliza->id,
            'fecha_contabilizacion' => now()->toDateString(),
            'glosa' => 'Partida para edición',
            'numero_documento' => 'DOC-EDIT-1',
            'sucursal_id' => $this->sucursal->id,
            'movimientos' => [
                ['cuenta_contable_id' => $cuentaBanco->id, 'debe' => 300, 'haber' => 0],
                ['cuenta_contable_id' => $cuentaCaja->id, 'debe' => 0, 'haber' => 300],
            ],
        ])->json('data');

        $partidaId = $creada['id'];

        // Editar partida cambiando el monto a 750 (cuadrado)
        $resUpdate = $this->putJson("/api/v1/contabilidad/asientos/{$partidaId}", [
            'glosa' => 'Partida editada exitosamente',
            'movimientos' => [
                ['cuenta_contable_id' => $cuentaBanco->id, 'debe' => 750, 'haber' => 0],
                ['cuenta_contable_id' => $cuentaCaja->id, 'debe' => 0, 'haber' => 750],
            ],
        ]);

        $resUpdate->assertStatus(200);
        $this->assertEquals(750.00, $resUpdate->json('data.total_debe'));
        $this->assertEquals('Partida editada exitosamente', $resUpdate->json('data.glosa'));

        // Eliminar partida
        $resDelete = $this->deleteJson("/api/v1/contabilidad/asientos/{$partidaId}");
        $resDelete->assertStatus(200);

        // Verificar que ya no existe en BD
        $this->assertNull(CtbDiario::find($partidaId));
    }

    /**
     * Test 5: Usuario cajero sin permisos no puede crear ni eliminar partidas
     */
    public function test_usuario_sin_permisos_no_puede_crear_partida(): void
    {
        $this->actingAs($this->cajeroUser);

        $tipoPoliza = CtbTipoPoliza::where('codigo', 'PD')->first();
        $cuentaCaja = CtbNomenclatura::where('codigo_cuenta', '1101.01.001')->first();
        $cuentaBanco = CtbNomenclatura::where('codigo_cuenta', '1101.01.003')->first();

        $response = $this->postJson('/api/v1/contabilidad/asientos', [
            'tipo_poliza_id' => $tipoPoliza->id,
            'fecha_contabilizacion' => now()->toDateString(),
            'glosa' => 'Intento no autorizado',
            'movimientos' => [
                ['cuenta_contable_id' => $cuentaBanco->id, 'debe' => 100, 'haber' => 0],
                ['cuenta_contable_id' => $cuentaCaja->id, 'debe' => 0, 'haber' => 100],
            ],
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 6: Compra directa genera asiento contable cuadrado
     */
    public function test_compra_prenda_genera_asiento_cuadrado(): void
    {
        $this->actingAs($this->adminUser);

        $categoria = \App\Models\CategoriaProducto::create([
            'codigo' => 'JOY-01',
            'nombre' => 'Joyería',
            'descripcion' => 'Prendas de oro y plata',
            'activa' => true,
        ]);

        CajaAperturaCierre::create([
            'sucursal_id' => $this->sucursal->id,
            'cajero_id' => $this->adminUser->id,
            'user_id' => $this->adminUser->id,
            'fecha_apertura' => now(),
            'hora_apertura' => '08:00:00',
            'saldo_inicial' => 10000,
            'saldo_actual' => 10000,
            'estado' => 'abierta',
        ]);

        $response = $this->postJson('/api/v1/compras', [
            'categoria_producto_id' => $categoria->id,
            'descripcion' => 'Anillo de oro 14k',
            'monto_pagado' => 800,
            'precio_venta' => 1200,
            'metodo_pago' => 'efectivo',
        ]);

        $response->assertStatus(201);

        $asiento = CtbDiario::where('tipo_origen', 'compra')
            ->where('estado', '!=', 'anulado')
            ->with('movimientos')
            ->latest('id')
            ->first();

        $this->assertNotNull($asiento, 'El asiento contable de compra no fue generado');
        $totalDebe = round((float) $asiento->movimientos->sum('debe'), 2);
        $totalHaber = round((float) $asiento->movimientos->sum('haber'), 2);

        $this->assertEquals($totalDebe, $totalHaber, 'El asiento de compra no cuadra');
        $this->assertEquals(800.00, $totalDebe);
    }

    /**
     * Test 7: Otro gasto operativo genera asiento contable cuadrado
     */
    public function test_otro_gasto_genera_asiento_cuadrado(): void
    {
        $this->actingAs($this->adminUser);

        CajaAperturaCierre::create([
            'sucursal_id' => $this->sucursal->id,
            'cajero_id' => $this->adminUser->id,
            'user_id' => $this->adminUser->id,
            'fecha_apertura' => now(),
            'hora_apertura' => '08:00:00',
            'saldo_inicial' => 10000,
            'saldo_actual' => 10000,
            'estado' => 'abierta',
        ]);

        $tipoGasto = OtroGastoTipo::create([
            'nombre' => 'Servicio de Internet',
            'tipo' => 'egreso',
            'grupo' => 'Servicios Básicos',
            'nomenclatura' => '5101.01',
            'activo' => true,
        ]);

        $response = $this->postJson('/api/v1/otros-gastos/movimientos', [
            'otro_gasto_tipo_id' => $tipoGasto->id,
            'monto' => 350,
            'concepto' => 'Pago mensual de internet',
            'forma_pago' => 'efectivo',
        ]);

        $response->assertStatus(201);

        $asiento = CtbDiario::where('tipo_origen', 'gasto')
            ->where('estado', '!=', 'anulado')
            ->with('movimientos')
            ->latest('id')
            ->first();

        $this->assertNotNull($asiento, 'El asiento contable de otro gasto no fue generado');
        $totalDebe = round((float) $asiento->movimientos->sum('debe'), 2);
        $totalHaber = round((float) $asiento->movimientos->sum('haber'), 2);

        $this->assertEquals($totalDebe, $totalHaber, 'El asiento de otro gasto no cuadra');
        $this->assertEquals(350.00, $totalDebe);
    }

    /**
     * Test 8: Descarga de comprobante PDF de una partida contable individual
     */
    public function test_descargar_pdf_partida_contable_individual(): void
    {
        $this->actingAs($this->contadorUser);

        $tipoPoliza = CtbTipoPoliza::where('codigo', 'PD')->first();
        $cuentaCaja = CtbNomenclatura::where('codigo_cuenta', '1101.01.001')->first();
        $cuentaBanco = CtbNomenclatura::where('codigo_cuenta', '1101.01.003')->first();

        // 1. Crear partida cuadrada
        $creada = $this->postJson('/api/v1/contabilidad/asientos', [
            'tipo_poliza_id' => $tipoPoliza->id,
            'fecha_contabilizacion' => now()->toDateString(),
            'glosa' => 'Partida para prueba de descarga PDF',
            'numero_documento' => 'DOC-PDF-1',
            'sucursal_id' => $this->sucursal->id,
            'movimientos' => [
                ['cuenta_contable_id' => $cuentaBanco->id, 'debe' => 600, 'haber' => 0, 'detalle' => 'Depósito banco'],
                ['cuenta_contable_id' => $cuentaCaja->id, 'debe' => 0, 'haber' => 600, 'detalle' => 'Salida caja'],
            ],
        ]);

        $creada->assertStatus(201);
        $partidaId = $creada->json('data.id');

        // 2. Solicitar descarga de PDF
        $responsePdf = $this->get("/api/v1/contabilidad/asientos/{$partidaId}/pdf");

        $responsePdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $responsePdf->headers->get('content-type'));
    }
}
