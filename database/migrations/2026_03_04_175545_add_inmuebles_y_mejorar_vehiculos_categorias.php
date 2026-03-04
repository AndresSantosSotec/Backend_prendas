<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mejora campos dinámicos de Vehículos y agrega categoría Inmuebles con geolocalización.
     */
    public function up(): void
    {
        // ==================== 1. MEJORAR VEHÍCULOS ====================
        DB::table('categoria_productos')
            ->where('nombre', 'Vehículos')
            ->update([
                'descripcion' => 'Automóviles, motocicletas, camionetas, camiones y todo tipo de vehículos automotores',
                'campos_formulario' => json_encode([
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => true,
                    'condicion_fisica' => true,
                ]),
                'campos_adicionales' => json_encode([
                    // --- Identificación del vehículo ---
                    ['nombre' => 'tipo_vehiculo', 'label' => 'Tipo de Vehículo', 'tipo' => 'select', 'requerido' => true,
                     'opciones' => ['Automóvil', 'Motocicleta', 'Camioneta', 'Pick-Up', 'SUV', 'Camión', 'Bus/Microbús', 'Maquinaria Agrícola', 'Otro']],
                    ['nombre' => 'anio', 'label' => 'Año del Vehículo', 'tipo' => 'number', 'requerido' => true, 'min' => 1950, 'max' => 2030],
                    ['nombre' => 'color', 'label' => 'Color', 'tipo' => 'text', 'requerido' => true],
                    ['nombre' => 'placa', 'label' => 'Número de Placa', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: P-123ABC'],

                    // --- Datos mecánicos ---
                    ['nombre' => 'numero_motor', 'label' => 'Número de Motor', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Número grabado en el motor'],
                    ['nombre' => 'cilindraje', 'label' => 'Cilindraje (cc)', 'tipo' => 'number', 'requerido' => false, 'min' => 50, 'placeholder' => 'Ej: 1600'],
                    ['nombre' => 'tipo_combustible', 'label' => 'Tipo de Combustible', 'tipo' => 'select', 'requerido' => false,
                     'opciones' => ['Gasolina', 'Diésel', 'Gas LP', 'Eléctrico', 'Híbrido', 'Otro']],
                    ['nombre' => 'transmision', 'label' => 'Transmisión', 'tipo' => 'select', 'requerido' => false,
                     'opciones' => ['Manual', 'Automática', 'CVT', 'Semi-automática']],
                    ['nombre' => 'kilometraje', 'label' => 'Kilometraje', 'tipo' => 'number', 'requerido' => false, 'min' => 0, 'placeholder' => 'Km recorridos'],
                    ['nombre' => 'numero_puertas', 'label' => 'Número de Puertas', 'tipo' => 'select', 'requerido' => false,
                     'opciones' => ['2', '3', '4', '5', 'N/A']],

                    // --- Documentación ---
                    ['nombre' => 'tarjeta_circulacion', 'label' => 'No. Tarjeta de Circulación', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Número de tarjeta'],
                    ['nombre' => 'tiene_titulo', 'label' => '¿Tiene título de propiedad?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_tarjeta_circulacion', 'label' => '¿Tiene tarjeta de circulación vigente?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_seguro_vigente', 'label' => '¿Tiene seguro vigente?', 'tipo' => 'checkbox', 'requerido' => false],

                    // --- Extras ---
                    ['nombre' => 'num_llaves', 'label' => 'Número de Llaves Entregadas', 'tipo' => 'number', 'requerido' => false, 'min' => 0, 'max' => 5],
                    ['nombre' => 'observaciones_vehiculo', 'label' => 'Observaciones del Vehículo', 'tipo' => 'textarea', 'requerido' => false, 'placeholder' => 'Golpes, rayones, detalles mecánicos, etc.'],
                ]),
                'updated_at' => now(),
            ]);

        // ==================== 2. CREAR CATEGORÍA INMUEBLES ====================
        $existe = DB::table('categoria_productos')->where('codigo', 'CAT-INM')->exists();

        if (!$existe) {
            DB::table('categoria_productos')->insert([
                'codigo' => 'CAT-INM',
                'nombre' => 'Inmuebles',
                'descripcion' => 'Casas, terrenos, apartamentos, locales comerciales, fincas y bienes inmuebles en general',
                'color' => '#0D9488',
                'icono' => 'Buildings',
                'orden' => 13,
                'activa' => true,
                'campos_formulario' => json_encode([
                    'marca' => false,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ]),
                'campos_adicionales' => json_encode([
                    // --- Tipo y ubicación ---
                    ['nombre' => 'tipo_inmueble', 'label' => 'Tipo de Inmueble', 'tipo' => 'select', 'requerido' => true,
                     'opciones' => ['Casa', 'Apartamento', 'Terreno', 'Local Comercial', 'Bodega', 'Finca', 'Oficina', 'Edificio', 'Otro']],
                    ['nombre' => 'direccion_inmueble', 'label' => 'Dirección del Inmueble', 'tipo' => 'textarea', 'requerido' => true, 'placeholder' => 'Dirección completa del inmueble'],
                    ['nombre' => 'departamento', 'label' => 'Departamento', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: Guatemala, Quetzaltenango'],
                    ['nombre' => 'municipio', 'label' => 'Municipio', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: Mixco, Villa Nueva'],
                    ['nombre' => 'zona', 'label' => 'Zona', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: Zona 10'],

                    // --- Geolocalización ---
                    ['nombre' => 'latitud', 'label' => 'Latitud (coordenada)', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: 14.6349'],
                    ['nombre' => 'longitud', 'label' => 'Longitud (coordenada)', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: -90.5069'],
                    ['nombre' => 'geolocalizacion_url', 'label' => 'Enlace Google Maps', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'https://maps.google.com/...'],

                    // --- Características físicas ---
                    ['nombre' => 'area_terreno_m2', 'label' => 'Área del Terreno (m²)', 'tipo' => 'number', 'requerido' => true, 'min' => 1, 'placeholder' => 'Metros cuadrados de terreno'],
                    ['nombre' => 'area_construccion_m2', 'label' => 'Área de Construcción (m²)', 'tipo' => 'number', 'requerido' => false, 'min' => 0, 'placeholder' => 'Metros cuadrados construidos'],
                    ['nombre' => 'niveles', 'label' => 'Niveles/Pisos', 'tipo' => 'number', 'requerido' => false, 'min' => 1, 'max' => 50],
                    ['nombre' => 'habitaciones', 'label' => 'Habitaciones', 'tipo' => 'number', 'requerido' => false, 'min' => 0],
                    ['nombre' => 'banos', 'label' => 'Baños', 'tipo' => 'number', 'requerido' => false, 'min' => 0],
                    ['nombre' => 'parqueos', 'label' => 'Parqueos', 'tipo' => 'number', 'requerido' => false, 'min' => 0],

                    // --- Documentación legal ---
                    ['nombre' => 'numero_finca', 'label' => 'Número de Finca', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Número de registro'],
                    ['nombre' => 'numero_folio', 'label' => 'Número de Folio', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'numero_libro', 'label' => 'Número de Libro', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'registro_propiedad', 'label' => 'Registro de la Propiedad', 'tipo' => 'select', 'requerido' => false,
                     'opciones' => ['Zona Central', 'Segundo Registro (Quetzaltenango)', 'Otro']],
                    ['nombre' => 'tiene_escritura', 'label' => '¿Tiene escritura inscrita?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_iusi_al_dia', 'label' => '¿IUSI al día?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'libre_gravamen', 'label' => '¿Libre de gravámenes?', 'tipo' => 'checkbox', 'requerido' => false],

                    // --- Servicios ---
                    ['nombre' => 'tiene_agua', 'label' => '¿Servicio de Agua?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_luz', 'label' => '¿Servicio de Luz?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_drenaje', 'label' => '¿Servicio de Drenaje?', 'tipo' => 'checkbox', 'requerido' => false],

                    // --- Observaciones ---
                    ['nombre' => 'observaciones_inmueble', 'label' => 'Observaciones del Inmueble', 'tipo' => 'textarea', 'requerido' => false, 'placeholder' => 'Detalles adicionales, linderos, referencias, etc.'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir Vehículos a campos originales
        DB::table('categoria_productos')
            ->where('nombre', 'Vehículos')
            ->update([
                'descripcion' => 'Bicicletas, motocicletas, partes y accesorios de vehículos',
                'campos_formulario' => json_encode([
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => true,
                    'condicion_fisica' => true,
                ]),
                'campos_adicionales' => json_encode([
                    ['nombre' => 'tipo_vehiculo', 'label' => 'Tipo de Vehículo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Automóvil', 'Motocicleta', 'Camioneta', 'Camión', 'Otro']],
                    ['nombre' => 'anio', 'label' => 'Año', 'tipo' => 'number', 'requerido' => true, 'min' => 1950, 'max' => 2030],
                    ['nombre' => 'color', 'label' => 'Color', 'tipo' => 'text', 'requerido' => true],
                    ['nombre' => 'placa', 'label' => 'Número de Placa', 'tipo' => 'text', 'requerido' => true],
                    ['nombre' => 'kilometraje', 'label' => 'Kilometraje', 'tipo' => 'number', 'requerido' => false, 'min' => 0],
                    ['nombre' => 'tiene_titulo', 'label' => '¿Tiene título de propiedad?', 'tipo' => 'checkbox', 'requerido' => false],
                ]),
                'updated_at' => now(),
            ]);

        // Eliminar Inmuebles
        DB::table('categoria_productos')->where('codigo', 'CAT-INM')->delete();
    }
};
