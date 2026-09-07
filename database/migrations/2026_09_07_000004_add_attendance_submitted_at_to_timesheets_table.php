<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->timestamp('attendance_submitted_at')->nullable()->after('hours_done');
        });

        // Backfill : les feuilles de temps qui ont déjà au moins une présence
        // enregistrée (données antérieures à cette migration) doivent être
        // considérées comme déjà soumises, sous peine de laisser le cours
        // rééditable sans jamais notifier les parents d'une correction.
        // Bulk update en une seule requête (pas d'Eloquent, pas de boucle) :
        // table potentiellement volumineuse en production.
        DB::table('timesheets')
            ->whereExists(function ($query) {
                $query->selectRaw(1)
                    ->from('attendances')
                    ->whereColumn('attendances.timesheet_id', 'timesheets.id');
            })
            ->update([
                'attendance_submitted_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn('attendance_submitted_at');
        });
    }
};
