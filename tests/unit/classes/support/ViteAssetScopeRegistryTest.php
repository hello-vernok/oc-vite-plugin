<?php

namespace Vernok\Vite\Tests\Unit\Classes\Support;

use PHPUnit\Framework\TestCase;
use Vernok\Vite\Classes\Support\ViteAssetScopeRegistry;

/**
 * @covers \Vernok\Vite\Classes\Support\ViteAssetScopeRegistry
 */
class ViteAssetScopeRegistryTest extends TestCase
{
    public function test_it_remembers_plugin_scope_from_context_class(): void
    {
        $registry = new ViteAssetScopeRegistry;
        $path = '/plugins/acme/blog/vite:resources/modules/blog/entrypoint.ts';

        $registry->remember($path, 'Acme\\Blog\\Components\\AssetRegistrationStub');

        [$scope, $pluginCode] = $registry->lookup($path);

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }

    public function test_it_normalizes_prefixed_paths_to_same_registry_key(): void
    {
        $registry = new ViteAssetScopeRegistry;
        $contextClass = 'Acme\\Blog\\Components\\AssetRegistrationStub';

        $registry->remember(
            '/plugins/acme/blog/vite:resources/modules/blog/entrypoint.ts',
            $contextClass,
        );

        [$scope, $pluginCode] = $registry->lookup('vite:resources/modules/blog/entrypoint.ts');

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }

    public function test_it_does_not_duplicate_scope_for_same_token(): void
    {
        $registry = new ViteAssetScopeRegistry;
        $contextClass = 'Acme\\Blog\\Components\\AssetRegistrationStub';
        $path = 'vite:resources/modules/blog/entrypoint.ts';

        $registry->remember($path, $contextClass);
        $registry->remember($path, 'Other\\Vendor\\Plugin\\Components\\Other');

        [$scope, $pluginCode] = $registry->lookup($path);

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }
}
