<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->vector('embedding', dimensions: 768)->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'position']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
