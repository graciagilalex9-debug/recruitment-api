<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluator;

use App\Evaluator\Domain\Exception\InvalidEvaluatorName;
use App\Evaluator\Domain\ValueObject\EvaluatorName;
use PHPUnit\Framework\TestCase;

final class EvaluatorNameTest extends TestCase
{
    public function test_trims_the_name(): void
    {
        $this->assertSame('Grace Hopper', (new EvaluatorName('  Grace Hopper  '))->value());
    }

    public function test_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidEvaluatorName::class);

        new EvaluatorName('   ');
    }
}
