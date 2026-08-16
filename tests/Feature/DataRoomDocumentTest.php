<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PortfolioCompany;
use App\Models\User;
use App\Models\UserCompanyAssignment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class DataRoomDocumentTest extends TestCase
{
    private static bool $schemaReady = false;

    private User $user;

    private PortfolioCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDatabase();

        if (! self::$schemaReady) {
            $this->rebuildSchema();
            self::$schemaReady = true;
        }

        DB::beginTransaction();

        Storage::fake('private');

        $organization = Organization::create([
            'name' => 'Test Fund',
            'base_currency' => 'USD',
        ]);

        $this->company = PortfolioCompany::create([
            'organization_id' => $organization->id,
            'name' => 'Acme Portfolio Co',
            'sector' => 'Technology',
            'status' => 'on_track',
            'transaction_date' => now()->toDateString(),
            'invested_amount' => 1000000,
            'invested_currency' => 'USD',
            'fx_currency' => 'USD',
            'fx_rate' => 1,
            'equity_stake' => 0.4,
            'entry_valuation' => 2500000,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        UserCompanyAssignment::create([
            'user_id' => $this->user->id,
            'portfolio_company_id' => $this->company->id,
            'role' => 'manager',
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_download_appends_missing_file_extension(): void
    {
        $documentId = $this->storeDocument([
            'name' => 'Q3 Report',
            'path' => "data-room/{$this->company->id}/hashed-file.pdf",
            'mime_type' => 'application/pdf',
            'contents' => '%PDF-1.4 test',
        ]);

        $response = $this->actingAs($this->user)->get(route('data-room.download', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]));

        $response->assertOk();

        $disposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('q3 report.pdf', $disposition);
        $this->assertStringNotContainsString('.pdf.pdf', $disposition);
    }

    public function test_download_does_not_duplicate_existing_extension(): void
    {
        $documentId = $this->storeDocument([
            'name' => 'report.PDF',
            'path' => "data-room/{$this->company->id}/hashed-file.pdf",
            'mime_type' => 'application/pdf',
            'contents' => '%PDF-1.4 test',
        ]);

        $response = $this->actingAs($this->user)->get(route('data-room.download', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]));

        $response->assertOk();

        $disposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('report.pdf', $disposition);
        $this->assertStringNotContainsString('report.pdf.pdf', $disposition);
    }

    public function test_view_streams_pdf_inline(): void
    {
        $contents = '%PDF-1.4 inline-preview';
        $documentId = $this->storeDocument([
            'name' => 'Board Pack',
            'path' => "data-room/{$this->company->id}/board-pack.pdf",
            'mime_type' => 'application/pdf',
            'contents' => $contents,
        ]);

        $response = $this->actingAs($this->user)->get(route('data-room.view', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]));

        $response->assertOk();

        $disposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('inline', $disposition);
        $this->assertStringContainsString('board pack.pdf', $disposition);
        $this->assertStringContainsString('application/pdf', strtolower((string) $response->headers->get('content-type')));
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_view_streams_docx_inline(): void
    {
        $contents = 'PK-docx-bytes';
        $documentId = $this->storeDocument([
            'name' => 'ffq',
            'path' => "data-room/{$this->company->id}/ffq.docx",
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'contents' => $contents,
        ]);

        $response = $this->actingAs($this->user)->get(route('data-room.view', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]));

        $response->assertOk();

        $disposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('inline', $disposition);
        $this->assertStringContainsString('ffq.docx', $disposition);
        $this->assertStringContainsString(
            'wordprocessingml',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_view_streams_xlsx_inline(): void
    {
        $contents = 'PK-xlsx-bytes';
        $documentId = $this->storeDocument([
            'name' => 'Q3 Numbers',
            'path' => "data-room/{$this->company->id}/q3.xlsx",
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'contents' => $contents,
        ]);

        $response = $this->actingAs($this->user)->get(route('data-room.view', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]));

        $response->assertOk();

        $disposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('inline', $disposition);
        $this->assertStringContainsString('q3 numbers.xlsx', $disposition);
        $this->assertStringContainsString(
            'spreadsheetml',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_view_streams_pptx_inline(): void
    {
        $contents = 'PK-pptx-bytes';
        $documentId = $this->storeDocument([
            'name' => 'Board Deck',
            'path' => "data-room/{$this->company->id}/board.pptx",
            'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'contents' => $contents,
        ]);

        $response = $this->actingAs($this->user)->get(route('data-room.view', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]));

        $response->assertOk();

        $disposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('inline', $disposition);
        $this->assertStringContainsString('board deck.pptx', $disposition);
        $this->assertStringContainsString(
            'presentationml',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_guest_cannot_view_or_download_documents(): void
    {
        $documentId = $this->storeDocument([
            'name' => 'Secret',
            'path' => "data-room/{$this->company->id}/secret.pdf",
            'mime_type' => 'application/pdf',
            'contents' => '%PDF-1.4 secret',
        ]);

        $this->get(route('data-room.download', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]))->assertRedirect(route('login'));

        $this->get(route('data-room.view', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]))->assertRedirect(route('login'));
    }

    public function test_user_without_assignment_cannot_view_or_download_documents(): void
    {
        $documentId = $this->storeDocument([
            'name' => 'Internal Memo',
            'path' => "data-room/{$this->company->id}/memo.pdf",
            'mime_type' => 'application/pdf',
            'contents' => '%PDF-1.4 memo',
        ]);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('data-room.download', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]))->assertForbidden();

        $this->actingAs($outsider)->get(route('data-room.view', [
            'company' => $this->company->id,
            'document' => $documentId,
        ]))->assertForbidden();
    }

    protected function configureDatabase(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            return;
        }

        $name = 'cfostools_testing';
        $mysql = config('database.connections.mysql');

        $pdo = new PDO(
            "mysql:host={$mysql['host']};port={$mysql['port']}",
            $mysql['username'],
            $mysql['password']
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $name,
        ]);

        DB::purge();
        DB::setDefaultConnection('mysql');
    }

    protected function rebuildSchema(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        Schema::dropIfExists('documents');
        Schema::dropIfExists('user_company_assignments');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('portfolio_companies');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');

        if (config('database.default') === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_structure', 100)->nullable();
            $table->string('logo')->nullable();
            $table->char('base_currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('portfolio_companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('type')->default('investment');
            $table->string('name');
            $table->string('lead_source', 100)->nullable();
            $table->string('sector', 100);
            $table->string('status')->default('on_track');
            $table->date('transaction_date');
            $table->decimal('invested_amount', 15, 2);
            $table->char('invested_currency', 3);
            $table->char('fx_currency', 3)->default('USD');
            $table->decimal('fx_rate', 12, 6);
            $table->decimal('equity_stake', 5, 4);
            $table->decimal('ebitda_multiplier', 6, 2)->nullable();
            $table->decimal('entry_valuation', 15, 2);
            $table->decimal('current_valuation', 15, 2)->nullable();
            $table->decimal('moic', 5, 2)->nullable();
            $table->decimal('irr', 5, 2)->nullable();
            $table->date('last_financial_update')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('user_company_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('role')->default('viewer');
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('name');
            $table->string('path', 512);
            $table->string('mime_type', 100)->nullable();
            $table->string('category', 50)->default('other');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
    }

    /**
     * @param  array{name:string,path:string,mime_type:string,contents:string}  $file
     */
    private function storeDocument(array $file): int
    {
        Storage::disk('private')->put($file['path'], $file['contents']);

        return DB::table('documents')->insertGetId([
            'portfolio_company_id' => $this->company->id,
            'name' => $file['name'],
            'path' => $file['path'],
            'mime_type' => $file['mime_type'],
            'category' => 'other',
            'uploaded_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
