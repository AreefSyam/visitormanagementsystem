<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->timestamp('check_in_at');
            $table->timestamp('check_out_at')->nullable();
            $table->enum('status', ['checked_in', 'checked_out', 'cancelled'])->default('checked_in');
            $table->string('badge_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('check_in_at');
            $table->index(['visitor_id', 'check_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
