<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->char('presence_status', 1)->default('P')->after('section_user_id');
            $table->char('justification_status', 1)->nullable()->after('presence_status');
        });

        DB::table('attendances')->where('is_present', true)->update(['presence_status' => 'P']);
        DB::table('attendances')->where('is_present', false)->update(['presence_status' => 'A', 'justification_status' => 'I']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('is_present');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_present')->default(true)->after('section_user_id');
        });

        DB::table('attendances')->where('presence_status', 'P')->update(['is_present' => true]);
        DB::table('attendances')->whereIn('presence_status', ['A', 'R'])->update(['is_present' => false]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['presence_status', 'justification_status']);
        });
    }
};
