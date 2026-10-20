<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sponsorship_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 150);
            $table->string('brand_name', 150)->nullable();
            $table->string('logo')->nullable();
            $table->string('phone', 30);
            $table->string('email', 150);
            $table->text('message');
            $table->boolean('is_anonymous')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_submissions');
    }
};
