<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kumon admin platform schema (squashed). Fresh installs: migrate + db:seed only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('street_address_1')->nullable();
            $table->string('street_address_2')->nullable();
            $table->string('street_address_3')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();
            $table->longText('logo')->nullable();
            $table->string('timezone', 100)->default('America/New_York');
            $table->string('date_format', 32)->default('mm/dd/yy');
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('admin_companies')->cascadeOnDelete();
            $table->string('student_id', 64);
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'student_id']);
        });

        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->json('applicable_roles')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            if ($teams || config('permission.testing')) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->foreignId('company_id')->constrained('admin_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('guard_name');
            $table->boolean('status')->default(true);
            $table->boolean('system')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['company_id', 'name', 'guard_name'], 'roles_company_name_guard_unique');
            }
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');
                $table->primary([$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');
                $table->primary([$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('admin_companies')->cascadeOnDelete();
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_image')->nullable();
            $table->boolean('dark_mode')->default(false);
            $table->json('table_preferences')->nullable();
            $table->string('timezone', 100)->nullable();
            $table->timestamp('first_login_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('admin_roles')->nullOnDelete();
        });

        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admin_user_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('admin_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('device_fingerprint');
            $table->string('device_name')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_fingerprint']);
        });

        Schema::create('admin_user_login_otps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('admin_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('device_fingerprint');
            $table->string('otp_code', 6);
            $table->string('session_token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('admin_email_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('admin_companies')->cascadeOnDelete();
            $table->string('slug');
            $table->unique(['company_id', 'slug'], 'admin_email_templates_company_slug_unique');
            $table->string('name');
            $table->string('subject_template', 255)->nullable();
            $table->string('from', 64)->nullable();
            $table->string('display_name')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('reply_to_name')->nullable();
            $table->json('bcc_recipients')->nullable();
            $table->longText('intro_html')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->json('detail_row_keys')->nullable();
            $table->json('detail_row_labels')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('admin_scheduled_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('handler');
            $table->string('endpoint');
            $table->string('token_config_key');
            $table->string('frequency')->default('0 * * * *');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->decimal('last_run_duration', 8, 3)->nullable();
            $table->timestamps();
            $table->index('enabled');
        });

        Schema::create('admin_scheduled_job_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('admin_companies')->nullOnDelete();
            $table->foreignId('scheduled_job_id')
                ->constrained('admin_scheduled_jobs')
                ->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->decimal('duration', 8, 3)->nullable();
            $table->string('status')->default('running');
            $table->string('trigger', 32)->default('schedule');
            $table->foreignId('triggered_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->longText('output')->nullable();
            $table->timestamps();
            $table->index(['scheduled_job_id', 'started_at']);
            $table->index('status');
        });

        Schema::create('admin_email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('admin_companies')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->string('template_slug', 255)->nullable();
            $table->foreignId('admin_email_template_id')
                ->nullable()
                ->constrained('admin_email_templates')
                ->nullOnDelete();
            $table->text('recipient');
            $table->text('subject')->nullable();
            $table->string('mailgun_message_id', 512)->nullable();
            $table->text('mailgun_send_message')->nullable();
            $table->string('status', 40)->default('queued');
            $table->string('failure_reason', 2048)->nullable();
            $table->string('mailgun_severity', 32)->nullable();
            $table->unsignedBigInteger('mailgun_last_event_ts')->nullable();
            $table->json('mailgun_events_snapshot')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index('status');
            $table->index('mailgun_message_id');
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('admin_companies')->nullOnDelete();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('event')->nullable();
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('admin_email_logs');
        Schema::dropIfExists('admin_scheduled_job_runs');
        Schema::dropIfExists('admin_scheduled_jobs');
        Schema::dropIfExists('admin_email_templates');
        Schema::dropIfExists('admin_user_login_otps');
        Schema::dropIfExists('admin_user_devices');
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('admin_users');

        $tableNames = config('permission.table_names');
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
        Schema::dropIfExists('students');
        Schema::dropIfExists('admin_companies');
    }
};
