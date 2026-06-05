<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->string('user_type')->default('agent')->after('role_id');
            $table->string('extension')->nullable()->after(Schema::hasColumn('users', 'phone') ? 'phone' : 'user_type');
            $table->string('status')->default('active')->after('extension');
            $table->boolean('is_super_admin')->default(false)->after('status');
            $table->string('theme_preference')->nullable()->after('is_super_admin');
            $table->unsignedInteger('items_per_page')->default(15)->after('theme_preference');

            $table->unique(['company_id', 'extension']);
            $table->unique(['company_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'id']);
            $table->dropUnique(['company_id', 'extension']);
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn([
                'user_type',
                'extension',
                'status',
                'is_super_admin',
                'theme_preference',
                'items_per_page',
            ]);
        });
    }
};
