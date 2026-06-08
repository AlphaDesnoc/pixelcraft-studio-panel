<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rank_user', function (Blueprint $table) {
            $table->boolean('is_responsible')->default(false)->after('user_id');
        });

        // Migration de l'ancien responsable unique : il devient un membre marqué
        // responsable. On l'attache d'abord au rang s'il n'en faisait pas partie.
        $ranks = DB::table('ranks')->whereNotNull('responsible_id')->get(['id', 'responsible_id']);
        foreach ($ranks as $rank) {
            $exists = DB::table('rank_user')
                ->where('rank_id', $rank->id)
                ->where('user_id', $rank->responsible_id)
                ->exists();

            if ($exists) {
                DB::table('rank_user')
                    ->where('rank_id', $rank->id)
                    ->where('user_id', $rank->responsible_id)
                    ->update(['is_responsible' => true]);
            } else {
                DB::table('rank_user')->insert([
                    'rank_id' => $rank->id,
                    'user_id' => $rank->responsible_id,
                    'is_responsible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('ranks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_id');
        });
    }

    public function down(): void
    {
        Schema::table('ranks', function (Blueprint $table) {
            $table->foreignId('responsible_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
        });

        // On restaure un responsable unique (le premier de chaque rang).
        $seen = [];
        foreach (DB::table('rank_user')->where('is_responsible', true)->get(['rank_id', 'user_id']) as $row) {
            if (in_array($row->rank_id, $seen, true)) {
                continue;
            }
            $seen[] = $row->rank_id;
            DB::table('ranks')->where('id', $row->rank_id)->update(['responsible_id' => $row->user_id]);
        }

        Schema::table('rank_user', function (Blueprint $table) {
            $table->dropColumn('is_responsible');
        });
    }
};
