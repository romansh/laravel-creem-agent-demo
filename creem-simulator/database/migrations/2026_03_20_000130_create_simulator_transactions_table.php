<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('store')->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('product_id')->nullable()->index();
            $table->string('subscription_id')->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->index();
            $table->string('type')->default('payment');
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
