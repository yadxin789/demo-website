<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    /**
     * 从 PM 参考项目完整迁移 AI 数据层：
     * 配置、供应商、模型、会话、消息、附件、技能、文档模板、变更提案、委托令牌。
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('deepseek');
            $table->string('api_key', 500)->nullable();
            $table->string('base_url', 500)->nullable();
            $table->string('model', 100)->default('deepseek-chat');
            $table->boolean('vision_enabled')->default(false);
            $table->string('vision_model', 100)->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('max_tokens')->default(2000);
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('rate_limit_minute')->default(30);
            $table->unsignedInteger('rate_limit_daily')->default(200);
            $table->timestamps();
        });

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 100);
            $table->string('base_url', 500)->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_id', 120);
            $table->string('name', 150);
            $table->boolean('is_vision')->default(false);
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['provider_id', 'model_id']);
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200)->default('新会话');
            $table->string('skill_slug', 100)->nullable();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system'])->default('user');
            $table->longText('content')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('ai_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['image', 'file'])->default('file');
            $table->string('original_name', 300);
            $table->string('path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('text_extracted')->default(false);
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('ai_doc_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 80)->default('通用');
            $table->text('description')->nullable();
            $table->string('template_path', 300)->nullable();
            $table->string('example_path', 300)->nullable();
            $table->json('support_files')->nullable();
            $table->text('prompt_guide')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('type', 30)->default('tool');
            $table->json('config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_delegation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 12);
            $table->json('scopes');
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('ai_change_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegation_token_id')->nullable()->constrained('ai_delegation_tokens')->nullOnDelete();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('action');
            $table->json('payload');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->string('confirm_token_hash', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolve_note')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['resource_type', 'resource_id']);
        });

        // laravel/ai 官方会话表（保留，供未来 Agent 持久化使用）
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('title');
            $table->timestamps();
            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
        });

        Schema::create('agent_conversation_messages', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('agent');
            $table->string('role', 25);
            $table->text('content');
            $table->text('attachments');
            $table->longText('steps');
            $table->text('usage');
            $table->text('meta');
            $table->string('status', 25);
            $table->timestamps();
            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversation_messages');
        Schema::dropIfExists('agent_conversations');
        Schema::dropIfExists('ai_change_proposals');
        Schema::dropIfExists('ai_delegation_tokens');
        Schema::dropIfExists('ai_skills');
        Schema::dropIfExists('ai_doc_templates');
        Schema::dropIfExists('ai_attachments');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
        Schema::dropIfExists('ai_settings');
    }
};
