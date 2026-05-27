<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceReading extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'gas_level_pct',
        'weight_kg',
        'temperature',
        'battery_voltage',
        'rssi',
        'created_at',
    ];

    protected $casts = [
        'gas_level_pct' => 'decimal:2',
        'weight_kg' => 'decimal:3',
        'temperature' => 'decimal:2',
        'battery_voltage' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
