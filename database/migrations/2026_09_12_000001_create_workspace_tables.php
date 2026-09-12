<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('organization_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role')->default('viewer');
            $t->unique(['organization_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->string('location');
            $t->string('status')->default('Active');
            $t->date('start_date')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'id']);
        });
        Schema::create('project_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('project_id');
            $t->foreignId('user_id')->constrained();
            $t->unique(['project_id', 'user_id']);
            $t->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
        });
        Schema::create('workers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('project_id');
            $t->string('name');
            $t->string('trade');
            $t->string('phone')->nullable();
            $t->timestamps();
            $t->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
            $t->unique(['organization_id', 'project_id', 'id']);
        });
        Schema::create('gate_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('project_id');
            $t->foreignId('worker_id');
            $t->timestamp('arrived_at');
            $t->timestamp('departed_at')->nullable();
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
            $t->foreign(['organization_id', 'project_id', 'worker_id'])->references(['organization_id', 'project_id', 'id'])->on('workers');
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('project_id');
            $t->string('title');
            $t->string('location');
            $t->string('responsible');
            $t->date('planned_date');
            $t->string('status')->default('Planned');
            $t->unsignedSmallInteger('progress')->default(0);
            $t->text('reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
        });
        Schema::create('diary_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('project_id');
            $t->string('title');
            $t->text('notes');
            $t->string('weather')->nullable();
            $t->date('entry_date');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
        });
        Schema::create('directions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('project_id');
            $t->text('message');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
        });
        Schema::create('direction_acknowledgments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('direction_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->timestamp('acknowledged_at');
            $t->unique(['direction_id', 'user_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('project_id')->nullable()->constrained();
            $t->foreignId('actor_user_id')->constrained('users');
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->json('previous_state')->nullable();
            $t->json('new_state')->nullable();
            $t->uuid('request_id');
            $t->timestamp('created_at');
            $t->index(['organization_id', 'project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'direction_acknowledgments', 'directions', 'diary_entries', 'activities', 'gate_entries', 'workers', 'project_users', 'projects', 'organization_users', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
