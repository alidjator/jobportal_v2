<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per finished erasure step of an anonymised account. No foreign key: the receipt must outlive any change to the user row.
        Schema::create('identity_erasure_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id');
            $table->string('step', 64);
            $table->timestamp('completed_at')->useCurrent();

            $table->primary(['user_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_erasure_receipts');
    }
};
