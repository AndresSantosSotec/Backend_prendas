<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    /**
     * Get recent laravel.log lines
     */
    public function __invoke(Request $request)
    {
        $path = storage_path('logs/laravel.log');
        
        if (!File::exists($path)) {
            return response()->json(['success' => true, 'data' => 'Log file not found at '.$path]);
        }

        $lines_to_return = $request->input('lines', 500);

        try {
            $file = new \SplFileObject($path, 'r');
            $file->seek(PHP_INT_MAX);
            $total_lines = $file->key();

            $output = '';
            $start = max(0, $total_lines - $lines_to_return);

            $file->seek($start);
            while (!$file->eof()) {
                $output .= $file->current();
                $file->next();
            }

            return response()->json([
                'success' => true,
                'data' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reading log file: ' . $e->getMessage()
            ], 500);
        }
    }
}
