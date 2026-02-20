<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table): void {
            $table->id();
            $table->morphs('translatable');
            $table->string('locale')->index();
            $table->string('attribute');
            $table->text('value')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'attribute'],
                'translations_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
