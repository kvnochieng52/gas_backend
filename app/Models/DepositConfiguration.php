<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositConfiguration extends Model
{
    protected $fillable = [
        'name',
        'amount',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerDeposits()
    {
        return $this->hasMany(CustomerDeposit::class, 'deposit_config_id');
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->latest()->first();
    }
}
