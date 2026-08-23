<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('user_school_role_id')->nullable()->after('section_course_id')
                ->constrained('users_schools_roles')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->after('user_school_role_id')
                ->constrained('subjects')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->after('subject_id')
                ->constrained('classrooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_school_role_id');
            $table->dropConstrainedForeignId('subject_id');
            $table->dropConstrainedForeignId('classroom_id');
        });
    }
};
