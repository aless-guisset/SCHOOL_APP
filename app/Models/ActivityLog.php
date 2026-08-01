<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'event',
        'model_type',
        'model_id',
        'school_id',
        'model_label',
        'user_id',
        'user_email',
        'changes',
        'ip_address',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forModel(Model $model): Builder
    {
        return static::where('model_type', get_class($model))->where('model_id', $model->getKey());
    }
}
