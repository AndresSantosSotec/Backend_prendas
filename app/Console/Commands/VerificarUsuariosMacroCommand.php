<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Permission;

/**
 * Comando para verificar y auditar los usuarios y permisos de MACRO FINANCIERA S.A.
 *
 * Ejecutar:
 *   php artisan macro:verificar-usuarios
 */
class VerificarUsuariosMacroCommand extends Command
{
    protected $signature = 'macro:verificar-usuarios {--email= : Verificar un usuario específico por su email}';

    protected $description = 'Verifica que los usuarios de Macro Financiera existan y tengan asignados los permisos correctos según su rol y cargo';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('🔍 AUDITORÍA DE USUARIOS Y PERMISOS: MACRO FINANCIERA S.A.');
        $this->info('================================================================');
        $this->newLine();

        $emails = [
            'brayanajpacaja547@gmail.com' => 'Gerente General (administrador)',
            'adielajpacaja@gmail.com'     => 'Contador General (contador)',
            'svictoriavp@gmail.com'        => 'Secretaria y Cajera (cajero)',
            'Vasqueztoto2018@gmail.com'    => 'Asesor de Créditos Prendarios (cajero/créditos)',
            'visiondefuego@hotmail.com'    => 'Asesor de Venta de Incobrables (vendedor)',
        ];

        if ($emailOpt = $this->option('email')) {
            $emails = [$emailOpt => 'Usuario específico'];
        }

        $resumenTabla = [];
        $todoCorrecto = true;

        foreach ($emails as $email => $cargoEsperado) {
            $user = User::with(['permissions', 'sucursal'])->where('email', $email)->first();

            if (!$user) {
                $this->error("❌ Usuario NO encontrado: {$email} ({$cargoEsperado})");
                $this->warn("   Ejecuta primero: php artisan db:seed --class=MacroFinancieraUsuariosSeeder --force");
                $this->newLine();
                $todoCorrecto = false;
                continue;
            }

            $totalPermisos = $user->permissions->count();
            $sucursalNombre = $user->sucursal?->nombre ?? 'Sin sucursal asignada';

            $resumenTabla[] = [
                'ID' => $user->id,
                'Nombre' => $user->name,
                'Email' => $user->email,
                'Rol' => $user->rol,
                'Sucursal' => $sucursalNombre,
                'Permisos' => $totalPermisos,
                'Activo' => $user->activo ? '✅ SÍ' : '❌ NO',
            ];
        }

        if (!empty($resumenTabla)) {
            $this->table(['ID', 'Nombre', 'Email', 'Rol', 'Sucursal', 'Permisos', 'Activo'], $resumenTabla);
            $this->newLine();
        }

        // =========================================================================
        // COMPROBACIÓN DETALLADA DE PERMISOS CLAVE POR CARGO
        // =========================================================================
        $this->info('📋 COMPROBACIÓN DETALLADA DE CAPACIDADES POR ROL:');
        $this->line('────────────────────────────────────────────────────────────────');

        foreach ($emails as $email => $cargoEsperado) {
            $user = User::with('permissions')->where('email', $email)->first();
            if (!$user) continue;

            $this->comment("👉 {$user->name} | Rol: [{$user->rol}] | {$cargoEsperado}");

            $permisosClave = match ($user->email) {
                'brayanajpacaja547@gmail.com' => [
                    'usuarios.ver' => 'Gestión de usuarios',
                    'sucursales.ver' => 'Gestión de sucursales',
                    'creditos.ver' => 'Acceso a cartera de créditos',
                    'caja.ver_movimientos' => 'Supervisión de cajas',
                    'reportes.generar' => 'Generación de reportes',
                ],
                'adielajpacaja@gmail.com' => [
                    'contabilidad.ver' => 'Ver módulo contable',
                    'contabilidad.asientos' => 'Libro diario y asientos',
                    'contabilidad.crear_partida' => 'Crear partidas contables',
                    'contabilidad.editar_partida' => 'Editar partidas contables',
                    'contabilidad.eliminar_partida' => 'Eliminar partidas contables',
                    'contabilidad.reportes' => 'Estados financieros y balances',
                    'cumplimiento.ver' => 'Reportes de cumplimiento e IVE',
                    'caja.ver_movimientos' => 'Auditoría de movimientos de caja',
                ],
                'svictoriavp@gmail.com' => [
                    'caja.abrir' => 'Apertura de caja',
                    'caja.cerrar' => 'Cierre de caja y arqueo',
                    'caja.ver_movimientos' => 'Registro de ingresos/egresos',
                    'cobros.realizar' => 'Cobro de cuotas y abonos',
                    'recibos.crear' => 'Emisión de comprobantes y recibos',
                    'creditos.ver' => 'Consulta de créditos',
                    'refrendos.procesar' => 'Procesamiento de refrendos',
                ],
                'Vasqueztoto2018@gmail.com' => [
                    'creditos.ver' => 'Ver créditos prendarios',
                    'creditos.crear' => 'Colocación de nuevos créditos',
                    'simulador.usar' => 'Simulador de préstamos y tasaciones',
                    'prendas.ver' => 'Inspección de garantías/prendas',
                    'refrendos.calcular' => 'Cálculo de refrendos y renovaciones',
                    'clientes.crear' => 'Registro de nuevos clientes',
                ],
                'visiondefuego@hotmail.com' => [
                    'ventas.ver' => 'Catálogo de prendas en venta',
                    'ventas.vender' => 'Procesar ventas de mostrador',
                    'cotizaciones.crear' => 'Crear cotizaciones a clientes',
                    'prendas.ver' => 'Inventario de prendas incobrables',
                    'recibos.crear' => 'Emisión de boletas de venta',
                ],
                default => [],
            };

            $todosAprobados = true;
            foreach ($permisosClave as $clave => $descripcion) {
                [$mod, $acc] = explode('.', $clave);
                $tiene = $user->hasPermission($mod, $acc);

                if ($tiene) {
                    $this->line("   [✓] {$clave} ({$descripcion})");
                } else {
                    $this->error("   [✗] FALTA: {$clave} ({$descripcion})");
                    $todosAprobados = false;
                    $todoCorrecto = false;
                }
            }

            if ($todosAprobados) {
                $this->info("   🎉 Perfil verificado con éxito.");
            } else {
                $this->warn("   ⚠️  Requiere sincronización: php artisan permissions:assign-missing --force");
            }

            $this->newLine();
        }

        $this->info('================================================================');
        if ($todoCorrecto) {
            $this->info('✅ TODOS LOS USUARIOS TIENEN SUS ROLES Y PERMISOS CORRECTOS');
        } else {
            $this->warn('⚠️  Se detectaron observaciones en la auditoría de usuarios');
        }
        $this->info('================================================================');

        return $todoCorrecto ? 0 : 1;
    }
}
