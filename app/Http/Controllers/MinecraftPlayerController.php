<?php

namespace App\Http\Controllers;

use App\Models\MinecraftPlayer;
use App\Models\MinecraftServer;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MinecraftPlayerController extends Controller
{
    public function regenerateToken(Request $request, Project $project): RedirectResponse
    {
        $server = $project->minecraftServerOrCreate();
        $server->forceFill([
            'link_code' => MinecraftServer::generateLinkCode(),
            'token' => MinecraftServer::generateToken(),
            'linked_at' => null,
        ])->save();

        return back()->with('success', 'Identifiant régénéré. L\'ancien serveur est déconnecté ; reliez-le avec le nouvel identifiant.');
    }

    public function destroy(Request $request, Project $project, MinecraftPlayer $player): RedirectResponse
    {
        abort_unless($player->project_id === $project->id, 404);

        $player->delete();

        return back()->with('success', 'Joueur supprimé.');
    }

    public function clearOffline(Request $request, Project $project): RedirectResponse
    {
        $project->minecraftPlayers()->where('online', false)->delete();

        return back()->with('success', 'Joueurs hors-ligne supprimés.');
    }
}
