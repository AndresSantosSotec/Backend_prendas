<?php

namespace App\Services;

use App\Models\CreditoPrendario;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Prenda;
use App\Models\CreditoMovimiento;
use App\Models\Recibo;
use App\Models\Compra;
use App\Models\Venta;
use App\Models\CtbNomenclatura;
use App\Models\CtbDiario;
use App\Models\CtbMovimiento;
use App\Models\CategoriaProducto;
use App\Models\MigracionDatosLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class MigracionService
{
    /**
     * Nombres legibles de las pestañas/modelos
     */
    protected $modelNames = [
        'clientes' => 'Clientes',
        'creditos' => 'Créditos Prendarios',
        'prendas' => 'Prendas de Garantía',
        'pagos' => 'Historial de Pagos',
        'compras' => 'Compras e Inventario',
        'ventas' => 'Ventas Históricas',
        'ctb_nomenclatura' => 'Nomenclatura Contable',
        'ctb_diario' => 'Partidas Contables',
    ];

    /**
     * Definición de columnas esperadas por modelo
     */
    protected $templates = [
        'clientes' => [
            'dpi', 'nit', 'nombres', 'apellidos', 'telefono', 'email',
            'direccion', 'municipio', 'departamento', 'estado_civil', 'profesion'
        ],
        'creditos' => [
            'numero_credito', 'cliente_documento', 'sucursal_codigo', 'analista_username',
            'fecha_solicitud', 'fecha_aprobacion', 'fecha_desembolso', 'fecha_vencimiento',
            'monto_solicitado', 'monto_aprobado', 'tasa_interes', 'plazo_dias', 'estado', 'observaciones'
        ],
        'prendas' => [
            'numero_credito', 'descripcion', 'categoria', 'marca', 'modelo', 'serie',
            'estado_conservacion', 'valor_tasacion', 'observaciones'
        ],
        'pagos' => [
            'numero_credito', 'numero_recibo', 'fecha_pago', 'monto_total',
            'capital', 'interes', 'mora', 'forma_pago', 'observaciones'
        ],
        'compras' => [
            'codigo_compra', 'cliente_documento', 'cliente_nombre', 'sucursal_codigo', 'fecha_compra',
            'descripcion', 'categoria_nombre', 'marca', 'modelo', 'serie',
            'valor_tasacion', 'monto_pagado', 'precio_venta_sugerido', 'observaciones'
        ],
        'ventas' => [
            'numero_factura', 'cliente_documento', 'sucursal_codigo', 'fecha_venta',
            'total_final', 'forma_pago', 'observaciones'
        ],
        'ctb_nomenclatura' => [
            'codigo_cuenta', 'nombre_cuenta', 'tipo', 'naturaleza', 'nivel',
            'cuenta_padre_codigo', 'acepta_movimientos'
        ],
        'ctb_diario' => [
            'numero_comprobante', 'fecha_contabilizacion', 'glosa', 'codigo_cuenta',
            'monto_debe', 'monto_haber', 'sucursal_codigo', 'concepto'
        ],
    ];

    /**
     * Descripciones de columnas por modelo (para la fila de ayuda)
     */
    protected $columnDescriptions = [
        'clientes' => [
            'dpi' => 'DPI o CUI del cliente (13 dígitos)',
            'nit' => 'NIT sin guiones (Opcional)',
            'nombres' => 'Nombres del cliente',
            'apellidos' => 'Apellidos del cliente',
            'telefono' => 'Teléfono principal',
            'email' => 'Correo electrónico (Opcional)',
            'direccion' => 'Dirección de residencia (Opcional)',
            'municipio' => 'Municipio de residencia',
            'departamento' => 'Departamento de residencia',
            'estado_civil' => 'soltero|casado|divorciado|viudo',
            'profesion' => 'Profesión u oficio (Opcional)',
        ],
        'creditos' => [
            'numero_credito' => 'Ej: CP-001 (Único)',
            'cliente_documento' => 'DPI o NIT del cliente',
            'sucursal_codigo' => 'Código de sucursal (Ej: SUC01)',
            'analista_username' => 'Usuario del analista (Opcional)',
            'fecha_solicitud' => 'Formato: YYYY-MM-DD',
            'fecha_aprobacion' => 'Formato: YYYY-MM-DD (Opcional)',
            'fecha_desembolso' => 'Formato: YYYY-MM-DD (Opcional)',
            'fecha_vencimiento' => 'Formato: YYYY-MM-DD',
            'monto_solicitado' => 'Monto numérico (Ej: 5000.00)',
            'monto_aprobado' => 'Monto numérico',
            'tasa_interes' => 'Porcentaje mensual (Ej: 3.5)',
            'plazo_dias' => 'Número de días (Ej: 30)',
            'estado' => 'vigente|cancelado|pagado|en_mora',
            'observaciones' => 'Texto libre (Opcional)',
        ],
        'prendas' => [
            'numero_credito' => 'Crédito al que pertenece (Ej: CP-001)',
            'descripcion' => 'Descripción de la prenda',
            'categoria' => 'Ej: Joyería, Electrónica, Vehículos',
            'marca' => 'Marca (Opcional)',
            'modelo' => 'Modelo (Opcional)',
            'serie' => 'Número de serie (Opcional)',
            'estado_conservacion' => 'Excelente|Bueno|Regular|Malo',
            'valor_tasacion' => 'Valor numérico (Ej: 2500.00)',
            'observaciones' => 'Texto libre (Opcional)',
        ],
        'pagos' => [
            'numero_credito' => 'Número de crédito (Ej: CP-001)',
            'numero_recibo' => 'Correlativo de recibo (Ej: REC-001)',
            'fecha_pago' => 'Formato: YYYY-MM-DD',
            'monto_total' => 'Total pagado (Ej: 500.00)',
            'capital' => 'Monto abonado a capital',
            'interes' => 'Monto de interés pagado',
            'mora' => 'Monto de mora pagada (Ej: 0.00)',
            'forma_pago' => 'efectivo|transferencia|deposito|cheque',
            'observaciones' => 'Texto libre (Opcional)',
        ],
        'compras' => [
            'codigo_compra' => 'Código único de compra (Ej: COM-001)',
            'cliente_documento' => 'DPI o NIT del vendedor',
            'cliente_nombre' => 'Nombre completo del vendedor',
            'sucursal_codigo' => 'Código de sucursal (Ej: SUC01)',
            'fecha_compra' => 'Formato: YYYY-MM-DD',
            'descripcion' => 'Descripción del artículo/prenda',
            'categoria_nombre' => 'Ej: Electrónica, Herramientas',
            'marca' => 'Marca (Opcional)',
            'modelo' => 'Modelo (Opcional)',
            'serie' => 'Serie (Opcional)',
            'valor_tasacion' => 'Valor evaluado',
            'monto_pagado' => 'Monto pagado al cliente',
            'precio_venta_sugerido' => 'Precio de venta al público',
            'observaciones' => 'Texto libre (Opcional)',
        ],
        'ventas' => [
            'numero_factura' => 'Número de factura o venta',
            'cliente_documento' => 'DPI o NIT del cliente comprador',
            'sucursal_codigo' => 'Código de sucursal (Ej: SUC01)',
            'fecha_venta' => 'Formato: YYYY-MM-DD',
            'total_final' => 'Total de la venta',
            'forma_pago' => 'efectivo|tarjeta|transferencia',
            'observaciones' => 'Texto libre (Opcional)',
        ],
        'ctb_nomenclatura' => [
            'codigo_cuenta' => 'Código contable (Ej: 110101)',
            'nombre_cuenta' => 'Nombre de la cuenta',
            'tipo' => 'activo|pasivo|capital|ingreso|gasto',
            'naturaleza' => 'deudora|acreedora',
            'nivel' => 'Número de nivel (1 a 5)',
            'cuenta_padre_codigo' => 'Código de cuenta padre (Opcional)',
            'acepta_movimientos' => '1 (Sí) o 0 (No)',
        ],
        'ctb_diario' => [
            'numero_comprobante' => 'Número de póliza (Ej: POL-2026-001)',
            'fecha_contabilizacion' => 'Formato: YYYY-MM-DD',
            'glosa' => 'Glosa/Descripción del comprobante',
            'codigo_cuenta' => 'Código de cuenta contable',
            'monto_debe' => 'Monto al Debe (0 si es al Haber)',
            'monto_haber' => 'Monto al Haber (0 si es al Debe)',
            'sucursal_codigo' => 'Código de sucursal (Ej: SUC01)',
            'concepto' => 'Concepto específico del renglón',
        ],
    ];

    /**
     * Generar plantilla Excel (.xlsx) para un modelo o para el Libro Maestro
     */
    public function generateTemplate(string $modelo): string
    {
        if ($modelo !== 'maestro' && !isset($this->templates[$modelo])) {
            throw new \Exception("Modelo no soportado para migración: {$modelo}");
        }

        $spreadsheet = new Spreadsheet();

        if ($modelo === 'maestro') {
            // Eliminar la hoja por defecto creada al instanciar
            $spreadsheet->removeSheetByIndex(0);

            // Generar una pestaña para cada modelo en orden relacional
            $orderedKeys = ['clientes', 'creditos', 'prendas', 'pagos', 'compras', 'ventas', 'ctb_nomenclatura', 'ctb_diario'];

            foreach ($orderedKeys as $key) {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($this->modelNames[$key] ?? $key);
                $this->buildSheetContent($sheet, $key);
            }

            // Hoja de instrucciones generales
            $instrSheet = $spreadsheet->createSheet();
            $instrSheet->setTitle('Instrucciones Generales');
            $this->buildMasterInstructionsContent($instrSheet);
            $spreadsheet->setActiveSheetIndex(0);

        } else {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Datos');
            $this->buildSheetContent($sheet, $modelo);

            // Hoja de instrucciones específicas
            $instrSheet = $spreadsheet->createSheet();
            $instrSheet->setTitle('Instrucciones');
            $this->buildSingleInstructionsContent($instrSheet, $modelo);
            $spreadsheet->setActiveSheetIndex(0);
        }

        // Guardar archivo en carpeta temporal
        $tempPath = storage_path('app/temp/plantilla_' . $modelo . '_' . time() . '.xlsx');
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $tempPath;
    }

    /**
     * Construir encabezados y fila de ayuda en una pestaña de Excel
     */
    protected function buildSheetContent($sheet, string $modelo): void
    {
        $headers = $this->templates[$modelo];
        $descriptions = $this->columnDescriptions[$modelo] ?? [];

        // Fila 1: Cabeceras con estilo azul
        foreach ($headers as $colIndex => $header) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cell = $col . '1';
            $sheet->setCellValue($cell, $header);

            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E3A8A']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Fila 2: Descripciones/ayuda (gris claro)
        foreach ($headers as $colIndex => $header) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cell = $col . '2';
            $desc = $descriptions[$header] ?? '';
            $sheet->setCellValue($cell, $desc);

            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '4B5563']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
        }

        // Ajustar altura de filas iniciales
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(20);
    }

    /**
     * Construir hoja de instrucciones para un modelo individual
     */
    protected function buildSingleInstructionsContent($sheet, string $modelo): void
    {
        $sheet->setCellValue('A1', 'INSTRUCCIONES DE MIGRACIÓN - ' . strtoupper($this->modelNames[$modelo] ?? $modelo));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));

        $sheet->setCellValue('A3', '1. Complete los datos a partir de la FILA 3 de la pestaña "Datos".');
        $sheet->setCellValue('A4', '2. NO modifique los nombres de las cabeceras (Fila 1) ni elimine la fila de ayuda (Fila 2).');
        $sheet->setCellValue('A5', '3. Las fechas deben ingresarse estrictamente en formato YYYY-MM-DD (Ejemplo: 2026-01-15).');
        $sheet->setCellValue('A6', '4. Los valores monetarios deben ser numéricos simples, sin símbolos de moneda (Ej: 1500.00).');
        $sheet->setCellValue('A7', '5. Los campos marcados como (Opcional) en la fila de ayuda pueden dejarse en blanco.');
        $sheet->setCellValue('A8', '6. Guarde el archivo en formato de libro de Excel (.xlsx) antes de cargarlo al sistema.');

        $sheet->getColumnDimension('A')->setWidth(90);
    }

    /**
     * Construir hoja de instrucciones para el Libro Maestro
     */
    protected function buildMasterInstructionsContent($sheet): void
    {
        $sheet->setCellValue('A1', 'INSTRUCCIONES DEL LIBRO MAESTRO DE MIGRACIÓN HISTÓRICA');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));

        $sheet->setCellValue('A3', 'Este Libro Maestro contiene pestañas para cada módulo de la base de datos.');
        $sheet->setCellValue('A4', 'El sistema procesará automáticamente las pestañas respetando el orden de dependencias relacionales:');
        $sheet->setCellValue('A5', '   a. Clientes -> Registra los titulares de créditos y transacciones.');
        $sheet->setCellValue('A6', '   b. Créditos Prendarios -> Registra los préstamos asociándolos al cliente por DPI/NIT.');
        $sheet->setCellValue('A7', '   c. Prendas de Garantía -> Asocia los artículos empeñados a su número de crédito.');
        $sheet->setCellValue('A8', '   d. Historial de Pagos -> Registra abonos y cancelaciones históricas.');
        $sheet->setCellValue('A9', '   e. Compras e Inventario -> Registra compras directas de mercadería.');
        $sheet->setCellValue('A10', '   f. Ventas Históricas -> Registra salidas/ventas de inventario.');
        $sheet->setCellValue('A11', '   g. Nomenclatura Contable -> Plan maestro de cuentas.');
        $sheet->setCellValue('A12', '   h. Partidas Contables -> Asientos contables históricos.');
        $sheet->setCellValue('A14', 'REGLAS CLAVE:');
        $sheet->setCellValue('A15', '1. Llene los datos desde la FILA 3 en cada pestaña que desee migrar.');
        $sheet->setCellValue('A16', '2. Si no desea migrar una entidad, puede dejar esa pestaña vacía desde la fila 3.');
        $sheet->setCellValue('A17', '3. No cambie el nombre de las pestañas.');

        $sheet->getColumnDimension('A')->setWidth(95);
    }

    /**
     * Leer filas de una pestaña de un archivo Excel (.xlsx)
     */
    protected function readExcelSheet($sheet, array $expectedHeaders): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // Leer cabeceras (fila 1)
        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = $sheet->getCellByColumnAndRow($col, 1)->getValue();
            if ($val !== null && $val !== '') {
                $headers[] = trim((string)$val);
            }
        }

        // Fila de inicio de datos (normalmente fila 3)
        $dataStartRow = 3;
        $firstCellRow2 = $sheet->getCellByColumnAndRow(1, 2)->getValue();
        if ($firstCellRow2 !== null && !str_starts_with((string)$firstCellRow2, 'Ej:') && !str_starts_with((string)$firstCellRow2, 'Formato:') && !str_starts_with((string)$firstCellRow2, 'DPI')) {
            // Si la fila 2 contiene datos y no texto de ayuda, empezar desde fila 2
            $dataStartRow = 2;
        }

        $rows = [];
        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $rowData = [];
            $isEmpty = true;
            for ($col = 1; $col <= count($headers); $col++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                $strVal = $cellValue !== null ? trim((string)$cellValue) : '';
                $rowData[] = $strVal;
                if ($strVal !== '') {
                    $isEmpty = false;
                }
            }
            if (!$isEmpty) {
                $rows[] = array_combine($headers, $rowData);
            }
        }

        return [$headers, $rows];
    }

    /**
     * Procesar validación preliminar de un archivo Excel
     */
    public function validateImport(string $modelo, string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);

        if ($modelo === 'maestro') {
            return $this->validateMasterImport($spreadsheet);
        }

        if (!isset($this->templates[$modelo])) {
            throw new \Exception("Modelo no soportado: {$modelo}");
        }

        $sheet = $spreadsheet->getActiveSheet();
        [$headers, $rows] = $this->readExcelSheet($sheet, $this->templates[$modelo]);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // Validar cabeceras
        $expectedHeaders = $this->templates[$modelo];
        $diff = array_diff($expectedHeaders, $headers);
        if (!empty($diff)) {
            throw new \Exception("Cabeceras faltantes en el archivo Excel: " . implode(', ', $diff));
        }

        $errors = [];
        $preview = [];
        $maxPreview = 5;

        foreach ($rows as $index => $data) {
            $rowNum = $index + 3; // Considerando ayuda en fila 2
            $rowErrors = $this->validateRow($modelo, $data, $rowNum);

            if (!empty($rowErrors)) {
                $errors[] = [
                    'fila' => $rowNum,
                    'errores' => $rowErrors,
                    'data' => $data
                ];
            }

            if (count($preview) < $maxPreview) {
                $preview[] = $data;
            }
        }

        return [
            'total_filas' => count($rows),
            'errores_count' => count($errors),
            'errores_muestra' => array_slice($errors, 0, 50),
            'preview' => $preview,
            'valido' => empty($errors)
        ];
    }

    /**
     * Validar importación completa de Libro Maestro Multi-Hoja
     */
    protected function validateMasterImport($spreadsheet): array
    {
        $totalFilas = 0;
        $allErrors = [];
        $preview = [];

        $sheetMapping = [
            'Clientes' => 'clientes',
            'Créditos Prendarios' => 'creditos',
            'Prendas de Garantía' => 'prendas',
            'Historial de Pagos' => 'pagos',
            'Compras e Inventario' => 'compras',
            'Ventas Históricas' => 'ventas',
            'Nomenclatura Contable' => 'ctb_nomenclatura',
            'Partidas Contables' => 'ctb_diario',
        ];

        foreach ($sheetMapping as $sheetTitle => $modKey) {
            $sheet = $spreadsheet->getSheetByName($sheetTitle);
            if (!$sheet) continue;

            [$headers, $rows] = $this->readExcelSheet($sheet, $this->templates[$modKey]);
            $totalFilas += count($rows);

            foreach ($rows as $index => $data) {
                $rowNum = $index + 3;
                $rowErrors = $this->validateRow($modKey, $data, $rowNum);

                if (!empty($rowErrors)) {
                    $allErrors[] = [
                        'fila' => "[{$sheetTitle}] Fila {$rowNum}",
                        'errores' => $rowErrors,
                        'data' => $data
                    ];
                }
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'total_filas' => $totalFilas,
            'errores_count' => count($allErrors),
            'errores_muestra' => array_slice($allErrors, 0, 50),
            'preview' => $preview,
            'valido' => empty($allErrors)
        ];
    }

    /**
     * Ejecutar la importación final dentro de una transacción
     */
    public function executeImport(string $modelo, string $filePath, int $userId): MigracionDatosLog
    {
        $log = MigracionDatosLog::create([
            'codigo_lote' => uniqid('MIG_'),
            'usuario_id' => $userId,
            'tabla_destino' => $modelo,
            'archivo_original' => basename($filePath),
            'estado' => 'importando',
            'fecha_inicio' => now(),
            'archivo_ruta' => $filePath
        ]);

        DB::beginTransaction();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $inserted = 0;
            $errors = [];
            $totalRows = 0;

            if ($modelo === 'maestro') {
                $orderedSheets = [
                    'Clientes' => 'clientes',
                    'Créditos Prendarios' => 'creditos',
                    'Prendas de Garantía' => 'prendas',
                    'Historial de Pagos' => 'pagos',
                    'Compras e Inventario' => 'compras',
                    'Ventas Históricas' => 'ventas',
                    'Nomenclatura Contable' => 'ctb_nomenclatura',
                    'Partidas Contables' => 'ctb_diario',
                ];

                foreach ($orderedSheets as $sheetTitle => $modKey) {
                    $sheet = $spreadsheet->getSheetByName($sheetTitle);
                    if (!$sheet) continue;

                    [$headers, $rows] = $this->readExcelSheet($sheet, $this->templates[$modKey]);
                    $rowNum = 0;

                    foreach ($rows as $data) {
                        $rowNum++;
                        $totalRows++;
                        try {
                            $this->importRow($modKey, $data, $userId);
                            $inserted++;
                        } catch (\Exception $e) {
                            $errors[] = "[{$sheetTitle}] Fila " . ($rowNum + 2) . ": " . $e->getMessage();
                        }
                    }
                }
            } else {
                $sheet = $spreadsheet->getActiveSheet();
                [$headers, $rows] = $this->readExcelSheet($sheet, $this->templates[$modelo]);

                $rowNum = 0;
                foreach ($rows as $data) {
                    $rowNum++;
                    $totalRows++;
                    try {
                        $this->importRow($modelo, $data, $userId);
                        $inserted++;
                    } catch (\Exception $e) {
                        $errors[] = "Fila " . ($rowNum + 2) . ": " . $e->getMessage();
                    }
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (!empty($errors)) {
                DB::rollBack();
                $log->update([
                    'estado' => 'error',
                    'fecha_fin' => now(),
                    'errores' => array_slice($errors, 0, 100),
                    'total_filas' => $totalRows,
                    'filas_con_error' => count($errors)
                ]);
                throw new \Exception("La importación falló con " . count($errors) . " errores. Se revirtieron todos los cambios.");
            }

            DB::commit();

            $log->update([
                'estado' => 'completado',
                'fecha_fin' => now(),
                'filas_insertadas' => $inserted,
                'total_filas' => $totalRows,
                'resumen' => ['mensaje' => "Se importaron exitosamente {$inserted} registros."]
            ]);

            return $log;

        } catch (\Exception $e) {
            DB::rollBack();

            $log->update([
                'estado' => 'error',
                'fecha_fin' => now(),
                'observaciones' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Validar una fila según el modelo
     */
    protected function validateRow(string $modelo, array $data, int $rowNum): array
    {
        $rules = [];

        switch ($modelo) {
            case 'clientes':
                $rules = [
                    'dpi' => 'required|string|max:20',
                    'nombres' => 'required|string|max:100',
                    'apellidos' => 'required|string|max:100',
                    'telefono' => 'required|string|max:20',
                    'estado_civil' => 'nullable|in:soltero,casado,divorciado,viudo',
                ];
                break;

            case 'creditos':
                $rules = [
                    'numero_credito' => 'required|string|max:50',
                    'cliente_documento' => 'required|string',
                    'sucursal_codigo' => 'required|string',
                    'monto_solicitado' => 'required|numeric|min:0',
                    'fecha_solicitud' => 'required|date_format:Y-m-d',
                    'estado' => 'required|in:vigente,cancelado,pagado,en_mora',
                ];
                break;

            case 'prendas':
                $rules = [
                    'numero_credito' => 'required|string',
                    'descripcion' => 'required|string|max:255',
                    'valor_tasacion' => 'required|numeric|min:0',
                ];
                break;

            case 'pagos':
                $rules = [
                    'numero_credito' => 'required|string',
                    'numero_recibo' => 'required|string',
                    'fecha_pago' => 'required|date_format:Y-m-d',
                    'monto_total' => 'required|numeric|min:0',
                    'forma_pago' => 'required|in:efectivo,transferencia,deposito,cheque',
                ];
                break;

            case 'compras':
                $rules = [
                    'codigo_compra' => 'required|string',
                    'cliente_nombre' => 'required|string',
                    'sucursal_codigo' => 'required|string',
                    'fecha_compra' => 'required|date_format:Y-m-d',
                    'descripcion' => 'required|string',
                    'monto_pagado' => 'required|numeric|min:0',
                ];
                break;

            case 'ventas':
                $rules = [
                    'numero_factura' => 'required|string',
                    'sucursal_codigo' => 'required|string',
                    'fecha_venta' => 'required|date_format:Y-m-d',
                    'total_final' => 'required|numeric|min:0',
                    'forma_pago' => 'required|string',
                ];
                break;

            case 'ctb_nomenclatura':
                $rules = [
                    'codigo_cuenta' => 'required|string',
                    'nombre_cuenta' => 'required|string',
                    'tipo' => 'required|in:activo,pasivo,capital,ingreso,gasto',
                    'naturaleza' => 'required|in:deudora,acreedora',
                    'nivel' => 'required|integer|min:1|max:5',
                    'acepta_movimientos' => 'required|in:0,1',
                ];
                break;

            case 'ctb_diario':
                $rules = [
                    'numero_comprobante' => 'required|string',
                    'fecha_contabilizacion' => 'required|date_format:Y-m-d',
                    'codigo_cuenta' => 'required|string',
                    'monto_debe' => 'required|numeric|min:0',
                    'monto_haber' => 'required|numeric|min:0',
                    'sucursal_codigo' => 'required|string',
                ];
                break;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return $validator->errors()->all();
        }

        return [];
    }

    /**
     * Importar una fila para un modelo específico
     */
    protected function importRow(string $modelo, array $data, int $userId): void
    {
        switch ($modelo) {
            case 'clientes':
                Cliente::updateOrCreate(
                    ['dpi' => $data['dpi']],
                    [
                        'nit' => $data['nit'] ?? null,
                        'nombres' => $data['nombres'],
                        'apellidos' => $data['apellidos'],
                        'telefono' => $data['telefono'] ?? null,
                        'email' => $data['email'] ?? null,
                        'direccion' => $data['direccion'] ?? null,
                        'municipio' => $data['municipio'] ?? null,
                        'estado_civil' => $data['estado_civil'] ?? 'soltero',
                        'profesion' => $data['profesion'] ?? null,
                        'estado' => 'activo',
                    ]
                );
                break;

            case 'creditos':
                $doc = trim($data['cliente_documento']);
                $cliente = Cliente::where('dpi', $doc)->orWhere('nit', $doc)->orWhere('codigo_cliente', $doc)->first();
                if (!$cliente) {
                    throw new \Exception("Cliente no encontrado con documento: {$doc}");
                }

                $sucursalCode = trim($data['sucursal_codigo']);
                $sucursal = Sucursal::where('codigo', $sucursalCode)->first();
                if (!$sucursal) {
                    throw new \Exception("Sucursal no encontrada con código: {$sucursalCode}");
                }

                $analista = !empty($data['analista_username'])
                    ? User::where('username', trim($data['analista_username']))->first()
                    : null;

                CreditoPrendario::updateOrCreate(
                    ['numero_credito' => trim($data['numero_credito'])],
                    [
                        'cliente_id' => $cliente->id,
                        'sucursal_id' => $sucursal->id,
                        'analista_id' => $analista ? $analista->id : $userId,
                        'cajero_id' => $userId,
                        'fecha_solicitud' => $data['fecha_solicitud'],
                        'fecha_aprobacion' => $data['fecha_aprobacion'] ?? $data['fecha_solicitud'],
                        'fecha_desembolso' => $data['fecha_desembolso'] ?? $data['fecha_solicitud'],
                        'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                        'monto_solicitado' => $data['monto_solicitado'],
                        'monto_aprobado' => $data['monto_aprobado'] ?? $data['monto_solicitado'],
                        'monto_desembolsado' => $data['monto_aprobado'] ?? $data['monto_solicitado'],
                        'capital_pendiente' => $data['monto_aprobado'] ?? $data['monto_solicitado'],
                        'tasa_interes' => $data['tasa_interes'] ?? 0,
                        'plazo_dias' => $data['plazo_dias'] ?? 30,
                        'estado' => $data['estado'],
                        'observaciones' => $data['observaciones'] ?? null,
                        'afecta_interes_mensual' => true,
                        'permite_pago_capital_diferente' => true,
                        'requiere_renovacion' => false,
                    ]
                );
                break;

            case 'prendas':
                $numCredito = trim($data['numero_credito']);
                $credito = CreditoPrendario::where('numero_credito', $numCredito)->first();
                if (!$credito) {
                    throw new \Exception("Crédito no encontrado con número: {$numCredito}");
                }

                Prenda::create([
                    'credito_prendario_id' => $credito->id,
                    'descripcion' => $data['descripcion'],
                    'categoria' => $data['categoria'] ?? 'General',
                    'marca' => $data['marca'] ?? null,
                    'modelo' => $data['modelo'] ?? null,
                    'serie' => $data['serie'] ?? null,
                    'estado_conservacion' => $data['estado_conservacion'] ?? 'Bueno',
                    'valor_tasacion' => $data['valor_tasacion'] ?? 0,
                    'observaciones' => $data['observaciones'] ?? null,
                    'estado' => 'custodia'
                ]);
                break;

            case 'pagos':
                $numCredito = trim($data['numero_credito']);
                $credito = CreditoPrendario::where('numero_credito', $numCredito)->first();
                if (!$credito) {
                    throw new \Exception("Crédito no encontrado para el pago: {$numCredito}");
                }

                CreditoMovimiento::create([
                    'credito_prendario_id' => $credito->id,
                    'usuario_id' => $userId,
                    'sucursal_id' => $credito->sucursal_id,
                    'numero_movimiento' => uniqid('MOV_'),
                    'numero_recibo' => trim($data['numero_recibo']),
                    'tipo_movimiento' => 'pago',
                    'fecha_movimiento' => $data['fecha_pago'],
                    'fecha_registro' => now(),
                    'monto_total' => $data['monto_total'],
                    'capital' => $data['capital'] ?? 0,
                    'interes' => $data['interes'] ?? 0,
                    'mora' => $data['mora'] ?? 0,
                    'observaciones' => $data['observaciones'] ?? 'Migración histórica de pago'
                ]);

                // Actualizar saldos en el crédito
                $capitalAbonado = floatval($data['capital'] ?? 0);
                if ($capitalAbonado > 0) {
                    $credito->capital_pendiente = max(0, $credito->capital_pendiente - $capitalAbonado);
                    $credito->capital_pagado = ($credito->capital_pagado ?? 0) + $capitalAbonado;
                    if ($credito->capital_pendiente == 0) {
                        $credito->estado = 'pagado';
                    }
                    $credito->save();
                }
                break;

            case 'compras':
                $sucursalCode = trim($data['sucursal_codigo']);
                $sucursal = Sucursal::where('codigo', $sucursalCode)->first();
                if (!$sucursal) {
                    throw new \Exception("Sucursal no encontrada: {$sucursalCode}");
                }

                $doc = trim($data['cliente_documento'] ?? '');
                $cliente = $doc !== '' ? Cliente::where('dpi', $doc)->orWhere('nit', $doc)->first() : null;

                Compra::create([
                    'codigo_compra' => trim($data['codigo_compra']),
                    'cliente_id' => $cliente ? $cliente->id : null,
                    'sucursal_id' => $sucursal->id,
                    'usuario_id' => $userId,
                    'cliente_nombre' => $data['cliente_nombre'],
                    'cliente_documento' => $doc,
                    'categoria_nombre' => $data['categoria_nombre'] ?? 'General',
                    'descripcion' => $data['descripcion'],
                    'marca' => $data['marca'] ?? null,
                    'modelo' => $data['modelo'] ?? null,
                    'serie' => $data['serie'] ?? null,
                    'valor_tasacion' => $data['valor_tasacion'] ?? 0,
                    'monto_pagado' => $data['monto_pagado'] ?? 0,
                    'precio_venta_sugerido' => $data['precio_venta_sugerido'] ?? 0,
                    'fecha_compra' => $data['fecha_compra'],
                    'estado' => 'completada',
                    'observaciones' => $data['observaciones'] ?? null,
                ]);
                break;

            case 'ventas':
                $sucursalCode = trim($data['sucursal_codigo']);
                $sucursal = Sucursal::where('codigo', $sucursalCode)->first();
                if (!$sucursal) {
                    throw new \Exception("Sucursal no encontrada: {$sucursalCode}");
                }

                $doc = trim($data['cliente_documento'] ?? '');
                $cliente = $doc !== '' ? Cliente::where('dpi', $doc)->orWhere('nit', $doc)->first() : null;

                Venta::create([
                    'numero_factura' => trim($data['numero_factura']),
                    'cliente_id' => $cliente ? $cliente->id : null,
                    'sucursal_id' => $sucursal->id,
                    'usuario_id' => $userId,
                    'fecha_venta' => $data['fecha_venta'],
                    'subtotal' => $data['total_final'],
                    'total_final' => $data['total_final'],
                    'total_pagado' => $data['total_final'],
                    'tipo_venta' => 'contado',
                    'estado' => 'completada',
                    'observaciones' => $data['observaciones'] ?? 'Migración histórica de venta'
                ]);
                break;

            case 'ctb_nomenclatura':
                $padreCodigo = !empty($data['cuenta_padre_codigo']) ? trim($data['cuenta_padre_codigo']) : null;
                $padreId = null;
                if ($padreCodigo) {
                    $padre = CtbNomenclatura::where('codigo_cuenta', $padreCodigo)->first();
                    $padreId = $padre ? $padre->id : null;
                }

                CtbNomenclatura::updateOrCreate(
                    ['codigo_cuenta' => trim($data['codigo_cuenta'])],
                    [
                        'nombre_cuenta' => $data['nombre_cuenta'],
                        'tipo' => strtolower($data['tipo']),
                        'naturaleza' => strtolower($data['naturaleza']),
                        'nivel' => intval($data['nivel']),
                        'cuenta_padre_id' => $padreId,
                        'acepta_movimientos' => boolval($data['acepta_movimientos']),
                        'estado' => true,
                    ]
                );
                break;

            case 'ctb_diario':
                $numComprobante = trim($data['numero_comprobante']);
                $diario = CtbDiario::firstOrCreate(
                    ['numero_comprobante' => $numComprobante],
                    [
                        'glosa' => $data['glosa'] ?? 'Migración de Partida Contable',
                        'fecha_documento' => $data['fecha_contabilizacion'],
                        'fecha_contabilizacion' => $data['fecha_contabilizacion'],
                        'usuario_id' => $userId,
                        'estado' => 'aprobado',
                    ]
                );

                $cuentaCode = trim($data['codigo_cuenta']);
                $cuenta = CtbNomenclatura::where('codigo_cuenta', $cuentaCode)->first();
                if (!$cuenta) {
                    throw new \Exception("Cuenta contable no encontrada con código: {$cuentaCode}");
                }

                CtbMovimiento::create([
                    'diario_id' => $diario->id,
                    'cuenta_contable_id' => $cuenta->id,
                    'monto_debe' => floatval($data['monto_debe'] ?? 0),
                    'monto_haber' => floatval($data['monto_haber'] ?? 0),
                    'concepto' => $data['concepto'] ?? $data['glosa'] ?? 'Movimiento histórico',
                ]);
                break;
        }
    }
}
