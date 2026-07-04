<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent persistence model for assignments (framework detail, confined to this folder).
 *
 * The `id` is a technical surrogate key — the domain Assignment has no id of its own (it is
 * identified by its candidature) — so it is generated here via HasUlids, not by the domain.
 *
 * @property string $id
 * @property string $candidature_id
 * @property string $evaluator_id
 * @property Carbon $assigned_at
 */
final class AssignmentModel extends Model
{
    use HasUlids;

    protected $table = 'assignments';

    public $timestamps = false;

    protected $fillable = [
        'candidature_id',
        'evaluator_id',
        'assigned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
