<?php

namespace App\Services\Social;

class SocialProviderCatalog
{
    public static function definitions(): array
    {
        return [
            'linkedin' => [
                'name' => 'LinkedIn',
                'status' => 'enabled',
                'capabilities_json' => [
                    'features' => ['text', 'image', 'article_link', 'analytics'],
                    'auth_modes' => ['oauth2'],
                    'publish_enabled' => true,
                    'analytics_enabled' => true,
                    'connection_type' => 'oauth',
                ],
            ],
            'wordpress' => [
                'name' => 'WordPress',
                'status' => 'enabled',
                'capabilities_json' => [
                    'features' => ['html', 'image', 'featured_image', 'draft', 'publish'],
                    'auth_modes' => ['application_password'],
                    'publish_enabled' => true,
                    'analytics_enabled' => false,
                    'connection_type' => 'cms',
                ],
            ],
            'facebook' => self::manualProvider('Facebook', ['page_id', 'access_token', 'profile_url']),
            'instagram' => self::manualProvider('Instagram', ['business_account_id', 'access_token', 'profile_url']),
            'x' => self::manualProvider('X / Twitter', ['handle', 'access_token', 'profile_url']),
            'drupal' => self::manualProvider('Drupal', ['site_url', 'api_base', 'username', 'access_token'], 'cms'),
            'joomla' => self::manualProvider('Joomla', ['site_url', 'api_base', 'username', 'access_token'], 'cms'),
            'medium' => self::manualProvider('Medium', ['profile_url', 'access_token']),
            'youtube' => self::manualProvider('YouTube', ['channel_id', 'access_token', 'profile_url']),
            'tiktok' => self::manualProvider('TikTok', ['profile_url', 'access_token']),
            'threads' => self::manualProvider('Threads', ['profile_url', 'access_token']),
            'pinterest' => self::manualProvider('Pinterest', ['profile_url', 'access_token']),
            'mastodon' => self::manualProvider('Mastodon', ['site_url', 'access_token'], 'federated'),
            'bluesky' => self::manualProvider('Bluesky', ['handle', 'app_password'], 'federated'),
        ];
    }

    public static function get(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }

    public static function providerSupportsPublishing(?array $capabilities): bool
    {
        if (! $capabilities) {
            return false;
        }

        if (array_key_exists('publish_enabled', $capabilities)) {
            return (bool) $capabilities['publish_enabled'];
        }

        return in_array('publish', $capabilities, true)
            || in_array('text', $capabilities, true)
            || in_array('html', $capabilities, true);
    }

    private static function manualProvider(string $name, array $fields, string $type = 'social'): array
    {
        return [
            'name' => $name,
            'status' => 'enabled',
            'capabilities_json' => [
                'features' => ['profile', 'publishing_activity', 'manual_connection'],
                'auth_modes' => ['manual_token'],
                'manual_fields' => $fields,
                'publish_enabled' => false,
                'analytics_enabled' => false,
                'connection_type' => $type,
            ],
        ];
    }
}
