# Guía de Docker para Desarrollo

## Requisitos Previos
- Docker Desktop instalado
- Docker Compose instalado

## Configuración Inicial

### 1. Configurar el archivo .env
```bash
# Copiar el archivo de configuración para Docker
cp .env.docker .env
```

### 2. Instalar dependencias (primera vez)
```bash
# Construir las imágenes de Docker
docker-compose build

# Instalar dependencias de Composer dentro del contenedor
docker-compose run --rm app composer install

# Generar la clave de la aplicación
docker-compose run --rm app php artisan key:generate
```

### 3. Levantar los servicios
```bash
# Iniciar todos los servicios en segundo plano
docker-compose up -d

# Ver los logs
docker-compose logs -f
```

### 4. Ejecutar migraciones
```bash
# Ejecutar migraciones
docker-compose exec app php artisan migrate

# O si es la primera vez y quieres seed:
docker-compose exec app php artisan migrate:fresh --seed
```

## Comandos Útiles

### Gestión de contenedores
```bash
# Iniciar servicios
docker-compose up -d

# Detener servicios
docker-compose down

# Ver estado de los contenedores
docker-compose ps

# Ver logs
docker-compose logs -f app

# Reiniciar un servicio específico
docker-compose restart app
```

### Comandos de Laravel
```bash
# Ejecutar artisan
docker-compose exec app php artisan [comando]

# Limpiar cache
docker-compose exec app php artisan optimize:clear

# Crear migración
docker-compose exec app php artisan make:migration nombre_migracion

# Ejecutar tests
docker-compose exec app php artisan test
```

### Comandos de Composer
```bash
# Instalar paquete
docker-compose exec app composer require nombre/paquete

# Actualizar dependencias
docker-compose exec app composer update
```

### Acceso a la base de datos
```bash
# Conectarse a MySQL desde la línea de comandos
docker-compose exec mysql mysql -u root -proot msplus2bdempenios

# O usar phpMyAdmin en el navegador
# URL: http://localhost:8080
# Usuario: root
# Contraseña: root
```

### Limpieza
```bash
# Detener y eliminar contenedores, redes
docker-compose down

# Detener y eliminar contenedores, redes y volúmenes (¡BORRARÁ LA BD!)
docker-compose down -v

# Limpiar todo (imágenes, contenedores, volúmenes no usados)
docker system prune -a --volumes
```

## URLs de Acceso

- **Aplicación Laravel**: http://localhost:8000
- **phpMyAdmin**: http://localhost:8080
- **MySQL**: localhost:3306

## Configuración de Base de Datos

**Dentro de los contenedores Docker:**
- Host: `mysql`
- Puerto: `3306`
- Base de datos: `msplus2bdempenios`
- Usuario: `root`
- Contraseña: `root`

**Desde tu máquina local (cliente MySQL, DBeaver, etc.):**
- Host: `localhost` o `127.0.0.1`
- Puerto: `3306`
- Base de datos: `msplus2bdempenios`
- Usuario: `root`
- Contraseña: `root`

## Solución de Problemas

### El puerto 3306 ya está en uso
Si tienes MySQL instalado localmente, detén el servicio o cambia el puerto en docker-compose.yml:
```yaml
ports:
  - "3307:3306"  # Cambia 3306 a 3307 en el host
```

### Permisos de archivos
```bash
# Desde Windows, ejecutar en PowerShell como administrador
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 775 /var/www/storage
```

### Reconstruir contenedores
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

## Desarrollo Diario

```bash
# 1. Iniciar el proyecto
docker-compose up -d

# 2. Trabajar normalmente...

# 3. Al terminar (opcional, los contenedores seguirán corriendo)
docker-compose down
```

Los archivos se sincronizan automáticamente entre tu máquina y los contenedores, así que puedes editar con VS Code normalmente.
