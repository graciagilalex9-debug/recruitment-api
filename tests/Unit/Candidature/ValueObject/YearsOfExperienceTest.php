<?php

declare(strict_types=1);

namespace Tests\Unit\Candidature\ValueObject;

use App\Candidature\Domain\Exception\InvalidYearsOfExperience;
use App\Candidature\Domain\ValueObject\YearsOfExperience;
use PHPUnit\Framework\TestCase;

final class YearsOfExperienceTest extends TestCase
{
    public function test_accepts_zero_and_positive_values(): void
    {
        $this->assertSame(0, (new YearsOfExperience(0))->value());
        $this->assertSame(7, (new YearsOfExperience(7))->value());
    }

    public function test_rejects_a_negative_value(): void
    {
        $this->expectException(InvalidYearsOfExperience::class);

        new YearsOfExperience(-1);
    }
}
