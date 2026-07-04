<?php

declare(strict_types=1);

namespace App\Evaluator\Infrastructure\Persistence;

use Database\Factories\EvaluatorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent persistence model for evaluators (framework detail, confined to this folder).
 *
 * @property string $id
 * @property string $name
 * @property Carbon $created_at
 */
final class EvaluatorModel extends Model
{
    /** @use HasFactory<EvaluatorFactory> */
    use HasFactory;

    protected $table = 'evaluators';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EvaluatorFactory
    {
        return EvaluatorFactory::new();
    }
}
