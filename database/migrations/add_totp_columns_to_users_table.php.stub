<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(app(config('totp-login.model'))->getTable(), static function (Blueprint $table): void {
            $table->string(config('totp-login.columns.code'))->nullable();
            $table->timestamp(config('totp-login.columns.code_valid_until'))->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(app(config('totp-login.model'))->getTable(), static function (Blueprint $table): void {
            $table->dropColumn([
                config('totp-login.columns.code'),
                config('totp-login.columns.code_valid_until'),
            ]);
        });
    }
};
