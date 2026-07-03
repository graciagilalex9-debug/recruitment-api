<?php

declare(strict_types=1);

namespace Tests\Unit\Candidature\Application;

use App\Candidature\Application\Register\CandidatureRegistrar;
use App\Candidature\Application\Register\RegisterCandidatureCommand;
use App\Candidature\Domain\Exception\CandidatureAlreadyExists;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryCandidatureRepository;

/**
 * Unit test for the use case — no database, no HTTP. It runs against the in-memory fake
 * repository, which is possible precisely because the Registrar depends on the port
 * (CandidatureRepository), not on Eloquent.
 */
final class CandidatureRegistrarTest extends TestCase
{
    public function test_registers_a_candidature(): void
    {
        $repository = new InMemoryCandidatureRepository;
        $registrar = new CandidatureRegistrar($repository);

        $response = $registrar->register(new RegisterCandidatureCommand(
            fullName: 'Ada Lovelace',
            email: 'Ada@Example.COM',
            yearsOfExperience: 7,
            cv: 'First programmer.',
        ));

        $this->assertSame('Ada Lovelace', $response->fullName);
        $this->assertSame('ada@example.com', $response->email); // normalized by the Email VO
        $this->assertNotEmpty($response->id);
        $this->assertSame(1, $repository->count());
    }

    public function test_rejects_a_duplicate_email(): void
    {
        $repository = new InMemoryCandidatureRepository;
        $registrar = new CandidatureRegistrar($repository);
        $command = new RegisterCandidatureCommand(
            fullName: 'Ada Lovelace',
            email: 'ada@example.com',
            yearsOfExperience: 7,
            cv: 'First programmer.',
        );

        $registrar->register($command);

        $this->expectException(CandidatureAlreadyExists::class);
        $registrar->register($command);
    }
}
