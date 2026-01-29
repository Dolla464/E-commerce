<?php

namespace App\Models;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Events\OrderStatusChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'shipping_name',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_zip_code',
        'shipping_country',
        'shipping_phone',
        'subtotal',
        'tax',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_status',
        'order_number',
        'notes',
        'tracking_id',
        'paid_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function transitionTo(OrderStatus $newStatus, ?User $changedBy = null, ?string $notes = null)
    {
        // do not allow same status transition
        if ($this->status === $newStatus) {
            return true;
        }

        if (!$this->status->canTransitionTo($newStatus)) {
            return false;
        }

        // store the old status
        $oldStatus = $this->status;
        $this->update(['status'=> $newStatus]);

        $this->statusHistory()->create([
            'order_id'   => $this->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy->id ?? Auth::id(),
            'notes'      => $notes,
        ]);

        // dispatch orderStatusChanged event
        OrderStatusChanged::dispatch(
            $this,
            $oldStatus->value,
            $changedBy?->name ?? Auth::user()->name ,
        );
        return true;
    }

    // get allowed transitions for the current status
    public function getAllowedTransitions(): array
    {
        return $this->status->getAllowedTransitions();
    }

    public function getLatesetStatusChange()
    {
        return $this->statusHistory()->first();
    }

    // generate unique order number
    public static function generateOrderNumber()
    {
        $year = date('Y');
        $month = date('m');
        $randomNumber = strtoupper(substr(uniqid(), -6));
        return "ORD-{$year}{$month}-{$randomNumber}";
    }

    public function canBeCancelled()
    {
        return in_array($this->status, [
            OrderStatus::PENDING,
            OrderStatus::PAID,
        ]);
    }

    // mark as paid
    public function markAsPaid($transactionId)
    {
        $this->update([
            'payment_status' => PaymentStatus::COMPLETED,
            'status' => OrderStatus::PAID,
            'transaction_id' => $transactionId,
            'paid_at' => now(),
        ]);
    }

    // mark as failed
    public function markAsFailed()
    {
        $this->update([
            'payment_status' => PaymentStatus::FAILED,
        ]);
    }
}
