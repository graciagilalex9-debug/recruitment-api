<?php

declare(strict_types=1);

namespace Tests\Unit\Candidature\ValueObject;

use App\Candidature\Domain\Exception\InvalidEmail;
use App\Candidature\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no Laravel, no database. Extends PHPUnit's TestCase directly.
 */
final class EmailTest extends TestCase
{
    public function test_normalizes_to_lowercase_and_trims(): void
    {
        $email = new Email('  Ada@Example.COM  ');

        $this->assertSame('ada@example.com', $email->value());
    }

    public function test_rejects_a_malformed_address(): void
    {
        $this->expectException(InvalidEmail::class);

        new Email('not-an-email');
    }

    public function test_rejects_an_empty_string(): void
    {
        $this->expectException(InvalidEmail::class);

        new Email('');
    }

    public function test_two_addresses_differing_only_in_case_are_equal(): void
    {
        $this->assertTrue(
            (new Email('ADA@example.com'))->equals(new Email('ada@example.com')),
        );
    }
}
