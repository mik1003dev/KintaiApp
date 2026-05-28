<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStampRequestBreaksTable extends Migration
{
    public function up()
    {
        Schema::create('stamp_request_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_correction_request_id')
                ->constrained('stamp_correction_requests')
                ->cascadeOnDelete();
            $table->dateTime('break_start_at');
            $table->dateTime('break_end_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stamp_request_breaks');
    }
}
