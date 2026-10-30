<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('version', 40);
            $table->string('title', 180);
            $table->longText('content');
            $table->dateTime('effective_at');
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'version']);
        });

        Schema::create('privacy_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('privacy_notice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_email');
            $table->string('notice_version', 40);
            $table->string('purpose', 80)->index();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('accepted_at');
            $table->dateTime('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['institution_id', 'subject_email']);
        });

        Schema::create('retention_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('record_type', 80);
            $table->unsignedInteger('retention_days');
            $table->string('disposition', 20);
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('last_enforced_at')->nullable();
            $table->unsignedInteger('last_affected_count')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'record_type']);
        });

        Schema::create('data_privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_number', 50);
            $table->string('requester_name', 150);
            $table->string('requester_email');
            $table->string('request_type', 30)->index();
            $table->text('details');
            $table->string('status', 30)->default('submitted')->index();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'reference_number']);
        });

        Schema::create('sensitive_field_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 150);
            $table->string('actor_role', 40);
            $table->string('resource_type', 100);
            $table->string('resource_id', 80)->nullable();
            $table->json('field_categories');
            $table->string('purpose', 180);
            $table->string('route_name')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('accessed_at')->index();
            $table->timestamps();
        });

        Schema::create('privacy_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_number', 50);
            $table->string('title', 180);
            $table->text('description');
            $table->string('severity', 20)->index();
            $table->string('status', 30)->default('reported')->index();
            $table->dateTime('discovered_at');
            $table->text('containment_actions')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'reference_number']);
        });

        Schema::create('backup_restoration_drills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_name');
            $table->string('archive_checksum', 64);
            $table->string('status', 20)->index();
            $table->unsignedInteger('tables_detected')->default(0);
            $table->text('findings')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        $institutionIds = DB::table('institutions')->pluck('id');
        foreach ($institutionIds as $institutionId) {
            DB::table('privacy_notices')->insert([
                'institution_id' => $institutionId,
                'version' => '1.0',
                'title' => 'NSTP Data Privacy Notice',
                'content' => 'Personal information is collected only for NSTP registration, instruction, assessment, compliance reporting, student support, and authorized institutional operations. Access is limited to authorized personnel, retained under the institution policy, and protected by audit and security controls.',
                'effective_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ([
                ['audit_logs', 730, 'archive'],
                ['student_notifications', 365, 'delete'],
                ['ai_chat_messages', 180, 'delete'],
                ['sensitive_field_access_logs', 730, 'delete'],
                ['privacy_consents', 1825, 'anonymize'],
            ] as [$recordType, $days, $disposition]) {
                DB::table('retention_policies')->insert([
                    'institution_id' => $institutionId,
                    'record_type' => $recordType,
                    'retention_days' => $days,
                    'disposition' => $disposition,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restoration_drills');
        Schema::dropIfExists('privacy_incidents');
        Schema::dropIfExists('sensitive_field_access_logs');
        Schema::dropIfExists('data_privacy_requests');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('privacy_consents');
        Schema::dropIfExists('privacy_notices');
    }
};
