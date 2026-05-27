<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDeposit extends Model
{
    protected $fillable = [
        'customer_id',
        'deposit_config_id',
        'amount_required',
        'amount_paid',
        'payment_method',
        'mpesa_receipt_no',
        'mpesa_checkout_request_id',
        'status',
        'notes',
        'collected_by',
    ];

    protected $casts = [
        'amount_required' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function depositConfig()
    {
        return $this->belongsTo(DepositConfiguration::class, 'deposit_config_id');
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
