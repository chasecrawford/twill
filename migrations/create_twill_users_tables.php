<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTwillUsersTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('twill.users_table', 'twill_users'), function (Blueprint $table) {
            createDefaultTableFields($table);
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password', 60)->nullable()->default(null);
            $table->string('role', 100);
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->rememberToken();
        });

        Schema::create(config('twill.password_resets_table', 'twill_password_resets'), function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('twill.password_resets_table', 'twill_password_resets'));
        Schema::dropIfExists(config('twill.users_table', 'twill_users'));
    }
}
