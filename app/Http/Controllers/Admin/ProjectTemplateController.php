<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ProjectTemplates/Index', [
            'templates' => ProjectTemplate::query()->orderBy('name')->get()->map->toPayload(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'payload' => ['required', 'array'],
        ]);

        ProjectTemplate::query()->create($validated);

        return back();
    }

    public function update(Request $request, ProjectTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'payload' => ['sometimes', 'array'],
        ]);

        $template->update($validated);

        return back();
    }

    public function destroy(ProjectTemplate $template): RedirectResponse
    {
        $template->delete();

        return back();
    }
}
