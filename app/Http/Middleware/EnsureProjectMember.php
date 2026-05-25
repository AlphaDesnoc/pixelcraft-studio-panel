<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\ProjectAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if ($project instanceof Project && $request->user()) {
            ProjectAccess::ensureAccess($request->user(), $project);
        }

        return $next($request);
    }
}
