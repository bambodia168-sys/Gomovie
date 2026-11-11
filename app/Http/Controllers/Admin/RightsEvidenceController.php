<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Media\AutomaticPublicationService;
use App\Services\Media\MediaPackageInspector;
use App\Services\Media\MediaPublicationReviewer;
use App\Services\Rights\RightsEvidenceService;
use App\Services\Rights\ReviewedDistributionRights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RightsEvidenceController extends Controller
{
    private function admin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, ['admin','super_admin'], true), 403);
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $evidence = DB::table('rights_evidence as e')
            ->join('users as submitters', 'submitters.id', '=', 'e.submitted_by')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'e.reviewed_by')
            ->select('e.*','submitters.name as submitter_name','reviewers.name as reviewer_name')
            ->orderByDesc('e.id')->paginate(10)->withQueryString();
        $encoded = DB::table('media_ingests as i')
            ->leftJoin('media_publication_reviews as r', 'r.media_ingest_id', '=', 'i.id')
            ->select('i.id','i.ingestable_type','i.ingestable_id','i.status',
                'i.uploaded_by','i.approved_by','r.status as review_status','r.rights_reference')
            ->where('i.status','encoded')->orderByDesc('i.id')->limit(50)->get();
        return view('admin.rights-evidence', [
            'evidence' => $evidence, 'encoded' => $encoded,
        ]);
    }

    public function submit(Request $request, RightsEvidenceService $service): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'licensable_type' => ['required',Rule::in(['movie','episode'])],
            'licensable_id' => ['required','integer','min:1'],
            'rights_reference' => ['required','string','min:8','max:180',
                'regex:/\A[A-Za-z0-9][A-Za-z0-9:._\/-]+\z/D'],
            'rights_holder' => ['required','string','min:3','max:180'],
            'starts_at' => ['required','date'],
            'ends_at' => ['required','date','after:starts_at'],
            'document' => ['required','file','mimes:pdf,jpg,jpeg,png','max:8192'],
        ]);
        $service->submit($request->user(), $data, $data['document'], $request->ip());
        return back()->with('status','Evidence scanned and saved privately. Independent verification is pending.');
    }

    public function document(Request $request, int $evidence, RightsEvidenceService $service): BinaryFileResponse
    {
        $this->admin($request);
        $found = $service->download($request->user(), $evidence);
        return response()->download($found['path'], 'license-evidence-'.$evidence.'.'.
            ($found['mime'] === 'application/pdf' ? 'pdf' :
                ($found['mime'] === 'image/png' ? 'png' : 'jpg')), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function verify(Request $request, int $evidence, RightsEvidenceService $service): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'review_reference' => ['required','string','min:8','max:180',
                'regex:/\A[A-Za-z0-9][A-Za-z0-9:._\/-]+\z/D'],
            'document_sha256' => ['required','regex:/\A[a-f0-9]{64}\z/D'],
            'legal_review_confirmed' => ['accepted'],
        ]);
        $service->review($request->user(), $evidence,
            $data['review_reference'], $data['document_sha256'], $request->ip());
        return back()->with('status','Independent administrator attestation recorded. License dates apply.');
    }

    public function revoke(Request $request, int $evidence, RightsEvidenceService $service): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['reason'=>['required','string','min:8','max:255']]);
        $service->revoke($request->user(), $evidence, $data['reason'], $request->ip());
        return back()->with('status','License revoked. Reviewed public playback is no longer eligible.');
    }

    public function reviewPackage(Request $request, int $ingest, MediaPublicationReviewer $reviewer): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'rights_reference' => ['required','string','min:8','max:180'],
            'scan_reference' => ['required','string','min:8','max:180'],
            'quality_reference' => ['required','string','min:8','max:180'],
            'manifest_sha256' => ['required','regex:/\A[a-f0-9]{64}\z/D'],
            'package_review_confirmed' => ['accepted'],
        ]);
        $row = DB::table('media_ingests')->where('id', $ingest)->first();
        abort_unless($row,404);
        app(ReviewedDistributionRights::class)->requireCurrent(
            $row->ingestable_type,(int)$row->ingestable_id,$data['rights_reference']);
        $reviewer->review($ingest,$request->user(),$data['rights_reference'],
            $data['scan_reference'],$data['quality_reference'],$data['manifest_sha256']);
        return back()->with('status','Independent package review recorded. Publishing requires another administrator.');
    }

    public function publish(Request $request, int $ingest, AutomaticPublicationService $publisher): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'publication_confirmed' => ['accepted'],
            'expected_package_sha256' => ['required','regex:/\A[a-f0-9]{64}\z/D'],
        ]);
        // Reinspect full manifest, segments, original, and current rights.
        $package = app(MediaPackageInspector::class)->inspect($ingest,true);
        abort_unless(hash_equals($package['package_sha256'],$data['expected_package_sha256']),409,
            'Package changed; obtain a fresh review.');
        $row=$package['ingest'];
        $review=DB::table('media_publication_reviews')
            ->where('media_ingest_id',$ingest)->where('status','reviewed')->first();
        abort_unless($review,403);
        app(ReviewedDistributionRights::class)->requireCurrent(
            $row->ingestable_type,(int)$row->ingestable_id,(string)$review->rights_reference);
        $asset = $publisher->finalize($ingest,$request->user());
        abort_unless(is_int($asset)&&$asset>0,503,
            'Public streaming remains disabled pending hosting acceptance.');
        return back()->with('status','Reviewed publication activated. Playback eligibility is checked for every request.');
    }
}
