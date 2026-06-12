<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_nodes', function (Blueprint $table) {
            // Niveau d'accréditation requis pour accéder au nœud. 0 = public.
            // Invariant : un enfant a toujours un niveau >= celui de son parent.
            $table->unsignedTinyInteger('access_level')->default(0)->after('rank_id');
        });
    }

    public function down(): void
    {
        Schema::table('file_nodes', function (Blueprint $table) {
            $table->dropColumn('access_level');
        });
    }
};
