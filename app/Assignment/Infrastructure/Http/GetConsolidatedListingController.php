<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use App\Assignment\Application\Consolidated\ConsolidatedListingQuery;
use App\Assignment\Application\Consolidated\ConsolidatedListingReader;
use Illuminate\Http\JsonResponse;

final readonly class GetConsolidatedListingController
{
    public function __construct(
        private ConsolidatedListingReader $reader,
    ) {}

    public function __invoke(ConsolidatedListingRequest $request): JsonResponse
    {
        $query = new ConsolidatedListingQuery(
            sort: $request->string('sort', 'years_of_experience')->toString(),
            direction: $request->string('direction', 'desc')->toString(),
            filters: $this->filters($request),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
        );

        return response()->json(
            ConsolidatedListingResource::toArray($this->reader->read($query)),
        );
    }

    /**
     * @return array<string, string>
     */
    private function filters(ConsolidatedListingRequest $request): array
    {
        $filters = [];

        foreach ($request->array('filter') as $key => $value) {
            if (is_string($key) && (is_string($value) || is_int($value) || is_float($value))) {
                $filters[$key] = (string) $value;
            }
        }

        return $filters;
    }
}
