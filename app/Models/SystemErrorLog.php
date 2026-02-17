<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemErrorLog extends Model
{
    use HasFactory;

    protected $table = 'system_error_logs';

    protected $fillable = [
        'user_id',
        'message',
        'exception',
        'file',
        'line',
        'trace',
        'url',
        'method',
        'ip_address',
        'user_agent',
        'input_data',
        'status_code',
    ];

    protected $casts = [
        'trace' => 'array',
        'input_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
