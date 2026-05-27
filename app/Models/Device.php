<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'imei',
        'customer_id',
        'gas_level_pct',
        'cylinder_size_kg',
        'valve_open',
        'last_seen',
        'mqtt_topic',
        'latitude',
        'longitude',
        'status',
        'firmware_version',
        'is_tampered',
        'last_tampered_at',
    ];

    protected $casts = [
        'gas_level_pct'    => 'decimal:2',
        'cylinder_size_kg' => 'decimal:2',
        'valve_open'       => 'boolean',
        'last_seen'        => 'datetime',
        'last_tampered_at' => 'datetime',
        'latitude'         => 'decimal:8',
        'longitude'        => 'decimal:8',
        'is_tampered'      => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function readings()
    {
        return $this->hasMany(DeviceReading::class);
    }

    public function latestReading()
    {
        return $this->hasOne(DeviceReading::class)->latestOfMany();
    }

    public function tamperEvents()
    {
        return $this->hasMany(DeviceTamperEvent::class);
    }

    public function latestTamperEvent()
    {
        return $this->hasOne(DeviceTamperEvent::class)->latestOfMany();
    }
}
