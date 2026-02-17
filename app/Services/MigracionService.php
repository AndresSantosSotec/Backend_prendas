<?php

namespace App\Services;

use App\Models\CreditoPrendario;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
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
     * Definición de columnas esperadas por modelo
     */
    protected $templates = [
        'creditos' => [
            'numero_credito', 'cliente_documento', 'sucursal_codigo', 'analista_username',
            'fecha_solicitud', 'fecha_aprobacion', 'fecha_desembolso', 'fecha_vencimiento',
            'monto_solicitado', 'monto_aprobado', 'tasa_interes', 'plazo_dias', 'estado', 'observaciones'
        ],
        'prendas' => [
            'numero_credito', 'descripcion', 'categoria', 'marca', 'modelo', 'serie',
            'estado_conservacion', 'valor_tasacion', 'observaciones'
        ],
        // ... otros modelos
    ];

    /**
     * Descripciones de columnas por modelo (para la fila de ayuda)
     */
    protected $columnDescriptions = [
        'creditos' => [
            'numero_credito' => 'Ej: CP-001 (Único)',
            'cliente_documento' => 'DPI o NIT del cliente',
            'sucursal_codigo' => 'Código de sucursal (Ej: SUC01)',
            'analista_username' => 'Usuario del analista',
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
            'categoria' => 'Ej: Joyería, Electrónica',
            'marca' => 'Marca (Opcional)',
            'modelo' => 'Modelo (Opcional)',
            'serie' => 'Número de serie (Opcional)',
            'estado_conservacion' => 'Excelente|Bueno|Regular|Malo',
            'valor_tasacion' => 'Valor numérico (Ej: 2500.00)',
            'observaciones' => 'Texto libre (Opcional)',
        ],
        'clientes' => [
            'numero_documento' => 'DPI o NIT',
            'nombre' => 'Nombre completo',
            'telefono' => 'Número de teléfono',
            'email' => 'Correo electrónico (Opcional)',
            'direccion' => 'Dirección (Opcional)',
        ],
    ];

    /**
     * Generar plantilla Excel (.xlsx) para un modelo
     */
    public function generateTemplate(string $modelo): string
    {
        if (!isset($this->templates[$modelo])) {
            throw new \Exception("Modelo no soportado para migración: {$modelo}");
        }

        $headers = $this->templates[$modelo];
        $descriptions = $this->columnDescriptions[$modelo] ?? [];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Datos');

        // Fila 1: Cabeceras con estilo
        foreach ($headers as $colIndex => $header) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cell = $col . '1';
            $sheet->setCellValue($cell, $header);

            // Estilo de cabecera
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Auto-ancho
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Fila 2: Descripciones/ayuda (gris claro)
        foreach ($headers as $colIndex => $header) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cell = $col . '2';
            $desc = $descriptions[$header] ?? '';
            $sheet->setCellValue($cell, $desc);

            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
        }

        // Hoja de instrucciones
        $instrSheet = $spreadsheet->createSheet();
        $instrSheet->setTitle('Instrucciones');
        $instrSheet->setCellValue('A1', 'INSTRUCCIONES DE LLENADO');
        $instrSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instrSheet->setCellValue('A3', '1. Llene los datos a partir de la FILA 3 de la hoja "Datos".');
        $instrSheet->setCellValue('A4', '2. NO modifique las cabeceras (Fila 1) ni la fila de ayuda (Fila 2).');
        $instrSheet->setCellValue('A5', '3. Las fechas deben tener formato YYYY-MM-DD (Ej: 2026-01-15).');
        $instrSheet->setCellValue('A6', '4. Los montos deben ser numéricos, sin símbolos de moneda.');
        $instrSheet->setCellValue('A7', '5. Los campos marcados como "Opcional" pueden dejarse vacíos.');
        $instrSheet->setCellValue('A8', '6. Guarde el archivo como .xlsx antes de subirlo al sistema.');
        $instrSheet->getColumnDimension('A')->setWidth(80);

        // Activar la hoja de datos
        $spreadsheet->setActiveSheetIndex(0);

        // Guardar en archivo temporal
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
     * Leer filas de un archivo Excel (.xlsx)
     * Retorna [headers, rows] donde rows es un array de arrays asociativos
     */
    protected function readExcelFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
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

        // Determinar fila de inicio de datos (fila 3 si hay descripciones, fila 2 si no)
        $dataStartRow = 3; // Nuestras plantillas tienen descripciones en fila 2
        // Si la fila 2 parece ser datos y no descripciones, empezar desde fila 2
        $firstCellRow2 = $sheet->getCellByColumnAndRow(1, 2)->getValue();
        if ($firstCellRow2 !== null && !str_starts_with((string)$firstCellRow2, 'Ej:') && !str_starts_with((string)$firstCellRow2, 'Formato:')) {
            // Verificar si parece ser una descripción o dato real
            $descriptions = $this->columnDescriptions[$headers[0] ?? ''] ?? [];
            if (empty($descriptions)) {
                $dataStartRow = 2;
            }
        }

        $rows = [];
        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $rowData = [];
            $isEmpty = true;
            for ($col = 1; $col <= count($headers); $col++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                $rowData[] = $cellValue !== null ? trim((string)$cellValue) : '';
                if ($cellValue !== null && $cellValue !== '') {
                    $isEmpty = false;
                }
            }
            if (!$isEmpty) {
                $rows[] = array_combine($headers, $rowData);
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [$headers, $rows];
    }

    /**
     * Procesar validación preliminar de archivo Excel
     */
    public function validateImport(string $modelo, string $filePath): array
    {
        if (!isset($this->templates[$modelo])) {
            throw new \Exception("Modelo no soportado");
        }

        [$headers, $rows] = $this->readExcelFile($filePath);

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
            $rowNum = $index + 1;

            // Validar fila
            $rowErrors = $this->validateRow($modelo, $data, $rowNum);

            if (!empty($rowErrors)) {
                $errors[] = [
                    'fila' => $rowNum,
                    'errores' => $rowErrors,
                    'data' => $data
                ];
            }

            if ($rowNum <= $maxPreview) {
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
     * Ejecutar la importación
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
            [$headers, $rows] = $this->readExcelFile($filePath);
            $inserted = 0;
            $updated = 0;
            $errors = [];
            $rowCount = 0;

            foreach ($rows as $data) {
                $rowCount++;

                try {
                    $this->importRow($modelo, $data);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = "Fila {$rowCount}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                // Si hay errores críticos, revertimos todo
                 DB::rollBack();
                 $log->update([
                     'estado' => 'error',
                     'fecha_fin' => now(),
                     'errores' => array_slice($errors, 0, 100), // Guardar primeros 100 errores
                     'total_filas' => $rowCount,
                     'filas_con_error' => count($errors)
                 ]);
                 throw new \Exception("La importación falló con " . count($errors) . " errores. Se ha revertido el proceso.");
            }

            DB::commit();

            $log->update([
                'estado' => 'completado',
                'fecha_fin' => now(),
                'filas_insertadas' => $inserted,
                'total_filas' => $rowCount,
                'resumen' => ['mensaje' => "Se importaron {$inserted} registros correctamente."]
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
     * Validar una fila específica según el modelo
     */
    protected function validateRow(string $modelo, array $data, int $rowNum): array
    {
        $errors = [];

        if ($modelo === 'creditos') {
            $validator = Validator::make($data, [
                'numero_credito' => 'required|unique:creditos_prendarios,numero_credito',
                'cliente_documento' => 'required|exists:clientes,numero_documento', // Asumiendo campo
                'monto_solicitado' => 'required|numeric|min:0',
                'sucursal_codigo' => 'required|exists:sucursales,codigo',
                'fecha_solicitud' => 'required|date_format:Y-m-d',
                'estado' => 'required|in:vigente,cancelado,pagado,en_mora'
            ]);

            if ($validator->fails()) {
                return $validator->errors()->all();
            }
        } elseif ($modelo === 'prendas') {
             $validator = Validator::make($data, [
                'numero_credito' => 'required|exists:creditos_prendarios,numero_credito',
                'descripcion' => 'required|string',
                'valor_tasacion' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return $validator->errors()->all();
            }
        }

        // Validaciones lógicas adicionales
        // Check references manually to cache lookup if needed for performance

        return $errors;
    }

    /**
     * Importar una fila
     */
    protected function importRow(string $modelo, array $data)
    {
        if ($modelo === 'creditos') {
            // Resolver IDs
            $cliente = Cliente::where('numero_documento', $data['cliente_documento'])->firstOrFail();
            $sucursal = Sucursal::where('codigo', $data['sucursal_codigo'])->firstOrFail();
            $analista = isset($data['analista_username'])
                ? User::where('username', $data['analista_username'])->first()
                : null;

            CreditoPrendario::create([
                'numero_credito' => $data['numero_credito'],
                'cliente_id' => $cliente->id,
                'sucursal_id' => $sucursal->id,
                'analista_id' => $analista ? $analista->id : null,
                'fecha_solicitud' => $data['fecha_solicitud'],
                'fecha_aprobacion' => $data['fecha_aprobacion'] ?? null,
                'fecha_desembolso' => $data['fecha_desembolso'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'monto_solicitado' => $data['monto_solicitado'],
                'monto_aprobado' => $data['monto_aprobado'] ?? $data['monto_solicitado'],
                'tasa_interes' => $data['tasa_interes'] ?? 0,
                'plazo_dias' => $data['plazo_dias'] ?? 30,
                'estado' => $data['estado'],
                'observaciones' => $data['observaciones'] ?? null,
                // Valores por defecto requeridos
                'monto_desembolsado' => $data['monto_aprobado'] ?? 0,
                'capital_pendiente' => $data['monto_aprobado'] ?? 0,
                'afecta_interes_mensual' => true,
                'permite_pago_capital_diferente' => true,
                'requiere_renovacion' => false
            ]);
        } elseif ($modelo === 'prendas') {
             // Lógica para prendas
             $credito = CreditoPrendario::where('numero_credito', $data['numero_credito'])->firstOrFail();

             \App\Models\Prenda::create([
                 'credito_prendario_id' => $credito->id,
                 'descripcion' => $data['descripcion'],
                 'categoria' => $data['categoria'] ?? 'General',
                 'marca' => $data['marca'] ?? null,
                 'modelo' => $data['modelo'] ?? null,
                 'serie' => $data['serie'] ?? null,
                 'estado_conservacion' => $data['estado_conservacion'] ?? 'Bueno',
                 'valor_tasacion' => $data['valor_tasacion'] ?? 0,
                 'observaciones' => $data['observaciones'] ?? null,
                 'estado' => 'custodia' // Estado por defecto al migrar
             ]);
        }
    }
}
