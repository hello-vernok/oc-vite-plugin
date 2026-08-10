<?php

namespace Vernok\Vite\Tests\Unit\Classes\Support;

use PHPUnit\Framework\TestCase;
use Vernok\Vite\Classes\Support\ViteScopeResolver;

/**
 * @covers \Vernok\Vite\Classes\Support\ViteScopeResolver
 */
class ViteScopeResolverTest extends TestCase
{
    public function test_it_prefers_theme_scope_for_root_entrypoint(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(null, null, ['resources/entrypoint.ts']);

        $this->assertSame('theme', $scope);
        $this->assertNull($pluginCode);
    }

    public function test_it_prefers_theme_scope_for_theme_image_paths(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(null, null, [], 'resources/images/logo.svg');

        $this->assertSame('theme', $scope);
        $this->assertNull($pluginCode);
    }

    public function test_it_treats_auto_scope_like_omitted_scope(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve('auto', null, ['resources/entrypoint.ts']);

        $this->assertSame('theme', $scope);
        $this->assertNull($pluginCode);
    }

    public function test_it_keeps_explicit_plugin_scope(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve('plugin', 'Acme.Blog', ['resources/modules/blog/entrypoint.ts']);

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }

    public function test_it_defaults_to_theme_without_hints(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(null, null, ['resources/modules/foo/entrypoint.ts']);

        $this->assertSame('theme', $scope);
        $this->assertNull($pluginCode);
    }

    public function test_it_uses_context_class_namespace_for_plugin_scope(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            null,
            null,
            ['resources/entrypoint.ts'],
            null,
            'Vernok\\ContentEditor\\Components\\Editor',
        );

        $this->assertSame('plugin', $scope);
        $this->assertSame('Vernok.ContentEditor', $pluginCode);
    }

    public function test_context_class_beats_theme_root_entrypoint_heuristic(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            null,
            null,
            ['resources/entrypoint.ts'],
            null,
            'Acme\\Blog\\Components\\ListView',
        );

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }

    public function test_explicit_theme_scope_overrides_context_class(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            'theme',
            null,
            ['resources/modules/blog/entrypoint.ts'],
            null,
            'Vernok\\ContentEditor\\Components\\Editor',
        );

        $this->assertSame('theme', $scope);
        $this->assertNull($pluginCode);
    }

    public function test_explicit_plugin_scope_uses_context_class_when_plugin_code_missing(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            'plugin',
            null,
            ['resources/modules/blog/entrypoint.ts'],
            null,
            'Acme\\Blog\\Components\\ListView',
        );

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }

    public function test_detect_plugin_code_from_class_returns_null_for_invalid_namespace(): void
    {
        $this->assertNull(ViteScopeResolver::detectPluginCodeFromClass('SingleClassName'));
        $this->assertNull(ViteScopeResolver::detectPluginCodeFromClass(null));
    }

    public function test_autodetect_scope_delegates_to_resolve_with_context_class(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::autodetectScope(null, null, 'Acme\\Blog\\Components\\ListView');

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }
}
