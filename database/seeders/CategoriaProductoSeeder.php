<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CategoriaProducto;

class CategoriaProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            [
                'codigo' => 'CAT-001',
                'nombre' => 'Joyería',
                'descripcion' => 'Anillos, collares, pulseras, relojes y accesorios de oro, plata y piedras preciosas',
                'color' => '#F59E0B',
                'icono' => 'Gem',
                'orden' => 1,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-002',
                'nombre' => 'Electrónica',
                'descripcion' => 'Celulares, tablets, laptops, televisores, consolas de videojuegos y dispositivos electrónicos',
                'color' => '#3B82F6',
                'icono' => 'DeviceMobile',
                'orden' => 2,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-003',
                'nombre' => 'Herramientas',
                'descripcion' => 'Herramientas eléctricas, manuales, equipos de construcción y maquinaria',
                'color' => '#8B5CF6',
                'icono' => 'Wrench',
                'orden' => 3,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-004',
                'nombre' => 'Electrodomésticos',
                'descripcion' => 'Refrigeradores, lavadoras, microondas, licuadoras y otros electrodomésticos',
                'color' => '#10B981',
                'icono' => 'Household',
                'orden' => 4,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-005',
                'nombre' => 'Vehículos',
                'descripcion' => 'Bicicletas, motocicletas, partes y accesorios de vehículos',
                'color' => '#EF4444',
                'icono' => 'Car',
                'orden' => 5,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-006',
                'nombre' => 'Muebles',
                'descripcion' => 'Muebles para hogar y oficina, decoración y artículos para el hogar',
                'color' => '#F97316',
                'icono' => 'Armchair',
                'orden' => 6,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-007',
                'nombre' => 'Ropa y Accesorios',
                'descripcion' => 'Ropa, calzado, bolsos, cinturones y accesorios de moda',
                'color' => '#EC4899',
                'icono' => 'Shirt',
                'orden' => 7,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-008',
                'nombre' => 'Instrumentos Musicales',
                'descripcion' => 'Guitarras, pianos, baterías, instrumentos de viento y equipos de audio',
                'color' => '#06B6D4',
                'icono' => 'MusicNote',
                'orden' => 8,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-009',
                'nombre' => 'Arte y Antigüedades',
                'descripcion' => 'Pinturas, esculturas, antigüedades, coleccionables y objetos de arte',
                'color' => '#8B5CF6',
                'icono' => 'PaintBrush',
                'orden' => 9,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-010',
                'nombre' => 'Deportes y Recreación',
                'descripcion' => 'Equipos deportivos, bicicletas, artículos de gimnasio y recreación',
                'color' => '#10B981',
                'icono' => 'Basketball',
                'orden' => 10,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-011',
                'nombre' => 'Computadoras y Accesorios',
                'descripcion' => 'Computadoras de escritorio, monitores, teclados, mouse y accesorios',
                'color' => '#3B82F6',
                'icono' => 'Monitor',
                'orden' => 11,
                'activa' => true,
            ],
            [
                'codigo' => 'CAT-012',
                'nombre' => 'Otros',
                'descripcion' => 'Productos que no encajan en las demás categorías',
                'color' => '#6B7280',
                'icono' => 'Package',
                'orden' => 99,
                'activa' => true,
            ],
        ];

        foreach ($categorias as $categoria) {
            CategoriaProducto::updateOrCreate(
                ['codigo' => $categoria['codigo']],
                $categoria
            );
        }

        $this->command->info('Categorías de productos creadas exitosamente.');
    }
}
