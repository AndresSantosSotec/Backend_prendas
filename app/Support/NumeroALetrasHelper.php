<?php

namespace App\Support;

use Carbon\Carbon;

class NumeroALetrasHelper
{
    private static array $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
    private static array $decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    private static array $especiales = [
        10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
        16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
        20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés', 24 => 'veinticuatro',
        25 => 'veinticinco', 26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve'
    ];
    private static array $centenas = [
        '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
        'seiscientos', 'setecientos', 'ochocientos', 'novecientos'
    ];

    public static function convertir(int|float $number): string
    {
        $number = (int) round($number);
        if ($number === 0) return 'cero';
        if ($number < 0) return 'menos ' . self::convertir(abs($number));

        $letras = '';

        if ($number >= 1000000) {
            $millones = floor($number / 1000000);
            $resto = $number % 1000000;
            if ($millones == 1) {
                $letras .= 'un millón ';
            } else {
                $letras .= self::convertir($millones) . ' millones ';
            }
            $number = $resto;
        }

        if ($number >= 1000) {
            $miles = floor($number / 1000);
            $resto = $number % 1000;
            if ($miles == 1) {
                $letras .= 'mil ';
            } else {
                $letras .= self::convertir($miles) . ' mil ';
            }
            $number = $resto;
        }

        if ($number >= 100) {
            $cents = floor($number / 100);
            $resto = $number % 100;
            if ($cents == 1 && $resto == 0) {
                $letras .= 'cien ';
            } else {
                $letras .= self::$centenas[$cents] . ' ';
            }
            $number = $resto;
        }

        if ($number > 0) {
            if (isset(self::$especiales[$number])) {
                $letras .= self::$especiales[$number];
            } else {
                $decs = floor($number / 10);
                $units = $number % 10;
                if ($decs > 0) {
                    $letras .= self::$decenas[$decs];
                    if ($units > 0) {
                        $letras .= ' y ' . self::$unidades[$units];
                    }
                } else {
                    $letras .= self::$unidades[$units];
                }
            }
        }

        return trim($letras);
    }

    public static function montoEnLetras(float $monto, string $moneda = 'QUETZALES'): string
    {
        $enteros = (int) floor($monto);
        $centavos = (int) round(($monto - $enteros) * 100);

        $texto = strtoupper(self::convertir($enteros));

        if ($centavos > 0) {
            return "{$texto} {$moneda} CON " . strtoupper(self::convertir($centavos)) . " CENTAVOS (Q." . number_format($monto, 2) . ")";
        }

        return "{$texto} {$moneda} EXACTOS (Q." . number_format($monto, 2) . ")";
    }

    public static function cuiEnLetras(?string $dpi): string
    {
        if (empty($dpi)) return '';
        $raw = preg_replace('/[^0-9]/', '', $dpi);
        if (strlen($raw) !== 13) {
            return $dpi;
        }

        $p1 = (int) substr($raw, 0, 4);
        $p2 = (int) substr($raw, 4, 5);
        $p3Str = substr($raw, 9, 4);

        $t1 = ucfirst(self::convertir($p1));
        $t2 = self::convertir($p2);

        $t3 = '';
        if (str_starts_with($p3Str, '0')) {
            $zeros = 0;
            while (isset($p3Str[$zeros]) && $p3Str[$zeros] === '0') {
                $zeros++;
            }
            $t3 .= str_repeat('cero ', $zeros);
            $rem = (int) substr($p3Str, $zeros);
            if ($rem > 0) {
                $t3 .= self::convertir($rem);
            }
        } else {
            $t3 = self::convertir((int) $p3Str);
        }

        $formateado = substr($raw, 0, 4) . ' ' . substr($raw, 4, 5) . ' ' . substr($raw, 9, 4);
        return trim($t1) . ', ' . trim($t2) . ', ' . trim($t3) . " ({$formateado})";
    }

    public static function fechaEnLetras($fecha = null): string
    {
        $f = $fecha ? Carbon::parse($fecha) : Carbon::now();
        $dia = $f->day === 1 ? 'primero' : self::convertir($f->day);
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        $mes = $meses[$f->month] ?? 'enero';
        $anio = self::convertir($f->year);

        return "{$dia} de {$mes} de {$anio}";
    }
}
