<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Services\Rights\ReviewedDistributionRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RightsEvidenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_dir($file)) @rmdir($file);
            else @unlink($file);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'admin','is_active'=>true,'email_verified_at'=>now()])->save();
        return $user->fresh();
    }

    private function movie(): Movie
    {
        return Movie::create([
            'title'=>'Synthetic License Verification QA',
            'slug'=>'synthetic-license-'.strtolower(Str::random(8)),
            'is_premium'=>false, 'status'=>'draft',
        ]);
    }

    private function scanner(int $exit = 0): void
    {
        $directory=sys_get_temp_dir().'/gomovie-rights-qa-'.Str::random(12);
        mkdir($directory,0700,true);
        $binary=$directory.'/clamscan';
        file_put_contents($binary,"#!/bin/sh\nexit ".$exit."\n");
        chmod($binary,0700);
        $this->temporaryFiles[]=$binary;
        $this->temporaryFiles[]=$directory;
        config()->set('gomovie.malware_scanner_binary',$binary);
    }

    private function document(): UploadedFile
    {
        $name=tempnam(sys_get_temp_dir(),'gomovie-license-');
        file_put_contents($name,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n");
        $this->temporaryFiles[]=$name;
        return new UploadedFile($name,'synthetic-legal-evidence.pdf','application/pdf',null,true);
    }

    private function submit(User $actor, Movie $title): void
    {
        $this->actingAs($actor)->post(route('admin.rights-evidence.submit'),[
            'licensable_type'=>'movie', 'licensable_id'=>$title->id,
            'rights_reference'=>'LEGAL-LICENSE-TEST-2026',
            'rights_holder'=>'Synthetic rights holder (not a real license)',
            'starts_at'=>now()->subDay()->toDateString(),
            'ends_at'=>now()->addDays(30)->toDateString(),
            'document'=>$this->document(),
        ])->assertRedirect();
    }

    public function test_protected_premium_workspace_requires_verified_administrator(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.rights-evidence.index'))->assertForbidden();
        $this->actingAs($this->admin())
            ->get(route('admin.rights-evidence.index'))->assertOk()
            ->assertSee('Rights &amp; Publication',false)
            ->assertSee('Add license evidence')
            ->assertSee('License evidence register');
    }

    public function test_only_independent_verified_admin_activates_scanned_license_and_revoke_blocks_it(): void
    {
        $this->scanner();
        $title=$this->movie();
        $submitter=$this->admin();
        $this->submit($submitter,$title);

        $e=DB::table('rights_evidence')->sole();
        self::assertSame('pending',$e->status);
        self::assertNotNull($e->document_scanned_at);
        self::assertSame('application/pdf',$e->document_mime);
        Storage::disk('local')->assertExists($e->document_key);
        self::assertFalse(app(ReviewedDistributionRights::class)
            ->allows('movie',(int)$title->id,(string)$e->rights_reference));
        $this->assertDatabaseCount('content_rights',0);

        $payload=[
            'review_reference'=>'LEGAL-REVIEW-SYNTHETIC-2026',
            'document_sha256'=>$e->document_sha256,
            'legal_review_confirmed'=>true,
        ];
        $this->actingAs($submitter)->post(
            route('admin.rights-evidence.verify',$e->id),$payload)->assertForbidden();
        $other=$this->admin();
        $this->actingAs($other)->get(
            route('admin.rights-evidence.document',$e->id))->assertOk()
            ->assertHeader('X-Content-Type-Options','nosniff');
        $this->post(route('admin.rights-evidence.verify',$e->id),$payload)->assertRedirect();
        $this->assertDatabaseHas('rights_evidence',[
            'id'=>$e->id,'status'=>'approved','reviewed_by'=>$other->id,
        ]);
        $this->assertDatabaseHas('content_rights',[
            'licensable_type'=>'movie','licensable_id'=>$title->id,
            'rights_reference'=>'LEGAL-LICENSE-TEST-2026','status'=>'active',
        ]);
        self::assertTrue(app(ReviewedDistributionRights::class)->allows(
            'movie',(int)$title->id,(string)$e->rights_reference));
        $this->post(route('admin.rights-evidence.verify',$e->id),$payload)
            ->assertStatus(409);

        $this->post(route('admin.rights-evidence.revoke',$e->id),[
            'reason'=>'Synthetic rights contract has been revoked',
        ])->assertRedirect();
        $this->assertDatabaseHas('rights_evidence',['id'=>$e->id,'status'=>'revoked']);
        $this->assertDatabaseHas('content_rights',[
            'rights_reference'=>'LEGAL-LICENSE-TEST-2026','status'=>'revoked']);
        self::assertFalse(app(ReviewedDistributionRights::class)->allows(
            'movie',(int)$title->id,(string)$e->rights_reference));
        $this->get(route('admin.rights-evidence.document',$e->id))->assertNotFound();
        $this->assertDatabaseCount('media_assets',0);
    }

    public function test_expiry_and_suspended_legal_verifier_immediately_deny_rights(): void
    {
        $this->scanner();
        $title=$this->movie();
        $this->submit($this->admin(),$title);
        $row=DB::table('rights_evidence')->sole();
        $verifier=$this->admin();
        $this->actingAs($verifier)->post(
            route('admin.rights-evidence.verify',$row->id),[
                'review_reference'=>'LEGAL-REVIEW-SYNTHETIC-2026',
                'document_sha256'=>$row->document_sha256,
                'legal_review_confirmed'=>true,
            ])->assertRedirect();
        self::assertTrue(app(ReviewedDistributionRights::class)->allows(
            'movie',(int)$title->id,(string)$row->rights_reference));

        DB::table('rights_evidence')->where('id',$row->id)->update([
            'ends_at'=>now()->subMinute(),
        ]);
        self::assertFalse(app(ReviewedDistributionRights::class)->allows(
            'movie',(int)$title->id,(string)$row->rights_reference));
        DB::table('rights_evidence')->where('id',$row->id)->update([
            'ends_at'=>now()->addDay(),
        ]);
        $verifier->forceFill(['is_active'=>false])->save();
        self::assertFalse(app(ReviewedDistributionRights::class)->allows(
            'movie',(int)$title->id,(string)$row->rights_reference));
    }

    public function test_scanner_failure_missing_scanner_and_bad_license_never_create_active_rights(): void
    {
        $title=$this->movie();
        $admin=$this->admin();
        config()->set('gomovie.malware_scanner_binary','/nonexistent/clamscan');
        $this->actingAs($admin)->post(route('admin.rights-evidence.submit'),[
            'licensable_type'=>'movie','licensable_id'=>$title->id,
            'rights_reference'=>'LEGAL-LICENSE-TEST-2026','rights_holder'=>'Synthetic holder',
            'starts_at'=>now()->subDay()->toDateString(),
            'ends_at'=>now()->addDay()->toDateString(),
            'document'=>$this->document(),
        ])->assertStatus(503);
        $this->scanner(1);
        $this->post(route('admin.rights-evidence.submit'),[
            'licensable_type'=>'movie','licensable_id'=>$title->id,
            'rights_reference'=>'LEGAL-LICENSE-TEST-2026','rights_holder'=>'Synthetic holder',
            'starts_at'=>now()->subDay()->toDateString(),
            'ends_at'=>now()->addDay()->toDateString(),
            'document'=>$this->document(),
        ])->assertStatus(422);
        $this->assertDatabaseCount('rights_evidence',0);
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
    }

    public function test_owner_declared_reference_is_rejected_even_with_successful_scanner(): void
    {
        $this->scanner();
        $title=$this->movie();
        $this->actingAs($this->admin())->post(route('admin.rights-evidence.submit'),[
            'licensable_type'=>'movie','licensable_id'=>$title->id,
            'rights_reference'=>'OWNER-DECLARED-FAKE-LICENSE',
            'rights_holder'=>'Synthetic test holder',
            'starts_at'=>now()->subDay()->toDateString(),
            'ends_at'=>now()->addDay()->toDateString(),
            'document'=>$this->document(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('rights_evidence',0);
        $this->assertDatabaseCount('content_rights',0);
    }
}
