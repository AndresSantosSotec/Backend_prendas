<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use Illuminate\Database\Seeder;

class CamposDinamicosCategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Configura los campos dinámicos del formulario de prendas según cada categoría.
     *
     * Campos base disponibles:
     * - marca: Marca del producto
     * - modelo: Modelo del producto
     * - numero_serie: Número de serie o IMEI
     * - condicion_fisica: Estado físico del producto
     *
     * Campos adicionales son específicos por categoría.
     */
    public function run(): void
    {
        $configuraciones = [
            // 1. Joyería - Solo condición física, campos adicionales: quilates, material, peso
            'Joyería' => [
                'campos_formulario' => [
                    'marca' => false,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'material', 'label' => 'Material', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Oro', 'Plata', 'Platino', 'Oro Blanco', 'Oro Rosa', 'Otro']],
                    ['nombre' => 'quilates', 'label' => 'Quilates', 'tipo' => 'select', 'requerido' => false, 'opciones' => ['10K', '14K', '18K', '22K', '24K', 'N/A']],
                    ['nombre' => 'peso_gramos', 'label' => 'Peso (gramos)', 'tipo' => 'number', 'requerido' => true, 'min' => 0.01, 'step' => 0.01],
                    ['nombre' => 'tipo_joya', 'label' => 'Tipo de Joya', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Anillo', 'Cadena', 'Pulsera', 'Arete', 'Dije', 'Reloj', 'Otro']],
                    ['nombre' => 'piedras_preciosas', 'label' => 'Piedras Preciosas', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Diamantes, esmeraldas, etc.'],
                ],
            ],

            // 2. Electrónica - Todos los campos base + campos adicionales
            'Electrónica' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => true,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_dispositivo', 'label' => 'Tipo de Dispositivo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Celular', 'Tablet', 'Laptop', 'Consola', 'TV', 'Audio', 'Otro']],
                    ['nombre' => 'capacidad', 'label' => 'Capacidad/Almacenamiento', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: 128GB, 1TB'],
                    ['nombre' => 'color', 'label' => 'Color', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'incluye_cargador', 'label' => '¿Incluye cargador?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'incluye_caja', 'label' => '¿Incluye caja original?', 'tipo' => 'checkbox', 'requerido' => false],
                ],
            ],

            // 3. Vehículos - Campos específicos para vehículos (mejorado)
            'Vehículos' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => true, // Será el número de VIN
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    // Identificación
                    ['nombre' => 'tipo_vehiculo', 'label' => 'Tipo de Vehículo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Automóvil', 'Motocicleta', 'Camioneta', 'Pick-Up', 'SUV', 'Camión', 'Bus/Microbús', 'Maquinaria Agrícola', 'Otro']],
                    ['nombre' => 'anio', 'label' => 'Año del Vehículo', 'tipo' => 'number', 'requerido' => true, 'min' => 1950, 'max' => 2030],
                    ['nombre' => 'color', 'label' => 'Color', 'tipo' => 'text', 'requerido' => true],
                    ['nombre' => 'placa', 'label' => 'Número de Placa', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: P-123ABC'],
                    // Datos mecánicos
                    ['nombre' => 'numero_motor', 'label' => 'Número de Motor', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Número grabado en el motor'],
                    ['nombre' => 'cilindraje', 'label' => 'Cilindraje (cc)', 'tipo' => 'number', 'requerido' => false, 'min' => 50, 'placeholder' => 'Ej: 1600'],
                    ['nombre' => 'tipo_combustible', 'label' => 'Tipo de Combustible', 'tipo' => 'select', 'requerido' => false, 'opciones' => ['Gasolina', 'Diésel', 'Gas LP', 'Eléctrico', 'Híbrido', 'Otro']],
                    ['nombre' => 'transmision', 'label' => 'Transmisión', 'tipo' => 'select', 'requerido' => false, 'opciones' => ['Manual', 'Automática', 'CVT', 'Semi-automática']],
                    ['nombre' => 'kilometraje', 'label' => 'Kilometraje', 'tipo' => 'number', 'requerido' => false, 'min' => 0, 'placeholder' => 'Km recorridos'],
                    ['nombre' => 'numero_puertas', 'label' => 'Número de Puertas', 'tipo' => 'select', 'requerido' => false, 'opciones' => ['2', '3', '4', '5', 'N/A']],
                    // Documentación
                    ['nombre' => 'tarjeta_circulacion', 'label' => 'No. Tarjeta de Circulación', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Número de tarjeta'],
                    ['nombre' => 'tiene_titulo', 'label' => '¿Tiene título de propiedad?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_tarjeta_circulacion', 'label' => '¿Tiene tarjeta de circulación vigente?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_seguro_vigente', 'label' => '¿Tiene seguro vigente?', 'tipo' => 'checkbox', 'requerido' => false],
                    // Extras
                    ['nombre' => 'num_llaves', 'label' => 'Número de Llaves Entregadas', 'tipo' => 'number', 'requerido' => false, 'min' => 0, 'max' => 5],
                    ['nombre' => 'observaciones_vehiculo', 'label' => 'Observaciones del Vehículo', 'tipo' => 'textarea', 'requerido' => false, 'placeholder' => 'Golpes, rayones, detalles mecánicos, etc.'],
                ],
            ],

            // 4. Electrodomésticos
            'Electrodomésticos' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => true,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_electrodomestico', 'label' => 'Tipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Refrigerador', 'Lavadora', 'Secadora', 'Estufa', 'Microondas', 'Licuadora', 'Otro']],
                    ['nombre' => 'voltaje', 'label' => 'Voltaje', 'tipo' => 'select', 'requerido' => false, 'opciones' => ['110V', '220V', 'Dual']],
                    ['nombre' => 'color', 'label' => 'Color', 'tipo' => 'text', 'requerido' => false],
                ],
            ],

            // 5. Herramientas
            'Herramientas' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_herramienta', 'label' => 'Tipo de Herramienta', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Eléctrica', 'Manual', 'Neumática', 'Otro']],
                    ['nombre' => 'incluye_accesorios', 'label' => '¿Incluye accesorios?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'detalle_accesorios', 'label' => 'Detalle de Accesorios', 'tipo' => 'textarea', 'requerido' => false],
                ],
            ],

            // 6. Instrumentos Musicales
            'Instrumentos Musicales' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_instrumento', 'label' => 'Tipo de Instrumento', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Guitarra', 'Piano/Teclado', 'Batería', 'Bajo', 'Violín', 'Saxofón', 'Otro Viento', 'Otro Cuerda', 'Otro']],
                    ['nombre' => 'incluye_estuche', 'label' => '¿Incluye estuche?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'incluye_accesorios', 'label' => '¿Incluye accesorios?', 'tipo' => 'checkbox', 'requerido' => false],
                ],
            ],

            // 7. Deportes y Recreación (antes Artículos Deportivos)
            'Deportes y Recreación' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_articulo', 'label' => 'Tipo de Artículo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Bicicleta', 'Equipo de Gimnasio', 'Golf', 'Pesca', 'Camping', 'Otro']],
                    ['nombre' => 'talla', 'label' => 'Talla/Tamaño', 'tipo' => 'text', 'requerido' => false],
                ],
            ],

            // 8. Muebles
            'Muebles' => [
                'campos_formulario' => [
                    'marca' => false,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_mueble', 'label' => 'Tipo de Mueble', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Sala', 'Comedor', 'Recámara', 'Cocina', 'Oficina', 'Otro']],
                    ['nombre' => 'material', 'label' => 'Material', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Madera', 'Metal', 'Vidrio', 'Plástico', 'Mixto', 'Otro']],
                    ['nombre' => 'dimensiones', 'label' => 'Dimensiones (Alto x Ancho x Profundidad)', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: 180x90x45 cm'],
                    ['nombre' => 'color', 'label' => 'Color', 'tipo' => 'text', 'requerido' => false],
                ],
            ],

            // 9. Arte y Antigüedades (antes Antigüedades)
            'Arte y Antigüedades' => [
                'campos_formulario' => [
                    'marca' => false,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_antiguedad', 'label' => 'Tipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Pintura', 'Escultura', 'Moneda', 'Documento', 'Porcelana', 'Reloj', 'Otro']],
                    ['nombre' => 'epoca_aproximada', 'label' => 'Época Aproximada', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: Siglo XIX, Años 50s'],
                    ['nombre' => 'origen', 'label' => 'Origen/Procedencia', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'tiene_certificado', 'label' => '¿Tiene certificado de autenticidad?', 'tipo' => 'checkbox', 'requerido' => false],
                ],
            ],

            // 10. Equipos Médicos
            'Equipos Médicos' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => true,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_equipo', 'label' => 'Tipo de Equipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Diagnóstico', 'Terapéutico', 'Laboratorio', 'Dental', 'Oftalmológico', 'Otro']],
                    ['nombre' => 'fecha_ultima_calibracion', 'label' => 'Fecha Última Calibración', 'tipo' => 'date', 'requerido' => false],
                    ['nombre' => 'tiene_manual', 'label' => '¿Tiene manual?', 'tipo' => 'checkbox', 'requerido' => false],
                ],
            ],

            // 11. Materiales de Construcción
            'Materiales de Construcción' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_material', 'label' => 'Tipo de Material', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Herramienta Pesada', 'Andamio', 'Compresor', 'Generador', 'Mezcladora', 'Otro']],
                    ['nombre' => 'cantidad', 'label' => 'Cantidad', 'tipo' => 'number', 'requerido' => false, 'min' => 1],
                ],
            ],

            // 12. Otros
            'Otros' => [
                'campos_formulario' => [
                    'marca' => false,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_articulo', 'label' => 'Tipo de Artículo', 'tipo' => 'text', 'requerido' => true],
                    ['nombre' => 'descripcion_adicional', 'label' => 'Descripción Adicional', 'tipo' => 'textarea', 'requerido' => false],
                ],
            ],

            // 13. Ropa y Accesorios
            'Ropa y Accesorios' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_prenda', 'label' => 'Tipo de Prenda', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Vestido', 'Traje', 'Chamarra/Abrigo', 'Bolso/Cartera', 'Zapatos', 'Reloj', 'Lentes', 'Otro']],
                    ['nombre' => 'talla', 'label' => 'Talla', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'color', 'label' => 'Color', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'material', 'label' => 'Material', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: Cuero, Seda, Algodón'],
                ],
            ],

            // 14. Computadoras y Accesorios
            'Computadoras y Accesorios' => [
                'campos_formulario' => [
                    'marca' => true,
                    'modelo' => true,
                    'numero_serie' => true,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    ['nombre' => 'tipo_equipo', 'label' => 'Tipo de Equipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Laptop', 'Desktop', 'Monitor', 'Impresora', 'Teclado/Mouse', 'Disco Duro', 'Tarjeta Gráfica', 'Otro']],
                    ['nombre' => 'procesador', 'label' => 'Procesador', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: Intel i7, AMD Ryzen 5'],
                    ['nombre' => 'ram', 'label' => 'Memoria RAM', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: 16GB'],
                    ['nombre' => 'almacenamiento', 'label' => 'Almacenamiento', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: 512GB SSD'],
                    ['nombre' => 'incluye_cargador', 'label' => '¿Incluye cargador?', 'tipo' => 'checkbox', 'requerido' => false],
                ],
            ],

            // 15. Inmuebles - Con geolocalización y coordenadas
            'Inmuebles' => [
                'campos_formulario' => [
                    'marca' => false,
                    'modelo' => false,
                    'numero_serie' => false,
                    'condicion_fisica' => true,
                ],
                'campos_adicionales' => [
                    // Tipo y ubicación
                    ['nombre' => 'tipo_inmueble', 'label' => 'Tipo de Inmueble', 'tipo' => 'select', 'requerido' => true, 'opciones' => ['Casa', 'Apartamento', 'Terreno', 'Local Comercial', 'Bodega', 'Finca', 'Oficina', 'Edificio', 'Otro']],
                    ['nombre' => 'direccion_inmueble', 'label' => 'Dirección del Inmueble', 'tipo' => 'textarea', 'requerido' => true, 'placeholder' => 'Dirección completa del inmueble'],
                    ['nombre' => 'departamento', 'label' => 'Departamento', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: Guatemala, Quetzaltenango'],
                    ['nombre' => 'municipio', 'label' => 'Municipio', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: Mixco, Villa Nueva'],
                    ['nombre' => 'zona', 'label' => 'Zona', 'tipo' => 'text', 'requerido' => false, 'placeholder' => 'Ej: Zona 10'],
                    // Geolocalización interactiva (mapa OpenStreetMap)
                    ['nombre' => 'ubicacion_mapa', 'label' => 'Ubicación en el Mapa', 'tipo' => 'mapa', 'requerido' => false],
                    // Características físicas
                    ['nombre' => 'area_terreno_m2', 'label' => 'Área del Terreno (m²)', 'tipo' => 'number', 'requerido' => true, 'min' => 1, 'placeholder' => 'Metros cuadrados de terreno'],
                    ['nombre' => 'area_construccion_m2', 'label' => 'Área de Construcción (m²)', 'tipo' => 'number', 'requerido' => false, 'min' => 0, 'placeholder' => 'Metros cuadrados construidos'],
                    ['nombre' => 'niveles', 'label' => 'Niveles/Pisos', 'tipo' => 'number', 'requerido' => false, 'min' => 1, 'max' => 50],
                    ['nombre' => 'habitaciones', 'label' => 'Habitaciones', 'tipo' => 'number', 'requerido' => false, 'min' => 0],
                    ['nombre' => 'banos', 'label' => 'Baños', 'tipo' => 'number', 'requerido' => false, 'min' => 0],
                    ['nombre' => 'parqueos', 'label' => 'Parqueos', 'tipo' => 'number', 'requerido' => false, 'min' => 0],
                    // Documentación legal
                    ['nombre' => 'numero_finca', 'label' => 'Número de Finca', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Número de registro'],
                    ['nombre' => 'numero_folio', 'label' => 'Número de Folio', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'numero_libro', 'label' => 'Número de Libro', 'tipo' => 'text', 'requerido' => false],
                    ['nombre' => 'registro_propiedad', 'label' => 'Registro de la Propiedad', 'tipo' => 'select', 'requerido' => false, 'opciones' => ['Zona Central', 'Segundo Registro (Quetzaltenango)', 'Otro']],
                    ['nombre' => 'tiene_escritura', 'label' => '¿Tiene escritura inscrita?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_iusi_al_dia', 'label' => '¿IUSI al día?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'libre_gravamen', 'label' => '¿Libre de gravámenes?', 'tipo' => 'checkbox', 'requerido' => false],
                    // Servicios
                    ['nombre' => 'tiene_agua', 'label' => '¿Servicio de Agua?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_luz', 'label' => '¿Servicio de Luz?', 'tipo' => 'checkbox', 'requerido' => false],
                    ['nombre' => 'tiene_drenaje', 'label' => '¿Servicio de Drenaje?', 'tipo' => 'checkbox', 'requerido' => false],
                    // Observaciones
                    ['nombre' => 'observaciones_inmueble', 'label' => 'Observaciones del Inmueble', 'tipo' => 'textarea', 'requerido' => false, 'placeholder' => 'Detalles adicionales, linderos, referencias, etc.'],
                ],
            ],
        ];

        foreach ($configuraciones as $nombreCategoria => $config) {
            CategoriaProducto::where('nombre', $nombreCategoria)->update([
                'campos_formulario' => json_encode($config['campos_formulario']),
                'campos_adicionales' => json_encode($config['campos_adicionales']),
            ]);
        }

        $this->command->info('Campos dinámicos configurados para ' . count($configuraciones) . ' categorías.');
    }
}
