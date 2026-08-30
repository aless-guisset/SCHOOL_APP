<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_student_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_school_role_id')->constrained('users_schools_roles')->cascadeOnDelete();
            $table->foreignId('student_user_school_role_id')->constrained('users_schools_roles')->cascadeOnDelete();
            $table->string('status', 1)->default('A');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unique(['parent_user_school_role_id', 'student_user_school_role_id'], 'parent_student_links_unique');
        });

        // Migre les liens existants (colonne unique) vers la nouvelle table
        // avant de la supprimer — aucune perte de lien actif.
        DB::table('users_schools_roles')
            ->whereNotNull('linked_student_user_school_role_id')
            ->get(['id', 'linked_student_user_school_role_id', 'created_by', 'updated_by'])
            ->each(function ($row) {
                DB::table('parent_student_links')->insert([
                    'parent_user_school_role_id' => $row->id,
                    'student_user_school_role_id' => $row->linked_student_user_school_role_id,
                    'status' => 'A',
                    'is_active' => true,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users_schools_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_student_user_school_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users_schools_roles', function (Blueprint $table) {
            $table->foreignId('linked_student_user_school_role_id')
                ->nullable()
                ->constrained('users_schools_roles')
                ->nullOnDelete();
        });

        DB::table('parent_student_links')->orderBy('id')->each(function ($row) {
            DB::table('users_schools_roles')
                ->where('id', $row->parent_user_school_role_id)
                ->update(['linked_student_user_school_role_id' => $row->student_user_school_role_id]);
        });

        Schema::dropIfExists('parent_student_links');
    }
};
