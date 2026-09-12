<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->unsignedBigInteger('project_id');
            $table->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
            $table->string('name', 160);
            $table->string('vehicle', 80)->nullable();
            $table->string('purpose', 500);
            $table->foreignId('host_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('authorized_by')->nullable()->constrained('users');
            $table->string('status')->default('Pending');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'project_id', 'status']);
        });
        Schema::create('worker_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->unsignedBigInteger('project_id');
            $table->foreign(['organization_id', 'project_id'])->references(['organization_id', 'id'])->on('projects');
            $table->foreignId('worker_id')->constrained();
            $table->foreignId('activity_id')->constrained('activities');
            $table->foreignId('supervisor_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->string('team', 120);
            $table->string('location', 160);
            $table->date('work_date');
            $table->time('expected_at');
            $table->timestamps();
            $table->unique(['worker_id', 'work_date']);
            $table->index(['organization_id', 'project_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_deployments');
        Schema::dropIfExists('visitor_entries');
    }
};
