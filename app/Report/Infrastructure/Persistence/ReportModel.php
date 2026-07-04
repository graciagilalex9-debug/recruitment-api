<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent persistence model for reports (framework detail, confined to this folder).
 *
 * The `id` is the domain's ULID (supplied by the repository's nextIdentity(), not generated
 * here), so the key is a non-incrementing string. Not the domain entity — the mapper
 * translates between this row and the Report aggregate.
 *
 * @property string $id
 * @property string $type
 * @property string $status
 * @property string $sort
 * @property string $direction
 * @property array<string, string> $filters
 * @property string|null $file_path
 * @property string|null $failure_reason
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 */
final class ReportModel extends Model
{
    protected $table = 'reports';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'type',
        'status',
        'sort',
        'direction',
        'filters',
        'file_path',
        'failure_reason',
        'requested_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
