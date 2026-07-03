<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Persistence;

use Database\Factories\CandidatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent persistence model for candidatures.
 *
 * This is NOT the domain entity — it is a framework detail confined to this folder.
 * The domain never sees it; the CandidatureMapper translates between this model and the
 * Candidature aggregate. Timestamps are disabled because the domain owns `created_at`.
 *
 * @property string $id
 * @property string $full_name
 * @property string $email
 * @property int $years_of_experience
 * @property string $cv
 * @property Carbon $created_at
 */
final class CandidatureModel extends Model
{
    /** @use HasFactory<CandidatureFactory> */
    use HasFactory;

    protected $table = 'candidatures';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'full_name',
        'email',
        'years_of_experience',
        'cv',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'years_of_experience' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CandidatureFactory
    {
        return CandidatureFactory::new();
    }
}
