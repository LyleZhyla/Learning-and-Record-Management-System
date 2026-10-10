<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40)->index();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('outcome', 30)->index();
            $table->string('color', 7)->default('#687589');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scope', 'slug']);
        });

        $now = now();
        DB::table('review_categories')->insert([
            ['scope' => 'registration', 'name' => 'Pending review', 'slug' => 'pending', 'outcome' => 'pending', 'color' => '#d18a16', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'registration', 'name' => 'Under review', 'slug' => 'under_review', 'outcome' => 'in_progress', 'color' => '#2f73c8', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'registration', 'name' => 'Approved / account created', 'slug' => 'verified', 'outcome' => 'approved', 'color' => '#168865', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'registration', 'name' => 'Needs correction', 'slug' => 'needs_correction', 'outcome' => 'correction', 'color' => '#c44f61', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'registration_document', 'name' => 'Pending review', 'slug' => 'pending', 'outcome' => 'pending', 'color' => '#d18a16', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'registration_document', 'name' => 'Verified', 'slug' => 'verified', 'outcome' => 'approved', 'color' => '#168865', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'registration_document', 'name' => 'Needs correction', 'slug' => 'needs_correction', 'outcome' => 'correction', 'color' => '#c44f61', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'document_submission', 'name' => 'Pending review', 'slug' => 'pending', 'outcome' => 'pending', 'color' => '#d18a16', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'document_submission', 'name' => 'Verified', 'slug' => 'verified', 'outcome' => 'approved', 'color' => '#168865', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'document_submission', 'name' => 'Needs correction', 'slug' => 'needs_correction', 'outcome' => 'correction', 'color' => '#c44f61', 'is_active' => true, 'is_default' => true, 'is_system' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (Schema::hasTable('roles')) {
            foreach (config('role_permissions.defaults', []) as $baseRole => $permissions) {
                DB::table('roles')->where('is_system', true)->where('base_role', $baseRole)
                    ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_categories');
    }
};
