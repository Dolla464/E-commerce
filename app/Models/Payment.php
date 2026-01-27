<?php

namespace App\Models;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    // fillable fields
    protected $fillable = [
        'order_id',
        'user_id',
        'provider',
        'payment_intent_id',
        'amount',
        'currency',
        'status',
        'metadata',
        'completed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
        'provider' => PaymentProvider::class,
        'status' => PaymentStatus::class,
    ];

    // define relationship to Order
    public function order()
    {
        return $this->belongsTo(Order::class); 
    }

    // define relationship to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // mark as completed
    public function markAsCompleted($paymentIntentId, $metadata = [])
    {
        $this->update([
            'status' => PaymentStatus::COMPLETED,
            'payment_intent_id' => $paymentIntentId,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
            'completed_at' => now(),
        ]);
        $this->order->markAsPaid($paymentIntentId);
    }

    // mark as failed
    public function markAsFailed($metadata = [])
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
        $this->order->markAsFailed();
    }

    // is finalized
    public function isFinal()
    {
        return in_array($this->status, [
            PaymentStatus::COMPLETED,
            PaymentStatus::FAILED,
            PaymentStatus::REFUNDED,
        ]);
    }
}