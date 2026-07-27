<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interpretation_templates', function (Blueprint $t) {
            $t->id();
            $t->string('template_key', 120);
            $t->string('locale', 10)->default('ko-KR');
            $t->string('version', 30)->default('1.0.0');
            $t->text('text');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['template_key', 'locale', 'version']);
        });

        Schema::create('report_shares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained('test_attempts')->cascadeOnDelete();
            $t->string('audience', 30)->default('guardian');
            $t->string('token', 64)->unique();
            $t->string('source', 20); // youth_self | staff
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('viewed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('consent_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained('test_attempts')->cascadeOnDelete();
            $t->string('consent_type', 30); // sensitive | guardian_offline
            $t->boolean('granted')->default(true);
            $t->timestamp('granted_at');
            $t->string('actor', 20); // youth | staff
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['attempt_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('report_shares');
        Schema::dropIfExists('interpretation_templates');
    }
};
