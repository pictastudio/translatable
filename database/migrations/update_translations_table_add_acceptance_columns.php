<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table): void {
            if (!Schema::hasColumn('translations', 'generated_by')) {
                $table->string('generated_by')->default('user')->after('value')->index();
            }

            if (!Schema::hasColumn('translations', 'accepted_at')) {
                $table->dateTime('accepted_at')->nullable()->after('generated_by')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table): void {
            if (Schema::hasColumn('translations', 'accepted_at')) {
                $table->dropIndex(['accepted_at']);
                $table->dropColumn('accepted_at');
            }

            if (Schema::hasColumn('translations', 'generated_by')) {
                $table->dropIndex(['generated_by']);
                $table->dropColumn('generated_by');
            }
        });
    }
};
