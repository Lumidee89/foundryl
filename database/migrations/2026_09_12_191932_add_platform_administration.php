<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_superadmin')->default(false);
            $table->text('platform_totp_secret')->nullable();
            $table->timestamp('platform_totp_confirmed_at')->nullable();
            $table->unsignedBigInteger('platform_totp_last_step')->nullable();
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable();
        });
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->foreignId('organization_id')->nullable()->constrained();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('suspended_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_superadmin', 'platform_totp_secret', 'platform_totp_confirmed_at', 'platform_totp_last_step']));
    }
};
