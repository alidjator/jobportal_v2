<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-requisitions.md` section 3.1: the documents a requester attaches to a requisition (a budget note, an organisation chart, a letter of the leaving employee). The file sits on the private disk. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_requisition_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisition_id')->constrained('workforce_requisitions')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('path', 255);
            $table->string('content_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by_id')->constrained('identity_users')->restrictOnDelete();
            $table->timestamps();

            $table->index('requisition_id', 'workforce_requisition_documents_requisition_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_requisition_documents');
    }
};
