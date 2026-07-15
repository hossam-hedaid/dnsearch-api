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
        Schema::create('pending_delete_domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('expiration_date');
            $table->float('grammar_score');
            $table->boolean('valid_grammar');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_delete_domains');
    }
};
