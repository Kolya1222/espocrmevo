<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('oidc_auth_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_id', 100)->unique();
            $table->unsignedInteger('user_id');
            $table->string('client_id', 80);
            $table->text('scopes')->nullable();
            $table->string('nonce', 255)->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index('code_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oidc_auth_codes');
    }
};