<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Cache;

use App\Candidature\Application\Validate\CandidatureValidationReader;
use App\Candidature\Application\Validate\RuleResultResponse;
use App\Candidature\Application\Validate\ValidationReportResponse;
use Illuminate\Contracts\Cache\Repository;

/**
 * Caching decorator over the validation reader. A candidature is immutable, so its eligibility
 * report never changes: cache it with a long TTL and no invalidation. A missing candidature makes
 * the inner reader throw (404) before anything is cached, so 404s are never memoised.
 *
 * We cache a plain-array form (not the DTO): on a pure cache hit the DTO classes may not be
 * autoloaded when the store unserializes, which would yield __PHP_Incomplete_Class.
 */
final readonly class CachingCandidatureValidationReader implements CandidatureValidationReader
{
    public function __construct(
        private CandidatureValidationReader $inner,
        private Repository $cache,
    ) {}

    public function validate(string $candidatureId): ValidationReportResponse
    {
        /** @var array<string, mixed> $data */
        $data = $this->cache->remember(
            'candidature-validation:'.$candidatureId,
            (int) config('performance.validation_cache_ttl', 86400),
            fn (): array => self::encode($this->inner->validate($candidatureId)),
        );

        return self::decode($data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function encode(ValidationReportResponse $report): array
    {
        return [
            'candidatureId' => $report->candidatureId,
            'valid' => $report->valid,
            'rules' => array_map(static fn (RuleResultResponse $rule): array => [
                'key' => $rule->key,
                'passed' => $rule->passed,
                'reason' => $rule->reason,
            ], $report->rules),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function decode(array $data): ValidationReportResponse
    {
        /** @var list<array<string, mixed>> $rawRules */
        $rawRules = $data['rules'] ?? [];

        $rules = array_map(static fn (array $rule): RuleResultResponse => new RuleResultResponse(
            (string) $rule['key'],
            (bool) $rule['passed'],
            (string) $rule['reason'],
        ), $rawRules);

        return new ValidationReportResponse(
            (string) $data['candidatureId'],
            (bool) $data['valid'],
            $rules,
        );
    }
}
