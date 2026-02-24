<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browser_locations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->string('accuracy_level', 20)->nullable();
            $table->boolean('is_accurate')->default(false);
            $table->string('permission_state', 20)->nullable();
            $table->unsignedTinyInteger('error_code')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('source', 100)->default('html5_geolocation');
            $table->json('meta')->nullable();
            $table->timestamp('captured_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_locations');
    }
};
