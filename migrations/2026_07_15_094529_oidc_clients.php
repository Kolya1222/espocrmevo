<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('oidc_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 80)->unique();
            $table->string('client_secret', 80)->nullable();
            $table->text('redirect_uri');
            $table->string('grant_types', 80)->nullable();
            $table->string('scope', 100)->nullable();
            $table->integer('user_id')->nullable();
            $table->string('name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('oidc_clients');
    }
};