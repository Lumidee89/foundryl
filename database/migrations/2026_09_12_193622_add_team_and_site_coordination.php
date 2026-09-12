<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL commits DDL before a migration finishes; preserve completed steps on retry.
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('organization_id')->constrained();
                $t->string('name');
                $t->timestamps();
                $t->unique(['organization_id', 'name']);
            });
        }
        if (! Schema::hasTable('invitations')) {
            Schema::create('invitations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('organization_id')->constrained();
                $t->string('email');
                $t->string('role');
                $t->json('project_ids');
                $t->string('department')->nullable();
                $t->string('token_hash', 64)->unique();
                $t->foreignId('invited_by')->constrained('users');
                $t->timestamp('expires_at');
                $t->timestamp('accepted_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('organization_users', 'department')) {
            Schema::table('organization_users', function (Blueprint $t) {
                $t->string('department')->nullable();
            });
        }
        if (! Schema::hasTable('site_instructions')) {
            Schema::create('site_instructions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('organization_id')->constrained();
                $t->foreignId('project_id');
                $t->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
                $t->string('title');
                $t->text('description');
                $t->foreignId('assigned_to')->constrained('users');
                $t->foreignId('created_by')->constrained('users');
                $t->date('due_date');
                $t->string('status')->default('Issued');
                $t->unsignedInteger('version')->default(1);
                $t->text('completion_notes')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('workspace_notifications')) {
            Schema::create('workspace_notifications', function (Blueprint $t) {
                $t->id();
                $t->foreignId('organization_id')->constrained();
                $t->foreignId('project_id')->nullable()->constrained();
                $t->foreignId('user_id')->constrained();
                $t->string('title');
                $t->string('url');
                $t->timestamp('read_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['organization_id', 'user_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_notifications');
        Schema::dropIfExists('site_instructions');
        Schema::table('organization_users', fn (Blueprint $t) => $t->dropColumn('department'));
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('departments');
    }
};
