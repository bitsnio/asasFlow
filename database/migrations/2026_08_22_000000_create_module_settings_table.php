<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_settings', function (Blueprint $table) {
            $table->id();

            /*
             * Example:
             * inventory
             * admin
             * moneytransfer
             */
            $table->string('module');

            /*
             * NULL = module/global level
             *
             * company_id != NULL = company level
             */
            $table->unsignedBigInteger('company_id')->nullable();

            /*
             * NULL = company/module level
             *
             * site_id != NULL = site level
             */
            $table->unsignedBigInteger('site_id')->nullable();

            /*
             * All persisted overrides for this scope.
             */
            $table->json('values')->nullable();

            $table->timestamps();

            /*
             * One settings document per module/scope.
             *
             * NULL handling differs between MySQL/PostgreSQL,
             * therefore we do not use one simple unique constraint
             * for all three nullable columns.
             */
            $table->index([
                'module',
                'company_id',
                'site_id',
            ],"module_settings_unique_index");

            $table->index('module');
            $table->index('company_id');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_settings');
    }
};