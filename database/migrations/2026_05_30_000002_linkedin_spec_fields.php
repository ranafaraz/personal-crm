<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── social_accounts: spec fields ──────────────────────────────────────
        Schema::table('social_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_accounts', 'public_profile_url')) {
                $table->string('public_profile_url', 500)->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('social_accounts', 'granted_scopes')) {
                $table->json('granted_scopes')->nullable()->after('scopes_json');
            }
            if (! Schema::hasColumn('social_accounts', 'missing_scopes')) {
                $table->json('missing_scopes')->nullable()->after('granted_scopes');
            }
            if (! Schema::hasColumn('social_accounts', 'capabilities')) {
                $table->json('capabilities')->nullable()->after('missing_scopes');
            }
        });

        // ── social_posts: spec fields ─────────────────────────────────────────
        Schema::table('social_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_posts', 'author_member_urn')) {
                $table->string('author_member_urn', 100)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('social_posts', 'first_comment_body')) {
                $table->text('first_comment_body')->nullable()->after('post_body');
            }
            if (! Schema::hasColumn('social_posts', 'content_version')) {
                $table->unsignedInteger('content_version')->default(1)->after('created_source');
            }
            if (! Schema::hasColumn('social_posts', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('content_version');
            }
            if (! Schema::hasColumn('social_posts', 'linkedin_post_urn')) {
                $table->string('linkedin_post_urn', 200)->nullable()->after('idempotency_key');
            }
            if (! Schema::hasColumn('social_posts', 'linkedin_post_url')) {
                $table->string('linkedin_post_url', 500)->nullable()->after('linkedin_post_urn');
            }
            if (! Schema::hasColumn('social_posts', 'linkedin_response_metadata')) {
                $table->json('linkedin_response_metadata')->nullable()->after('linkedin_post_url');
            }
        });

        // ── social_media_assets: spec fields ──────────────────────────────────
        Schema::table('social_media_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('social_media_assets', 'sha256_hash')) {
                $table->string('sha256_hash', 64)->nullable()->after('size_bytes');
            }
            if (! Schema::hasColumn('social_media_assets', 'linkedin_media_urn')) {
                $table->string('linkedin_media_urn', 200)->nullable()->after('sha256_hash');
            }
            if (! Schema::hasColumn('social_media_assets', 'linkedin_upload_status')) {
                $table->string('linkedin_upload_status', 20)->default('pending')->after('linkedin_media_urn');
            }
        });

        // ── social_post_confirmations ─────────────────────────────────────────
        if (! Schema::hasTable('social_post_confirmations')) {
            Schema::create('social_post_confirmations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('social_post_id')->constrained()->onDelete('cascade');
                // publish_now | schedule | edit_published | delete_published
                $table->string('action', 50);
                $table->unsignedInteger('content_version_snapshot');
                $table->string('body_hash', 64);
                $table->dateTime('scheduled_at')->nullable();
                $table->string('timezone', 100)->default('Asia/Karachi');
                $table->uuid('confirmation_token')->unique();
                // pending | approved | rejected | used | expired
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();

                $table->index(['social_post_id', 'status']);
                $table->index(['confirmation_token', 'status']);
            });
        }

        // ── social_analytics_snapshots ────────────────────────────────────────
        if (! Schema::hasTable('social_analytics_snapshots')) {
            Schema::create('social_analytics_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_account_id')->constrained()->onDelete('cascade');
                $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
                $table->string('analytics_scope', 20); // post | profile
                $table->string('metric_name', 100);
                $table->bigInteger('metric_value')->default(0);
                $table->string('aggregation', 20)->default('total'); // total | daily
                $table->date('date_range_start')->nullable();
                $table->date('date_range_end')->nullable();
                $table->dateTime('collected_at');
                $table->json('raw_provider_response')->nullable();
                $table->timestamps();

                $table->index(['social_account_id', 'analytics_scope', 'collected_at']);
                $table->index(['social_post_id', 'metric_name']);
            });
        }

        // ── social_audit_events ───────────────────────────────────────────────
        if (! Schema::hasTable('social_audit_events')) {
            Schema::create('social_audit_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event_type', 100);
                $table->string('event_status', 50); // success | failure | pending
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->string('confirmation_token', 100)->nullable();
                $table->json('safe_request_summary')->nullable();
                $table->json('safe_response_summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'event_type', 'created_at']);
                $table->index(['social_post_id', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_audit_events');
        Schema::dropIfExists('social_analytics_snapshots');
        Schema::dropIfExists('social_post_confirmations');

        Schema::table('social_media_assets', function (Blueprint $table) {
            foreach (['sha256_hash', 'linkedin_media_urn', 'linkedin_upload_status'] as $col) {
                if (Schema::hasColumn('social_media_assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('social_posts', function (Blueprint $table) {
            foreach (['author_member_urn', 'first_comment_body', 'content_version', 'idempotency_key',
                      'linkedin_post_urn', 'linkedin_post_url', 'linkedin_response_metadata'] as $col) {
                if (Schema::hasColumn('social_posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            foreach (['public_profile_url', 'granted_scopes', 'missing_scopes', 'capabilities'] as $col) {
                if (Schema::hasColumn('social_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
