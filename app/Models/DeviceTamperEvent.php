<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceTamperEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'customer_id',
        'event_type',
        'gas_level_pct',
        'description',
        'resolved',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'gas_level_pct' => 'decimal:2',
        'resolved'      => 'boolean',
        'resolved_at'   => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
