<?php

namespace Vernok\Vite\Tests\Unit\Classes\Services;

use PHPUnit\Framework\TestCase;
use Vernok\Vite\Classes\Assets\Vite;
use Vernok\Vite\Classes\Services\ViteAssetTokenQueue;
use Vernok\Vite\Classes\Services\ViteAssetTokenRenderer;

/**
 * @covers \Vernok\Vite\Classes\Services\ViteAssetTokenRenderer
 */
class ViteAssetTokenRendererTest extends TestCase
{
    public function test_it_replaces_rendered_vite_script_lines(): void
    {
        $vite = $this->createMock(Vite::class);
        $vite->method('renderTags')
            ->with(['resources/js/app.ts'], 'theme', null, 'js')
            ->willReturn('<script type="module" src="/themes/demo/assets/js/app.js"></script>');

        $renderer = new ViteAssetTokenRenderer($vite, new ViteAssetTokenQueue);

        $input = '<script src="vite:resources/js/app.ts"></script>'."\n";
        $output = $renderer->replaceRenderedAssets($input, 'js');

        $this->assertStringContainsString('/themes/demo/assets/js/app.js', $output);
        $this->assertStringNotContainsString('vite:', $output);
    }

    public function test_it_renders_queued_tokens_for_backend_head_injection(): void
    {
        $vite = $this->createMock(Vite::class);
        $vite->method('renderTags')
            ->with(['resources/js/admin.ts'], 'plugin', 'Acme.Blog', 'js')
            ->willReturn('<script type="module" src="/plugins/acme/blog/assets/js/admin.js"></script>');

        $queue = new ViteAssetTokenQueue;
        $queue->push('vite:plugin:Acme.Blog:resources/js/admin.ts', 'js');

        $renderer = new ViteAssetTokenRenderer($vite, $queue);

        $this->assertSame(
            '<script type="module" src="/plugins/acme/blog/assets/js/admin.js"></script>',
            $renderer->renderQueued(),
        );
        $this->assertTrue($queue->isEmpty());
    }
}
