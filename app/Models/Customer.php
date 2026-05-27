<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'account_no',
        'name',
        'phone',
        'email',
        'address',
        'agent_id',
        'rate_plan_id',
        'credit_balance',
        'is_active',
        'pin',
    ];

    protected $hidden = ['pin'];

    protected $casts = [
        'credit_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function setPinAttribute(string $value): void
    {
        $this->attributes['pin'] = Hash::make($value);
    }

    public function verifyPin(string $pin): bool
    {
        return Hash::check($pin, $this->attributes['pin'] ?? '');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function ratePlan()
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function creditLedger()
    {
        return $this->hasMany(CreditLedger::class);
    }

    public function deposits()
    {
        return $this->hasMany(CustomerDeposit::class);
    }

    public function completedDeposit()
    {
        return $this->hasOne(CustomerDeposit::class)->where('status', 'COMPLETED')->latestOfMany();
    }

    public function hasCompletedDeposit(): bool
    {
        return $this->deposits()->where('status', 'COMPLETED')->exists();
    }
}
