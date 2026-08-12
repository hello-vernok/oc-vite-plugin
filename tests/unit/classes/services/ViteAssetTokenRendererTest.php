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
        $queue->push('vite:plugin:Acme.Blog:resources/js/admin.ts', 'js', 'plugin', 'Acme.Blog');

        $renderer = new ViteAssetTokenRenderer($vite, $queue);

        $this->assertSame(
            '<script type="module" src="/plugins/acme/blog/assets/js/admin.js"></script>',
            $renderer->renderQueued(),
        );
        $this->assertTrue($queue->isEmpty());
    }

    public function test_it_uses_enqueue_time_scope_when_rendering_queued_tokens(): void
    {
        $vite = $this->createMock(Vite::class);
        $vite->method('renderTags')
            ->with(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog', 'js')
            ->willReturn('<script type="module" src="/plugins/acme/blog/assets/modules/blog/entrypoint.js"></script>');

        $queue = new ViteAssetTokenQueue;
        $queue->push(
            'vite:resources/modules/blog/entrypoint.ts',
            'js',
            'plugin',
            'Acme.Blog',
        );

        $renderer = new ViteAssetTokenRenderer($vite, $queue);

        $this->assertSame(
            '<script type="module" src="/plugins/acme/blog/assets/modules/blog/entrypoint.js"></script>',
            $renderer->renderQueued('js'),
        );
    }

    public function test_it_appends_queued_tokens_when_render_result_is_empty(): void
    {
        $vite = $this->createMock(Vite::class);
        $vite->method('renderTags')
            ->with(['resources/js/app.ts'], 'theme', null, 'js')
            ->willReturn('<script type="module" src="/themes/demo/assets/js/app.js"></script>');

        $queue = new ViteAssetTokenQueue;
        $queue->push('vite:resources/js/app.ts', 'js');

        $renderer = new ViteAssetTokenRenderer($vite, $queue);

        $this->assertSame(
            '<script type="module" src="/themes/demo/assets/js/app.js"></script>',
            $renderer->appendResolved('', 'js'),
        );
    }

    public function test_it_appends_pending_css_when_rendering_scripts(): void
    {
        $vite = $this->createMock(Vite::class);
        $vite->method('renderTags')
            ->willReturnMap([
                [
                    ['resources/modules/blog/entrypoint.ts'],
                    'plugin',
                    'Acme.Blog',
                    'js',
                    '<script type="module" src="/plugins/acme/blog/assets/modules/blog/entrypoint.js"></script>',
                ],
                [
                    ['resources/modules/blog/entrypoint.ts'],
                    'plugin',
                    'Acme.Blog',
                    'css',
                    '<link rel="stylesheet" href="/plugins/acme/blog/assets/modules/blog/entrypoint.css">',
                ],
            ]);

        $queue = new ViteAssetTokenQueue;
        $queue->push('vite:resources/modules/blog/entrypoint.ts', 'js', 'plugin', 'Acme.Blog');
        $queue->push('vite:resources/modules/blog/entrypoint.ts', 'css', 'plugin', 'Acme.Blog');

        $renderer = new ViteAssetTokenRenderer($vite, $queue);
        $output = $renderer->appendResolved('', 'js');

        $this->assertStringContainsString('entrypoint.js', $output);
        $this->assertStringContainsString('entrypoint.css', $output);
        $this->assertTrue($queue->isEmpty());
    }
}