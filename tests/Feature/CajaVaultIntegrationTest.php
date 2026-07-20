<?php

namespace Tests\Feature;

use App\Models\Boveda;
use App\Models\BovedaMovimiento;
use App\Models\CajaAperturaCierre;
use App\Models\ConfiguracionSistema;
use App\Models\MovimientoCaja;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaVaultIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Sucursal $sucursal;
    private Boveda $boveda;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear una sucursal para los tests
        $this->sucursal = Sucursal::create([
            'codigo' => 'ESQ-001',
            'nombre' => 'Esquipulas',
            'direccion' => 'Dirección de Esquipulas',
            'telefono' => '12345678',
            'activo' => true,
        ]);

        // Crear un usuario administrador (tiene permisos globales automáticos)
        $this->user = User::create([
            'name' => 'Usuario Test',
            'username' => 'usuario_test',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'activo' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);

        // Crear una bóveda activa con saldo inicial de 5000
        $this->boveda = Boveda::create([
            'codigo' => 'BOV-001',
            'nombre' => 'Bóveda Principal',
            'descripcion' => 'Bóveda de prueba',
            'sucursal_id' => $this->sucursal->id,
            'saldo_actual' => 5000.00,
            'saldo_minimo' => 0.00,
            'saldo_maximo' => 100000.00,
            'tipo' => 'principal',
            'activa' => true,
            'requiere_aprobacion' => true,
            'responsable_id' => $this->user->id,
            'creado_por' => $this->user->id,
        ]);
    }

    /**
     * Test de modo desconectado (por defecto).
     */
    public function test_desconectado_apertura_y_movimientos(): void
    {
        // 1. Asegurar que la integración esté desactivada
        ConfiguracionSistema::where('clave', 'cash_vault_integration_enabled')->delete();
        $this->assertFalse(ConfiguracionSistema::integracionCajaBovedaActiva());

        // 2. Verificar check-estado
        $response = $this->actingAs($this->user)->getJson('/api/v1/cajas/check-estado');
        $response->assertStatus(200)
            ->assertJson([
                'estado' => 'cerrada',
                'cash_vault_integration_enabled' => false,
            ]);

        // 3. Abrir caja sin especificar bóveda (en modo desconectado no es obligatoria)
        $response = $this->actingAs($this->user)->postJson('/api/v1/cajas/abrir', [
            'saldo_inicial' => 1000.00,
            'fecha_apertura' => now()->toDateString(),
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('caja.estado', 'abierta')
            ->assertJsonPath('caja.saldo_inicial', 1000);

        $cajaId = $response->json('caja.id');

        // El saldo de la bóveda no debe verse afectado en modo desconectado
        $this->assertEquals(5000.00, $this->boveda->fresh()->saldo_actual);

        // 4. Registrar movimiento de incremento en modo desconectado (se aplica inmediatamente)
        $response = $this->actingAs($this->user)->postJson('/api/v1/cajas/movimientos', [
            'caja_id' => $cajaId,
            'tipo' => 'incremento',
            'monto' => 500.00,
            'concepto' => 'Ingreso de prueba desconectado',
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('movimiento.estado', 'aplicado')
            ->assertJsonPath('movimiento.monto', 500);

        // No debe haber movimientos de bóveda creados
        $this->assertEquals(0, BovedaMovimiento::count());
    }

    /**
     * Test de modo integrado: validación de apertura y débito de saldo inicial de bóveda.
     */
    public function test_integrado_apertura_requiere_y_debit_boveda(): void
    {
        // 1. Activar la integración
        ConfiguracionSistema::updateOrCreate(
            ['clave' => 'cash_vault_integration_enabled'],
            ['valor' => '1', 'tipo' => 'boolean', 'descripcion' => 'Integración']
        );
        $this->assertTrue(ConfiguracionSistema::integracionCajaBovedaActiva());

        // 2. Intentar abrir caja sin especificar boveda_origen_id (debe fallar)
        $response = $this->actingAs($this->user)->postJson('/api/v1/cajas/abrir', [
            'saldo_inicial' => 1000.00,
            'fecha_apertura' => now()->toDateString(),
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['boveda_origen_id']);

        // 3. Intentar abrir con saldo inicial que excede el saldo de la bóveda (debe fallar)
        $response = $this->actingAs($this->user)->postJson('/api/v1/cajas/abrir', [
            'saldo_inicial' => 6000.00,
            'fecha_apertura' => now()->toDateString(),
            'boveda_origen_id' => $this->boveda->id,
        ]);
        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'La bóveda seleccionada no tiene fondos suficientes. Saldo disponible: 5000']);

        // 4. Abrir correctamente especificando boveda_origen_id
        $response = $this->actingAs($this->user)->postJson('/api/v1/cajas/abrir', [
            'saldo_inicial' => 1000.00,
            'fecha_apertura' => now()->toDateString(),
            'boveda_origen_id' => $this->boveda->id,
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('caja.estado', 'abierta')
            ->assertJsonPath('caja.boveda_origen_id', $this->boveda->id);

        // El saldo de la bóveda debe haberse debitado (5000 - 1000 = 4000)
        $this->assertEquals(4000.00, $this->boveda->fresh()->saldo_actual);

        // Debe haberse registrado un movimiento de salida aprobado en bóveda
        $this->assertDatabaseHas('boveda_movimientos', [
            'boveda_id' => $this->boveda->id,
            'tipo_movimiento' => 'salida',
            'monto' => 1000.00,
            'estado' => 'aprobado',
        ]);
    }

    /**
     * Test de modo integrado: registrar incremento (requiere aprobación).
     */
    public function test_integrado_registrar_incremento_pendiente(): void
    {
        // 1. Activar integración
        ConfiguracionSistema::updateOrCreate(['clave' => 'cash_vault_integration_enabled'], ['valor' => '1', 'tipo' => 'boolean']);

        // 2. Abrir caja
        $caja = CajaAperturaCierre::create([
            'user_id' => $this->user->id,
            'sucursal_id' => $this->sucursal->id,
            'fecha_apertura' => now()->toDateString(),
            'hora_apertura' => now()->toTimeString(),
            'saldo_inicial' => 1000.00,
            'estado' => 'abierta',
            'boveda_origen_id' => $this->boveda->id,
        ]);

        // 3. Registrar incremento en modo integrado (debe quedar pendiente)
        $response = $this->actingAs($this->user)->postJson('/api/v1/cajas/movimientos', [
            'caja_id' => $caja->id,
            'tipo' => 'incremento',
            'monto' => 500.00,
            'concepto' => 'Solicitud de incremento',
            'boveda_id' => $this->boveda->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('movimiento.estado', 'pendiente')
            ->assertJsonPath('movimiento.estado_boveda', 'pendiente_aprobacion')
            ->assertJsonPath('requiere_aprobacion', true);

        $movCajaId = $response->json('movimiento.id');
        $movBovedaId = $response->json('boveda_movimiento.id');

        // Bóveda no debe haber debitado los 500 aún porque está pendiente
        $this->assertEquals(5000.00, $this->boveda->fresh()->saldo_actual);

        // 4. Listar pendientes de aprobación en bóveda
        $response = $this->actingAs($this->user)->getJson('/api/v1/boveda/movimientos-caja-pendientes');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movCajaId);

        // 5. Aprobar el movimiento de caja pendiente en la bóveda
        $response = $this->actingAs($this->user)->postJson("/api/v1/boveda/movimientos-caja-pendientes/{$movCajaId}/aprobar", [
            'observaciones' => 'Aprobado en test',
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('movimiento.estado', 'aplicado')
            ->assertJsonPath('movimiento.estado_boveda', 'aprobado');

        // Bóveda ahora sí debe verse afectada (5000 - 500 = 4500)
        $this->assertEquals(4500.00, $this->boveda->fresh()->saldo_actual);

        // El movimiento de bóveda correspondiente debe estar aprobado
        $this->assertDatabaseHas('boveda_movimientos', [
            'id' => $movBovedaId,
            'estado' => 'aprobado',
        ]);
    }

    /**
     * Test de modo integrado: registrar decremento (se aplica de inmediato).
     */
    public function test_integrado_registrar_decremento_inmediato(): void
    {
        // 1. Activar integración
        ConfiguracionSistema::updateOrCreate(['clave' => 'cash_vault_integration_enabled'], ['valor' => '1', 'tipo' => 'boolean']);

        // 2. Abrir caja
        $caja = CajaAperturaCierre::create([
            'user_id' => $this->user->id,
            'sucursal_id' => $this->sucursal->id,
            'fecha_apertura' => now()->toDateString(),
            'hora_apertura' => now()->toTimeString(),
            'saldo_inicial' => 1000.00,
            'estado' => 'abierta',
            'boveda_origen_id' => $this->boveda->id,
        ]);

        // 3. Registrar decremento en modo integrado (caja envía a bóveda - se aplica de inmediato)
        $response = $this->actingAs($this->user)->postJson('/api/v1/cajas/movimientos', [
            'caja_id' => $caja->id,
            'tipo' => 'decremento',
            'monto' => 300.00,
            'concepto' => 'Envío a bóveda de sobrante',
            'boveda_id' => $this->boveda->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('movimiento.estado', 'aplicado')
            ->assertJsonPath('movimiento.estado_boveda', 'aprobado');

        // El saldo de la bóveda debe haberse incrementado inmediatamente (5000 + 300 = 5300)
        $this->assertEquals(5300.00, $this->boveda->fresh()->saldo_actual);

        // El movimiento de bóveda correspondiente debe estar aprobado y registrado como entrada
        $this->assertDatabaseHas('boveda_movimientos', [
            'boveda_id' => $this->boveda->id,
            'tipo_movimiento' => 'entrada',
            'monto' => 300.00,
            'estado' => 'aprobado',
        ]);
    }
}
