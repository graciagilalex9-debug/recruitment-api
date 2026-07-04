<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Assignment\Infrastructure\Persistence\AssignmentModel;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use App\Report\Domain\Report;
use App\Report\Domain\ReportRepository;
use App\Report\Domain\ValueObject\ReportCriteria;
use App\Report\Domain\ValueObject\ReportType;
use App\Report\Infrastructure\Mail\ReportReadyMail;
use App\Report\Infrastructure\Queue\GenerateReportJob;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

final class ExcelReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    private function assign(CandidatureModel $candidature, EvaluatorModel $evaluator): void
    {
        AssignmentModel::create([
            'candidature_id' => $candidature->id,
            'evaluator_id' => $evaluator->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_an_export_request_is_accepted_and_schedules_generation(): void
    {
        Bus::fake(); // keep the report pending so we can observe the 202 body

        $response = $this->postJson('/candidatures/consolidated/export', [
            'sort' => 'years_of_experience',
            'direction' => 'desc',
        ])->assertStatus(202);

        $response->assertJsonPath('data.status', 'pending');
        $this->assertNotEmpty($response->json('data.id'));
        Bus::assertDispatched(GenerateReportJob::class);
    }

    public function test_it_rejects_an_export_ordered_by_an_unknown_column(): void
    {
        $this->postJson('/candidatures/consolidated/export', ['sort' => 'drop_table'])
            ->assertStatus(422);
    }

    public function test_a_requested_export_completes_and_can_be_downloaded(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $this->assign(CandidatureModel::factory()->create(), $evaluator);

        $id = $this->postJson('/candidatures/consolidated/export')->assertStatus(202)->json('data.id');

        $this->getJson("/reports/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.download_url', route('reports.download', ['id' => $id]));

        Storage::disk('local')->assertExists("reports/{$id}.xlsx");

        $this->get("/reports/{$id}/download")
            ->assertOk()
            ->assertDownload("consolidated-report-{$id}.xlsx");
    }

    public function test_it_splits_candidatures_into_sheets_of_at_most_fifty(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        foreach (CandidatureModel::factory()->count(60)->create() as $candidature) {
            $this->assign($candidature, $evaluator);
        }

        $id = $this->postJson('/candidatures/consolidated/export')->assertStatus(202)->json('data.id');
        $this->getJson("/reports/{$id}")->assertJsonPath('data.status', 'completed');

        $spreadsheet = IOFactory::load(Storage::disk('local')->path("reports/{$id}.xlsx"));

        $this->assertSame(2, $spreadsheet->getSheetCount());
        // Sheet 1: header row + 50 candidatures = 51 rows. Sheet 2: header + 10 = 11 rows.
        $this->assertSame(51, $spreadsheet->getSheet(0)->getHighestRow());
        $this->assertSame(11, $spreadsheet->getSheet(1)->getHighestRow());
    }

    public function test_the_export_honours_the_requested_filter(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $this->assign(CandidatureModel::factory()->create(['full_name' => 'Ada Lovelace']), $evaluator);
        $this->assign(CandidatureModel::factory()->create(['full_name' => 'Alan Turing']), $evaluator);

        $id = $this->postJson('/candidatures/consolidated/export', ['filter' => ['full_name' => 'Ada']])
            ->assertStatus(202)->json('data.id');
        $this->getJson("/reports/{$id}")->assertJsonPath('data.status', 'completed');

        $sheet = IOFactory::load(Storage::disk('local')->path("reports/{$id}.xlsx"))->getSheet(0);

        $this->assertSame(2, $sheet->getHighestRow()); // header + exactly one matching row
        $this->assertSame('Ada Lovelace', $sheet->getCell('A2')->getValue());
    }

    public function test_it_sends_an_email_with_the_download_link_on_completion(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $this->assign(CandidatureModel::factory()->create(), $evaluator);

        $this->postJson('/candidatures/consolidated/export')->assertStatus(202);

        Mail::assertQueued(ReportReadyMail::class);
    }

    public function test_the_status_of_an_unknown_report_is_not_found(): void
    {
        $this->getJson('/reports/'.strtoupper((string) Str::ulid()))->assertStatus(404);
    }

    public function test_downloading_a_report_that_is_not_ready_is_refused(): void
    {
        Bus::fake(); // keep the report pending (never generated)

        $id = $this->postJson('/candidatures/consolidated/export')->assertStatus(202)->json('data.id');

        $this->get("/reports/{$id}/download")->assertStatus(409);
    }

    public function test_a_failed_generation_is_reflected_in_the_report_status(): void
    {
        $reports = app(ReportRepository::class);
        $id = $reports->nextIdentity();

        $report = Report::request(
            $id,
            ReportType::ConsolidatedListing,
            new ReportCriteria('years_of_experience', 'desc', []),
            new DateTimeImmutable,
        );
        $report->markProcessing();
        $reports->save($report);

        // Simulate the queue exhausting its retries and invoking the job's failed() hook.
        (new GenerateReportJob($id->value()))->failed(new RuntimeException('disk full'));

        $this->getJson("/reports/{$id->value()}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'disk full');
    }
}
