<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Customers
        // ------------------------------------------------------------------
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('contact_number', 32);
            $table->string('password');
            $table->string('profile_photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });

        // ------------------------------------------------------------------
        // Admins — separate guard/provider from customers, never mixed.
        // ------------------------------------------------------------------
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username', 64)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'manager', 'staff'])->default('staff')->index();
            $table->string('profile_photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // ------------------------------------------------------------------
        // Salon operating configuration (drives booking date/time validation).
        // ------------------------------------------------------------------
        Schema::create('salon_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Balai ti Arjud');
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            // ['monday' => ['09:00', '18:00'], ...] — closed days omitted.
            $table->json('operating_hours');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(30);
            $table->unsignedSmallInteger('booking_lead_days')->default(60);
            $table->boolean('down_payment_required')->default(true);
            $table->unsignedTinyInteger('down_payment_percentage')->default(50);
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Services
        // ------------------------------------------------------------------
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
        });

        // Short/long hair style pricing for a service.
        Schema::create('service_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['service_id', 'name']);
        });

        // ------------------------------------------------------------------
        // Inventory
        // ------------------------------------------------------------------
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 64)->unique();
            $table->string('category')->index();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit', 24)->default('pcs');
            $table->decimal('reorder_threshold', 12, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->enum('status_tag', ['available', 'low_stock', 'best_seller', 'sold_out'])
                ->default('available')
                ->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Which services consume which items (drives auto low-stock flagging).
        Schema::create('service_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_per_service', 12, 2)->default(1);
            $table->timestamps();

            $table->unique(['service_id', 'inventory_item_id']);
        });

        // ------------------------------------------------------------------
        // Blocked dates (admin configured; validated on customer booking)
        // ------------------------------------------------------------------
        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date')->default(null);
            // null scope = blocks every service
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        // ------------------------------------------------------------------
        // Appointments
        // ------------------------------------------------------------------
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 32)->unique();

            // Nullable: the salon accepts walk-in / guest bookings.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('customer_email')->nullable();

            $table->date('preferred_date')->index();
            $table->time('preferred_time');

            $table->text('allergies')->nullable();
            $table->text('last_services_availed')->nullable();
            $table->foreignId('preferred_stylist_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('special_request')->nullable();

            // Manual GCash reference verification — no payment gateway.
            $table->string('down_payment_reference', 64)->nullable();
            $table->decimal('down_payment_amount', 10, 2)->nullable();
            $table->enum('down_payment_status', ['unverified', 'verified', 'rejected', 'not_required'])
                ->default('unverified')
                ->index();

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])
                ->default('pending')
                ->index();
            $table->string('source', 24)->default('web');

            // Internal only — never rendered on customer-facing views.
            $table->text('admin_notes')->nullable();

            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('reschedule_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('started_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['preferred_date', 'preferred_time']);
            $table->index(['user_id', 'status']);
        });

        // Services (and chosen variant) captured per appointment, price snapshotted.
        Schema::create('appointment_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->string('variant_name')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['appointment_id', 'service_id']);
        });

        Schema::create('appointment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->enum('from_status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->nullable();
            $table->enum('to_status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled']);
            $table->enum('changed_by', ['admin', 'customer', 'system'])->default('system');
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'created_at']);
        });

        // ------------------------------------------------------------------
        // Reviews — one per completed appointment
        // ------------------------------------------------------------------
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('message');
            $table->string('customer_name');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rating', 'created_at']);
        });

        // ------------------------------------------------------------------
        // Contact messages
        // ------------------------------------------------------------------
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->enum('topic', [
                'general_inquiry', 'booking_question', 'service_question',
                'pricing_question', 'product_inquiry', 'feedback_complaint', 'others',
            ])->index();
            $table->text('message');
            $table->boolean('is_read')->default(false)->index();
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Terms & Conditions — versioned per category
        // ------------------------------------------------------------------
        Schema::create('terms_and_conditions', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['booking', 'cancellation', 'rescheduling'])->index();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['category', 'version']);
            $table->index(['category', 'is_published']);
        });

        // ------------------------------------------------------------------
        // Promos / announcements
        // ------------------------------------------------------------------
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->date('starts_at')->index();
            $table->date('ends_at')->index();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('notified')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        // ------------------------------------------------------------------
        // Password reset: 6-digit code, stored hashed, with expiry.
        // ------------------------------------------------------------------
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash', 255);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
        Schema::dropIfExists('promos');
        Schema::dropIfExists('terms_and_conditions');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('appointment_status_history');
        Schema::dropIfExists('appointment_service');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('blocked_dates');
        Schema::dropIfExists('service_inventory');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('service_variants');
        Schema::dropIfExists('services');
        Schema::dropIfExists('salon_settings');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('users');
    }
};
