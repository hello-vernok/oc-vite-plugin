<?php

namespace Vernok\Vite\Tests\Unit\Classes\Support;

use PHPUnit\Framework\TestCase;
use Vernok\Vite\Classes\Support\ViteScopeResolver;

/**
 * @covers \Vernok\Vite\Classes\Support\ViteScopeResolver
 */
class ViteScopeResolverBacktraceTest extends TestCase
{
    public function test_it_skips_backtrace_fallback_when_disabled(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            null,
            null,
            ['resources/modules/foo/entrypoint.ts'],
            null,
            null,
            false,
        );

        $this->assertSame('theme', $scope);
        $this->assertNull($pluginCode);
    }

    public function test_it_uses_context_class_without_backtrace_fallback(): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            null,
            null,
            ['resources/modules/blog/entrypoint.ts'],
            null,
            'Acme\\Blog\\Components\\ListView',
            false,
        );

        $this->assertSame('plugin', $scope);
        $this->assertSame('Acme.Blog', $pluginCode);
    }
}
