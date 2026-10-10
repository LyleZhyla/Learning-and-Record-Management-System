<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_project_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_project_activity_id')->nullable();
            $table->foreign('community_project_activity_id', 'project_documents_activity_fk')
                ->references('id')->on('community_project_activities')->nullOnDelete();
            $table->string('category', 30)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['community_project_id', 'community_project_activity_id'], 'project_document_activity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_project_documents');
    }
};
