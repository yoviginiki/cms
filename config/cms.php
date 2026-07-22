<?php

return [

    'version' => env('CMS_VERSION', '1.0.0'),

    'redis_enabled' => env('REDIS_ENABLED', false),

    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'db_default' => env('REDIS_DB_DEFAULT', 0),
        'db_cache' => env('REDIS_DB_CACHE', 1),
        'db_session' => env('REDIS_DB_SESSION', 2),
        'db_queue' => env('REDIS_DB_QUEUE', 3),
        'prefix' => env('REDIS_PREFIX', 'cms_'),
    ],

    'drivers' => [
        'cache' => env('REDIS_ENABLED', false) ? 'redis' : 'file',
        'session' => env('REDIS_ENABLED', false) ? 'redis' : 'database',
        'queue' => env('REDIS_ENABLED', false) ? 'redis' : 'database',
        'broadcast' => env('REDIS_ENABLED', false) ? 'reverb' : null,
    ],

    'ai' => [
        'enabled' => env('AI_ENABLED', false),
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Point at a self-hosted / local Anthropic-compatible endpoint if desired.
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'model' => env('AI_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 2000),
    ],

    'issue_studio' => [
        // Sonnet drives the interview; Opus drives flatplan + spread generation.
        'model_interview' => env('ISSUE_STUDIO_MODEL_INTERVIEW', 'claude-sonnet-5'),
        'model_generate' => env('ISSUE_STUDIO_MODEL_GENERATE', 'claude-opus-4-8'),
        // Auto-source stock photos (Pexels) for image slots the author didn't
        // fill. Default state for new issues; overridable per-session via the
        // brief's auto_source_images flag. Needs services.pexels.key set.
        'auto_source_images' => env('ISSUE_STUDIO_AUTO_SOURCE_IMAGES', true),
    ],

    'site_wizard' => [
        // Whole-site builds from a crawled URL / uploaded design ZIP. The
        // pipeline is deterministic (DOM extraction + computed-style theme);
        // ai_polish adds an OPTIONAL vision pass over the reference when the
        // Anthropic key has credits — its failure never breaks a build.
        'max_pages' => (int) env('SITE_WIZARD_MAX_PAGES', 15),
        'zip_max_mb' => (int) env('SITE_WIZARD_ZIP_MAX_MB', 100),
        'zip_max_files' => (int) env('SITE_WIZARD_ZIP_MAX_FILES', 5000),
        'zip_max_uncompressed_mb' => (int) env('SITE_WIZARD_ZIP_MAX_UNCOMPRESSED_MB', 250),
        'max_images' => (int) env('SITE_WIZARD_MAX_IMAGES', 60),
        'ai_polish' => (bool) env('SITE_WIZARD_AI_POLISH', false),
    ],

    'theme_wizard' => [
        // Opus does the vision analysis of a reference; Sonnet routes the chat.
        'vision_model' => env('THEME_WIZARD_VISION_MODEL', 'claude-opus-4-8'),
        'chat_model' => env('THEME_WIZARD_CHAT_MODEL', 'claude-sonnet-5'),
        // node binary used for server-side reference screenshots (capture-url.mjs)
        'node_bin' => env('THEME_WIZARD_NODE_BIN', 'node'),
    ],

    'page_wizard' => [
        // Opus reads a screenshot into a page layout; Sonnet handles content
        // extraction, plain-description generation, and refinement nudges.
        'vision_model' => env('PAGE_WIZARD_VISION_MODEL', 'claude-opus-4-8'),
        'content_model' => env('PAGE_WIZARD_CONTENT_MODEL', 'claude-sonnet-5'),
        'max_blocks' => (int) env('PAGE_WIZARD_MAX_BLOCKS', 40),
    ],

    'database' => [
        'rls_enabled' => env('DB_CONNECTION') === 'pgsql',
        'driver' => env('DB_CONNECTION', 'mysql'),
    ],

    'updates' => [
        'server' => env('CMS_UPDATE_SERVER', 'https://updates.ensodo.eu'),
        'check_interval' => 86400, // 24 hours
    ],

];
