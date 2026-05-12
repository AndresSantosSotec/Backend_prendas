<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $nuevos = [
            [
                'modulo' => 'creditos',
                'accion' => 'editar_tasa_interes',
                'descripcion' => 'Permite modificar el % de interés al configurar créditos prendarios',
            ],
            [
                'modulo' => 'creditos',
                'accion' => 'editar_mora',
                'descripcion' => 'Permite modificar tasa de mora, tipo y monto fijo al configurar créditos prendarios',
            ],
        ];

        foreach ($nuevos as $row) {
            Permission::firstOrCreate(
                ['modulo' => $row['modulo'], 'accion' => $row['accion']],
                ['descripcion' => $row['descripcion']]
            );
        }

        $ids = Permission::query()
            ->where('modulo', 'creditos')
            ->whereIn('accion', ['editar_tasa_interes', 'editar_mora'])
            ->pluck('id');

        User::query()
            ->whereIn('rol', ['administrador', 'superadmin'])
            ->each(function (User $user) use ($ids) {
                $user->permissions()->syncWithoutDetaching($ids->all());
            });
    }

    public function down(): void
    {
        $ids = Permission::query()
            ->where('modulo', 'creditos')
            ->whereIn('accion', ['editar_tasa_interes', 'editar_mora'])
            ->pluck('id');

        foreach ($ids as $id) {
            \Illuminate\Support\Facades\DB::table('user_permissions')->where('permission_id', $id)->delete();
        }

        Permission::query()
            ->where('modulo', 'creditos')
            ->whereIn('accion', ['editar_tasa_interes', 'editar_mora'])
            ->delete();
    }
};
