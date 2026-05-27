<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTeamController extends Controller
{
    public function show(Request $request, Project $project): JsonResponse
    {
        $payload = app(ProjectController::class)->buildShowPayload($request, $project);

        return response()->json([
            'members' => $payload['members'],
            'teamMembers' => $payload['teamMembers'],
            'teamCandidates' => $payload['teamCandidates'],
            'canManageTeam' => $payload['canManageTeam'],
            'memberRoles' => $payload['memberRoles'],
        ]);
    }
}
