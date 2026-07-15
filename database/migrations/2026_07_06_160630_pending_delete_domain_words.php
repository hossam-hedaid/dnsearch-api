<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_delete_domain_words', function (Blueprint $table) {
            $table->foreignId('domain_id')->references('id')->on('pending_delete_domains')
                ->restrictOnDelete()
                ->restrictOnDelete();
            $table->foreignId('word_id')->references('id')->on('domain_words')
                ->restrictOnDelete()
                ->restrictOnDelete();
            $table->unsignedInteger('index');
            $table->unique(['domain_id', 'word_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_delete_domain_words');
    }
};
