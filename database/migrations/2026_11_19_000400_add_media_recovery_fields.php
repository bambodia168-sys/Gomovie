<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->boolean('auto_publication_requested')->default(false);
            $table->uuid('publication_retry_token')->nullable();
            $table->timestamp('publication_retry_queued_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
        });

        // Recover legacy automatic uploads only when there is evidence of that workflow.
        DB::table('media_ingests')->where('disk', 'local')->where(function ($query): void {
            $query->whereIn('pipeline_error', [
                'publication_queue_unavailable', 'automatic_publication_failed',
                'active_free_distribution_rights_required', 'documented_distribution_rights_required',
            ])->orWhereExists(function ($audit): void {
                $audit->selectRaw('1')->from('admin_audit_events')
                    ->where('resource_type', 'media_ingest')->where('action', 'media.auto_scan_passed')
                    ->whereColumn('resource_id', 'media_ingests.id');
            })->orWhere(function ($standalone): void {
                $standalone->where('ingestable_type', 'movie')
                    ->whereIn('ingestable_id', DB::table('movies')->select('id')->where('is_standalone_video', true));
            });
        })->update(['auto_publication_requested' => true]);
    }

    public function down(): void
    {
        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_publication_requested', 'publication_retry_token',
                'publication_retry_queued_at', 'processing_started_at',
            ]);
        });
    }
};
