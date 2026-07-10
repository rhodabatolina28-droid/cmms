<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Users table indexes ──
        Schema::table('users', function (Blueprint $table) {
            $table->index('full_name', 'idx_users_full_name');
            $table->index('email', 'idx_users_email');
            $table->index('department', 'idx_users_department');
            $table->index('office', 'idx_users_office');
            $table->index('role', 'idx_users_role');
            $table->index('is_active', 'idx_users_is_active');
            $table->index('branch', 'idx_users_branch');
        });

        // ── Requests table indexes ──
        Schema::table('requests', function (Blueprint $table) {
            $table->index('type', 'idx_requests_type');
            $table->index('status', 'idx_requests_status');
            $table->index('division_admin_review_status', 'idx_requests_review_status');
            $table->index('assigned_to', 'idx_requests_assigned_to');
            $table->index('office', 'idx_requests_office');
            $table->index('request_number', 'idx_requests_request_number');
            $table->index('requestor_name', 'idx_requests_requestor_name');
            $table->index('created_at', 'idx_requests_created_at');
        });

        // ── Audit logs table indexes ──
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('module', 'idx_audit_logs_module');
            $table->index('action', 'idx_audit_logs_action');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_full_name');
            $table->dropIndex('idx_users_email');
            $table->dropIndex('idx_users_department');
            $table->dropIndex('idx_users_office');
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_is_active');
            $table->dropIndex('idx_users_branch');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex('idx_requests_type');
            $table->dropIndex('idx_requests_status');
            $table->dropIndex('idx_requests_review_status');
            $table->dropIndex('idx_requests_assigned_to');
            $table->dropIndex('idx_requests_office');
            $table->dropIndex('idx_requests_request_number');
            $table->dropIndex('idx_requests_requestor_name');
            $table->dropIndex('idx_requests_created_at');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_module');
            $table->dropIndex('idx_audit_logs_action');
        });
    }
};