<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Media\ResumableVideoUpload;
use App\Support\VideoUploadLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class VideoUploadTransferController extends Controller
{
    public function __construct(private ResumableVideoUpload $uploads) {}

    public function store(Request $request): JsonResponse
    {
        $uploader = $this->administrator($request);
        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'workflow' => ['required', Rule::in(['quick', 'catalog'])],
            'filename' => ['required', 'string', 'max:255', 'not_regex:~[/\\\\\x00-\x1f\x7f]~'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.VideoUploadLimit::bytes()],
        ]);

        return $this->reply($this->uploads->begin(strtolower($data['id']), $uploader,
            $data['workflow'], $data['filename'], (int) $data['size_bytes']));
    }

    public function show(Request $request, string $transfer): JsonResponse
    {
        return $this->reply($this->uploads->status(strtolower($transfer), $this->administrator($request)));
    }

    public function update(Request $request, string $transfer): JsonResponse
    {
        $uploader = $this->administrator($request);
        abort_unless($request->header('Content-Type') === 'application/octet-stream', 415);
        $data = $request->validate(['offset' => ['required', 'integer', 'min:0']]);
        $input = $request->getContent(true);
        abort_unless(is_resource($input), 400);
        try {
            return $this->reply($this->uploads->append(strtolower($transfer), $uploader, (int) $data['offset'], $input));
        } finally {
            fclose($input);
        }
    }

    public function destroy(Request $request, string $transfer): Response
    {
        $this->uploads->cancel(strtolower($transfer), $this->administrator($request));

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    private function administrator(Request $request): int
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super_admin'], true), 403);

        return (int) $request->user()->id;
    }

    private function reply(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'private, no-store');
    }
}
