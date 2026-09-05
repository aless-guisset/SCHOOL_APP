<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CantineTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'section_user_id', 'type', 'amount', 'cantine_order_id',
        'stripe_payment_intent_id', 'note', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'is_active' => 'boolean',
    ];

    public function sectionUser(): BelongsTo
    {
        return $this->belongsTo(SectionUserSchoolRole::class, 'section_user_id');
    }

    public function cantineOrder(): BelongsTo
    {
        return $this->belongsTo(CantineOrder::class, 'cantine_order_id');
    }
}
