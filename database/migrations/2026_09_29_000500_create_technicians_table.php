<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people who serve customers.
     *
     * Separate from `admins`: an admin is whoever holds the panel login, and a
     * technician is whoever a customer can ask for. The booking form offers
     * active technicians and nothing else.
     *
     * Name and photo only. `is_active` is the switch the admin flips instead of
     * deleting someone who is simply off today, and soft deletes mean a
     * technician can be retired without orphaning the appointments that name
     * them.
     */
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technicians');
    }
};
