<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresignedUrlController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => app(EvidenceService::class)->prepare($request)]);
    }
}
