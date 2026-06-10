<?php

namespace App\Console\Commands;

use App\Models\FileNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateFilesToPrivate extends Command
{
    protected $signature = 'files:migrate-to-private';

    protected $description = 'Déplace les fichiers suivis du disque public vers le disque privé (local)';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $local = Storage::disk('files');

        $moved = 0;
        $missing = 0;

        FileNode::withTrashed()
            ->whereNotNull('path')
            ->where('type', FileNode::TYPE_FILE)
            ->cursor()
            ->each(function (FileNode $node) use ($public, $local, &$moved, &$missing) {
                if ($local->exists($node->path)) {
                    return; // déjà migré
                }
                if (! $public->exists($node->path)) {
                    $missing++;

                    return;
                }

                $stream = $public->readStream($node->path);
                $local->writeStream($node->path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $public->delete($node->path);
                $moved++;
            });

        $this->info("Fichiers déplacés : {$moved}");
        if ($missing > 0) {
            $this->warn("Fichiers introuvables sur le disque public : {$missing}");
        }

        return self::SUCCESS;
    }
}
