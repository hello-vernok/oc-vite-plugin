<?php

namespace Vernok\Vite\Tests\Unit\Classes\Support;

use PHPUnit\Framework\TestCase;
use Vernok\Vite\Classes\Support\ViteAssetToken;

/**
 * @covers \Vernok\Vite\Classes\Support\ViteAssetToken
 */
class ViteAssetTokenTest extends TestCase
{
    public function test_it_parses_a_plain_entry_from_a_prefixed_asset_path(): void
    {
        $parsed = ViteAssetToken::parse('plugins/acme/blog/assets/vite:resources/js/app.ts');

        $this->assertNotNull($parsed);
        $this->assertNull($parsed['scope']);
        $this->assertNull($parsed['pluginCode']);
        $this->assertSame('resources/js/app.ts', $parsed['entry']);
    }

    public function test_it_parses_explicit_theme_scope(): void
    {
        $parsed = ViteAssetToken::parse('vite:theme:resources/entrypoint.ts');

        $this->assertSame('theme', $parsed['scope']);
        $this->assertNull($parsed['pluginCode']);
        $this->assertSame('resources/entrypoint.ts', $parsed['entry']);
    }

    public function test_it_parses_explicit_plugin_scope(): void
    {
        $parsed = ViteAssetToken::parse('vite:plugin:Acme.Blog:resources/modules/blog/entrypoint.ts');

        $this->assertSame('plugin', $parsed['scope']);
        $this->assertSame('Acme.Blog', $parsed['pluginCode']);
        $this->assertSame('resources/modules/blog/entrypoint.ts', $parsed['entry']);
    }

    public function test_it_returns_null_for_values_without_a_token(): void
    {
        $this->assertNull(ViteAssetToken::parse('/themes/demo/assets/js/app.js'));
        $this->assertFalse(ViteAssetToken::contains('/themes/demo/assets/js/app.js'));
    }
}
