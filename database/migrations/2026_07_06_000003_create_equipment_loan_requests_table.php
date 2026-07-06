<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_loan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->date('requested_from');
            $table->date('requested_to');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'requested_from', 'requested_to'], 'elr_user_status_period_idx');
            $table->index(['equipment_id', 'status', 'requested_from', 'requested_to'], 'elr_equipment_status_period_idx');
            $table->index(['status', 'requested_to'], 'elr_status_requested_to_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_loan_requests');
    }
};
