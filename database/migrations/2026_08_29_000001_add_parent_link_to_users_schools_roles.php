<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_schools_roles', function (Blueprint $table) {
            $table->foreignId('linked_student_user_school_role_id')
                ->nullable()
                ->after('role_id')
                ->constrained('users_schools_roles')
                ->nullOnDelete();
            $table->string('student_access_code', 12)->unique()->nullable()->after('linked_student_user_school_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users_schools_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_student_user_school_role_id');
            $table->dropColumn('student_access_code');
        });
    }
};
