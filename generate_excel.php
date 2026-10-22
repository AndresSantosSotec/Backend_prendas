<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$raw = <<<TXT
9999900000001;;CARMEN ODILIA;VILLATORO HERRERA;00000000;;;;;;
9999900000002;;ANGELA PEDRO;ESTABAN DE TOMAS;00000000;;;;;;
9999900000003;;SAIDA MARLENE;CARILLO SUTUC;00000000;;;;;;
9999900000004;;ANGELA ISABEL;MARTIN OSORIO;00000000;;;;;;
9999900000005;;NICOLASA NOHEMI;GRACIELA MARTIN OSORIO;00000000;;;;;;
9999900000006;;SAMARA NOHEMI;GARCIA MARTINEZ;00000000;;;;;;
9999900000007;;CRECENCIA ISABEL OSORIO;LUX DE MARTIN;00000000;;;;;;
9999900000008;;JOSUE DANIEL;RAMOS ESTEBAN;00000000;;;;;;
9999900000009;;WENDY FERNANDA;BARRIOS SCHAART;00000000;;;;;;
9999900000010;;ESTUARDO VICTOR;AZ CALEL;00000000;;;;;;
9999900000011;;JENNIFER;PEREZ GONZALEZ;00000000;;;;;;
9999900000012;;MARIO DAVID;CALDERON MARTINEZ;00000000;;;;;;
9999900000013;;AIDA MATIAS;ADRIANA VICENTE VICENTE;00000000;;;;;;
9999900000014;;ELSY EUGENIA GUTIERREZ;VASQUEZ DE GONZALEZ;00000000;;;;;;
9999900000015;;DALIZ ARMIDA;MONTEJO LOPEZ;00000000;;;;;;
9999900000016;;DENNIS RIKABDO;LOPEZ MENDEZ;00000000;;;;;;
9999900000017;;PETRONA GONZÁLEZ;SANTIAGO DE VELÁSQUEZ;00000000;;;;;;
9999900000018;;VERONICA ANTONIA;DOMINGO RAMIREZ;00000000;;;;;;
9999900000019;;JEISSON GIOVANI;GOMEZ GONZALEZ;00000000;;;;;;
9999900000020;;PETRONA;TOMÁS ANTONIO;00000000;;;;;;
9999900000021;;ALVARO AVARISTO;GOMEZ BARRIOS;00000000;;;;;;
9999900000022;;ANA MARIA MARTINEZ;RODRIGUEZ DE PORTILLO;00000000;;;;;;
9999900000023;;LUIS FELIPE;PINEDA RECIUNCOY;00000000;;;;;;
9999900000024;;VICENTE JOSELINO;MARTIN OSORIO;00000000;;;;;;
9999900000025;;EDVIN EDUARDO;VELASQUEZ DÍAZ;00000000;;;;;;
9999900000026;;CARLOS CEFERINO;GARCIA GARCIA;00000000;;;;;;
9999900000027;;JESUS FERNANDO;CAPRIEL TZARAX;00000000;;;;;;
9999900000028;;MARIA;DOMINGO VICENTE;00000000;;;;;;
9999900000029;;HEIDY PAOLA;RAMIREZ PEREZ;00000000;;;;;;
9999900000030;;ALEJANDRO ERICKSON;ANIBAL MORALES HERRERA;00000000;;;;;;
9999900000031;;MARLÓN JOEL;CASTILLO PÉREZ;00000000;;;;;;
9999900000032;;JONATHAN;EMMANUEL RIVERA;00000000;;;;;;
9999900000033;;CRISTIAN JOSE;ALVARDO CALDERON;00000000;;;;;;
9999900000034;;MELVIN ALEXANDER;MARTINEZ ORDOÑEZ;00000000;;;;;;
9999900000035;;CRISTINA YOLANDA;ANTONIO TOMAS;00000000;;;;;;
9999900000036;;EDGAR LEONEL;CLAUDIO PEREZ;00000000;;;;;;
9999900000037;;JUAN ABELARDO;ALVARADO LUCAS;00000000;;;;;;
9999900000038;;SELVIN ADEMAR;GARCIA PEREZ;00000000;;;;;;
9999900000039;;DENILSON MIGUEL;RIVAS RIVAS;00000000;;;;;;
9999900000040;;BARBARA CAROLINA;MORALES HERNANDEZ;00000000;;;;;;
9999900000041;;CARLOS URIELL;LOPEZ HERNANDEZ;00000000;;;;;;
9999900000042;;JAQUELINE LETONA;DE PAZ;00000000;;;;;;
9999900000043;;ESTHER FLORY;PU SACVIN;00000000;;;;;;
9999900000044;;ANGELINA;SEBATIAN FRANCISCO;00000000;;;;;;
9999900000045;;YOSELIN DE LOS;ANGELES LOPEZ SICA;00000000;;;;;;
9999900000046;;JORGE ALEXANDER;VALLECIOS ENRIQUEZ;00000000;;;;;;
9999900000047;;MAGNOLIA CECILIA;RIVAS CARILLO;00000000;;;;;;
9999900000048;;GABINO ALVARO;PEREZ RAMIREZ;00000000;;;;;;
9999900000049;;DENIS ESTUARDO;PALACIOS MATIAS;00000000;;;;;;
9999900000050;;CESAR OVIDIO;CHIVALAN LUX;00000000;;;;;;
9999900000051;;LUIS ALBERTO;GUTIERREZ GOMEZ;00000000;;;;;;
9999900000052;;CARLOS ENRIQUE;LOPEZ MENDOZA;00000000;;;;;;
9999900000053;;CONCEPCION AZUCENA;ORDOÑEZ CARILLO;00000000;;;;;;
9999900000054;;THELMA CRISTINA;CUCUL CHAMAM;00000000;;;;;;
9999900000055;;JEREMY MOISES;VILLATORO GOMEZ;00000000;;;;;;
9999900000056;;JOSE;JUAN MATEO;00000000;;;;;;
9999900000057;;WENER JOSE;GILBERTO CORTEZ JUC;00000000;;;;;;
9999900000058;;ROLDAN JOSE;DOMINGO AGUILAR;00000000;;;;;;
9999900000059;;MACK ALEXANDER;GONZALEZ CARRION;00000000;;;;;;
9999900000060;;TOMAS EZEQUIEL;GASPAR PELICO;00000000;;;;;;
9999900000061;;MONICA ESTHER SERRANO;SANTOS DE AGUILAR;00000000;;;;;;
9999900000062;;EUNICE CORINA DE;LA CRUZ HERRRERA;00000000;;;;;;
TXT;

$headers = ['dpi', 'nit', 'nombres', 'apellidos', 'telefono', 'email', 'direccion', 'municipio', 'departamento', 'estado_civil', 'profesion'];
$descriptions = [
    'DPI o CUI del cliente (13 dígitos)',
    'NIT sin guiones (Opcional)',
    'Nombres del cliente',
    'Apellidos del cliente',
    'Teléfono principal',
    'Correo electrónico (Opcional)',
    'Dirección de residencia (Opcional)',
    'Municipio de residencia',
    'Departamento de residencia',
    'soltero|casado|divorciado|viudo',
    'Profesión u oficio (Opcional)'
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Datos');

// Fila 1: Cabeceras
foreach ($headers as $colIdx => $h) {
    $col = $colIdx + 1;
    $sheet->setCellValueByColumnAndRow($col, 1, $h);
}

// Estilo cabecera
$sheet->getStyle('A1:K1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
$sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('1E40AF');

// Fila 2: Descripciones
foreach ($descriptions as $colIdx => $d) {
    $col = $colIdx + 1;
    $sheet->setCellValueByColumnAndRow($col, 2, $d);
}
$sheet->getStyle('A2:K2')->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
$sheet->getStyle('A2:K2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F1F5F9');

// Fila 3 en adelante: Datos
$lines = explode("\n", trim($raw));
$rowNum = 3;
foreach ($lines as $line) {
    $parts = explode(';', trim($line));
    foreach ($parts as $colIdx => $val) {
        $col = $colIdx + 1;
        $sheet->setCellValueExplicitByColumnAndRow($col, $rowNum, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }
    $rowNum++;
}

foreach (range('A', 'K') as $colLetter) {
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Hoja Instrucciones
$instrSheet = $spreadsheet->createSheet();
$instrSheet->setTitle('Instrucciones');
$instrSheet->setCellValue('A1', 'INSTRUCCIONES DE MIGRACIÓN - CLIENTES');
$instrSheet->setCellValue('A3', '1. Complete los datos a partir de la FILA 3 de la pestaña "Datos".');
$instrSheet->setCellValue('A4', '2. NO modifique los nombres de las cabeceras (Fila 1) ni elimine la fila de ayuda (Fila 2).');
$instrSheet->setCellValue('A5', '3. Guarde el archivo como .xlsx.');

$spreadsheet->setActiveSheetIndex(0);

$target = dirname(__DIR__) . '/Docs/clientes_migracion_listo.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($target);
echo "Archivo generado con éxito en: {$target}\n";
