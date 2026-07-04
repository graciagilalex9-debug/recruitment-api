<?php

declare(strict_types=1);

namespace Tests\Feature\Idempotency;

use App\Report\Infrastructure\Persistence\ReportModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ExportIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The export runs its job inline (sync queue); keep side effects out of the way.
        Storage::fake('local');
        Mail::fake();
    }

    public function test_a_retry_with_the_same_key_replays_the_response_without_duplicating(): void
    {
        $headers = ['Idempotency-Key' => 'key-abc'];
        $body = ['sort' => 'years_of_experience', 'direction' => 'desc'];

        $first = $this->postJson('/candidatures/consolidated/export', $body, $headers)->assertStatus(202);
        $second = $this->postJson('/candidatures/consolidated/export', $body, $headers)->assertStatus(202);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $second->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(1, ReportModel::query()->count());
    }

    public function test_reusing_a_key_with_a_different_body_is_rejected(): void
    {
        $headers = ['Idempotency-Key' => 'key-xyz'];

        $this->postJson('/candidatures/consolidated/export', ['sort' => 'years_of_experience'], $headers)
            ->assertStatus(202);

        $this->postJson('/candidatures/consolidated/export', ['sort' => 'email'], $headers)
            ->assertStatus(422);
    }

    public function test_a_request_with_an_in_progress_key_is_rejected(): void
    {
        // Simulate the first request still holding the lock for this key.
        $lock = Cache::lock('idempotency:lock:key-busy', 30);
        $this->assertTrue($lock->get());

        try {
            $this->postJson('/candidatures/consolidated/export', [], ['Idempotency-Key' => 'key-busy'])
                ->assertStatus(409);
        } finally {
            $lock->release();
        }
    }

    public function test_requests_without_a_key_are_processed_independently(): void
    {
        $this->postJson('/candidatures/consolidated/export')->assertStatus(202);
        $this->postJson('/candidatures/consolidated/export')->assertStatus(202);

        $this->assertSame(2, ReportModel::query()->count());
    }
}
