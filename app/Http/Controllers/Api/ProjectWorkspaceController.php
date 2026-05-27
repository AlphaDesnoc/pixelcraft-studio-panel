<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectWorkspaceController extends Controller
{
    public function show(Request $request, Project $project): JsonResponse
    {
        return response()->json(
            app(ProjectController::class)->buildShowPayload($request, $project),
        );
    }
}
