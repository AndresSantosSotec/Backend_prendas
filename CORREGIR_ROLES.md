# CORRECCIÓN DE ROLES - USUARIOS PRODUCCIÓN

## 🐛 Problema Detectado

El rol `gerente` NO existe en la base de datos. Los roles válidos son:
- `superadmin`
- `administrador`
- `cajero`
- `tasador`
- `supervisor`
- `vendedor`

## ✅ Solución Aplicada

Todos los usuarios se configuraron como **administrador** y luego puedes ajustar sus permisos manualmente desde el sistema.

### Usuarios Actualizados:
- **Tiffany Rivera** (Gerente General) → `administrador`
- **Carlos Adrián Maderos** (Auxiliar de Gerencia) → `administrador`
- **Sergio Burgos** (Asesor) → `administrador`

## 🚀 Comandos para Ejecutar

### En tu servidor local (Windows):
```powershell
# Opción 1: Borrar usuarios existentes y recrear
php artisan migrate:fresh --seed

# Opción 2: Solo recrear usuarios (sin borrar datos)
php artisan db:seed --class=UserProdSeeder --force
```

### En servidor compartido (cPanel/SSH con ea-php83):
```bash
# OPCIÓN A: Reset completo de base de datos
cd ~/public_html/api
git pull origin main
ea-php83 artisan migrate:fresh --force
ea-php83 artisan db:seed --class=DatabaseProdSeeder --force
ea-php83 artisan optimize:clear
ea-php83 artisan config:cache
ea-php83 artisan route:cache
```

```bash
# OPCIÓN B: Solo recrear usuarios (sin borrar datos)
cd ~/public_html/api
git pull origin main
ea-php83 artisan db:seed --class=UserProdSeeder --force
ea-php83 artisan permissions:assign-missing
ea-php83 artisan optimize:clear
```

```bash
# OPCIÓN C: Una sola línea para reset completo
cd ~/public_html/api && git pull origin main && ea-php83 artisan migrate:fresh --force && ea-php83 artisan db:seed --class=DatabaseProdSeeder --force && ea-php83 artisan optimize:clear && ea-php83 artisan config:cache && ea-php83 artisan route:cache
```

## 📝 Credenciales de Acceso

Después de ejecutar los comandos, los usuarios tendrán estas credenciales:

### César Vinicio (Administrador Principal)
- **Email:** cvinicio1983@gmail.com
- **Username:** cvinicio1983
- **Password:** `ME#a$uTrinBg3G@s9R`

### Tiffany Rivera (Gerente General)
- **Email:** tiffany07442@gmail.com
- **Username:** tiffany07442
- **Password:** `^fgx7Hq5$g^oXoJccH`
- **Rol:** administrador (todos los permisos)

### Carlos Adrián Maderos (Auxiliar de Gerencia)
- **Email:** adrianmaderos27@gmail.com
- **Username:** adrianmaderos27
- **Password:** `un9AAjaZJ&wuxpfEaW`
- **Rol:** administrador (todos los permisos)

### Sergio Burgos (Asesor)
- **Email:** sergio.burgos.gt@gmail.com
- **Username:** sergioburgosgt
- **Password:** `qahGoHML#5Wf79vEGF`
- **Rol:** administrador (todos los permisos)

### Angel Mejía (Vendedor)
- **Email:** Angelmoreira9210@gmail.com
- **Username:** Angelmoreira9210
- **Password:** `SJaS^#qc8^UKb$q%%2`
- **Rol:** vendedor

### Grisell López (Vendedora)
- **Email:** Asesordeventasgrisslopez@gmail.com
- **Username:** Asesordeventasgrisslopez
- **Password:** `KP^gt#7wUMmRs!BwW6`
- **Rol:** vendedor

## 🔧 Ajustar Permisos Manualmente (Después de Crear Usuarios)

Una vez creados los usuarios como administradores, puedes ajustar sus permisos desde el sistema:

1. Ingresa como SuperAdmin (andres@empenios.com)
2. Ve a la sección de **Usuarios**
3. Edita cada usuario y personaliza sus permisos según su cargo:
   - **Tiffany Rivera:** Permisos de gerencia
   - **Carlos Maderos:** Permisos de auxiliar
   - **Sergio Burgos:** Permisos de asesor

O usa este comando artisan para asignar permisos por rol:
```bash
ea-php83 artisan permissions:assign-missing --rol=administrador --force
```

## ⚠️ Notas Importantes

1. El rol `administrador` tiene **TODOS los permisos** por defecto
2. Después de crear los usuarios, puedes editar sus permisos manualmente desde el panel admin
3. Si quieres crear un rol personalizado, necesitas modificar la migración de la tabla `users`
4. Los permisos se asignan automáticamente al crear usuarios mediante `assignDefaultPermissions()`

## 🎯 Verificar que Todo Funcione

```bash
# Ver todos los usuarios
ea-php83 artisan tinker --execute="print_r(App\Models\User::all(['id','name','email','rol'])->toArray());"

# Ver permisos de un usuario específico
ea-php83 artisan tinker --execute="\$user = App\Models\User::where('email', 'tiffany07442@gmail.com')->first(); echo \$user->name . ' tiene ' . \$user->permissions()->count() . ' permisos';"
```
