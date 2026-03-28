<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulator_advance_results', function (Blueprint $table) {
            $table->id();
            $table->string('store')->index();
            $table->integer('events_count')->default(0);
            $table->integer('deliveries_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_advance_results');
    }
};
