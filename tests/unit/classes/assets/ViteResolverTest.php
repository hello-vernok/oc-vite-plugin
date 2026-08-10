<?php

namespace Vernok\Vite\Tests\Unit\Classes\Assets;

use PHPUnit\Framework\TestCase;
use Vernok\Vite\Classes\Assets\Vite;

/**
 * @covers \Vernok\Vite\Classes\Assets\Vite
 */
class ViteResolverTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/vernok-vite-resolver-'.uniqid('', true);
        @mkdir($this->tmp.'/themes/labaz/assets/.vite', 0777, true);
        @mkdir($this->tmp.'/plugins/acme/blog/assets/.vite', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmp);
        parent::tearDown();
    }

    public function test_plugin_dev_theme_build(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":"http://localhost:5174","pid":123,"mode":"plugin"}');
        $this->writeManifest($this->tmp.'/themes/labaz/assets/.vite/manifest.json', [
            'resources/entrypoint.ts' => [
                'file' => 'js/entrypoint-a.js',
                'css'  => ['css/entrypoint-a.css'],
            ],
        ]);

        $vite = $this->makeResolver();

        $pluginAssets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');
        $themeAssets = $vite->resolveAssets(['resources/entrypoint.ts'], 'theme');

        $this->assertSame('http://localhost:5174/@vite/client', $pluginAssets['js'][0]['url']);
        $this->assertSame('http://localhost:5174/resources/modules/blog/entrypoint.ts', $pluginAssets['js'][1]['url']);
        $this->assertSame([], $pluginAssets['css']);

        $this->assertSame('/themes/labaz/assets/js/entrypoint-a.js', $themeAssets['js'][0]['url']);
        $this->assertSame('/themes/labaz/assets/css/entrypoint-a.css', $themeAssets['css'][0]['url']);
    }

    public function test_plugin_dev_works_without_manifest_file(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":"http://localhost:5174","pid":123,"mode":"plugin"}');

        $vite = $this->makeResolver();
        $assets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertSame('http://localhost:5174/@vite/client', $assets['js'][0]['url']);
        $this->assertSame('http://localhost:5174/resources/modules/blog/entrypoint.ts', $assets['js'][1]['url']);
        $this->assertSame([], $assets['css']);
    }

    public function test_theme_dev_works_without_manifest_file(): void
    {
        $this->writeFile($this->tmp.'/themes/labaz/.vite-dev.json', '{"origin":"http://localhost:5173","pid":456,"mode":"theme"}');

        $vite = $this->makeResolver();
        $assets = $vite->resolveAssets(['resources/entrypoint.ts'], 'theme');

        $this->assertSame('http://localhost:5173/@vite/client', $assets['js'][0]['url']);
        $this->assertSame('http://localhost:5173/resources/entrypoint.ts', $assets['js'][1]['url']);
        $this->assertSame([], $assets['css']);
    }

    public function test_theme_dev_plugin_build(): void
    {
        $this->writeFile($this->tmp.'/themes/labaz/.vite-dev.json', '{"origin":"http://localhost:5173","pid":456,"mode":"theme"}');
        $this->writeManifest($this->tmp.'/plugins/acme/blog/assets/.vite/manifest.json', [
            'resources/modules/blog/entrypoint.ts' => [
                'file' => 'modules/blog/entrypoint-a.js',
                'css'  => ['modules/blog/entrypoint-a.css'],
            ],
        ]);

        $vite = $this->makeResolver();

        $themeAssets = $vite->resolveAssets(['resources/entrypoint.ts'], 'theme');
        $pluginAssets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertSame('http://localhost:5173/@vite/client', $themeAssets['js'][0]['url']);
        $this->assertSame('http://localhost:5173/resources/entrypoint.ts', $themeAssets['js'][1]['url']);
        $this->assertSame([], $themeAssets['css']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.js', $pluginAssets['js'][0]['url']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.css', $pluginAssets['css'][0]['url']);
    }

    public function test_both_dev_different_origins(): void
    {
        $this->writeFile($this->tmp.'/themes/labaz/.vite-dev.json', '{"origin":"http://localhost:5173","pid":1,"mode":"theme"}');
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":"http://localhost:5174","pid":2,"mode":"plugin"}');

        $vite = $this->makeResolver();

        $themeAssets = $vite->resolveAssets(['resources/entrypoint.ts'], 'theme');
        $pluginAssets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertStringStartsWith('http://localhost:5173/', $themeAssets['js'][0]['url']);
        $this->assertStringStartsWith('http://localhost:5174/', $pluginAssets['js'][0]['url']);
        $this->assertSame([], $themeAssets['css']);
        $this->assertSame([], $pluginAssets['css']);
    }

    public function test_both_build_manifest_only(): void
    {
        $this->writeManifest($this->tmp.'/themes/labaz/assets/.vite/manifest.json', [
            'resources/entrypoint.ts' => ['file' => 'js/entrypoint-a.js', 'css' => ['css/entrypoint-a.css']],
        ]);
        $this->writeManifest($this->tmp.'/plugins/acme/blog/assets/.vite/manifest.json', [
            'resources/modules/blog/entrypoint.ts' => ['file' => 'modules/blog/entrypoint-a.js', 'css' => ['modules/blog/entrypoint-a.css']],
        ]);

        $vite = $this->makeResolver();

        $themeAssets = $vite->resolveAssets(['resources/entrypoint.ts'], 'theme');
        $pluginAssets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertSame('/themes/labaz/assets/js/entrypoint-a.js', $themeAssets['js'][0]['url']);
        $this->assertSame('/themes/labaz/assets/css/entrypoint-a.css', $themeAssets['css'][0]['url']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.js', $pluginAssets['js'][0]['url']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.css', $pluginAssets['css'][0]['url']);
    }

    public function test_build_resolves_manifest_import_chunks(): void
    {
        $this->writeManifest($this->tmp.'/plugins/acme/blog/assets/.vite/manifest.json', [
            '_bootstrap-abc123.js' => [
                'file' => 'bootstrap-abc123.js',
                'css'  => ['bootstrap/entrypoint-shared.css'],
            ],
            'resources/modules/blog/entrypoint.ts' => [
                'file'    => 'modules/blog/entrypoint-a.js',
                'imports' => ['_bootstrap-abc123.js'],
                'css'     => ['modules/blog/entrypoint-a.css'],
            ],
        ]);

        $vite = $this->makeResolver();
        $assets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertSame(
            [
                '/plugins/acme/blog/assets/bootstrap-abc123.js',
                '/plugins/acme/blog/assets/modules/blog/entrypoint-a.js',
            ],
            array_column($assets['js'], 'url'),
        );
        $this->assertSame(
            [
                '/plugins/acme/blog/assets/bootstrap/entrypoint-shared.css',
                '/plugins/acme/blog/assets/modules/blog/entrypoint-a.css',
            ],
            array_column($assets['css'], 'url'),
        );
    }

    public function test_asset_url_uses_dev_origin_and_manifest_public_base(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":"http://localhost:5174","pid":123,"mode":"plugin"}');
        $this->writeManifest($this->tmp.'/themes/labaz/assets/.vite/manifest.json', [
            'resources/images/logo.svg' => ['file' => 'images/logo-abc.svg'],
        ]);

        $vite = $this->makeResolver();

        $this->assertSame(
            'http://localhost:5174/resources/images/logo.svg',
            $vite->assetUrl('resources/images/logo.svg', 'plugin', 'Acme.Blog'),
        );
        $this->assertSame(
            '/themes/labaz/assets/images/logo-abc.svg',
            $vite->assetUrl('resources/images/logo.svg', 'theme'),
        );
    }

    public function test_render_tags_returns_html_comment_when_plugin_code_missing(): void
    {
        $vite = $this->makeResolver();

        $html = $vite->renderTags(['resources/modules/blog/entrypoint.ts'], 'plugin');

        $this->assertSame('<!-- Vite: plugin code missing -->', $html);
    }

    public function test_corrupted_dev_json_falls_back_to_build(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":');
        $this->writeManifest($this->tmp.'/plugins/acme/blog/assets/.vite/manifest.json', [
            'resources/modules/blog/entrypoint.ts' => [
                'file' => 'modules/blog/entrypoint-a.js',
                'css'  => ['modules/blog/entrypoint-a.css'],
            ],
        ]);

        $vite = $this->makeResolver();

        $assets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.js', $assets['js'][0]['url']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.css', $assets['css'][0]['url']);
    }

    public function test_vite_dev_marker_without_json_uses_build_mode(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev', '');
        $this->writeManifest($this->tmp.'/plugins/acme/blog/assets/.vite/manifest.json', [
            'resources/modules/blog/entrypoint.ts' => [
                'file' => 'modules/blog/entrypoint-a.js',
                'css'  => ['modules/blog/entrypoint-a.css'],
            ],
        ]);

        $vite = $this->makeResolver();
        $assets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.js', $assets['js'][0]['url']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.css', $assets['css'][0]['url']);
    }

    public function test_invalid_dev_json_origin_falls_back_to_build(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":"not-a-url","pid":2,"mode":"plugin"}');
        $this->writeManifest($this->tmp.'/plugins/acme/blog/assets/.vite/manifest.json', [
            'resources/modules/blog/entrypoint.ts' => [
                'file' => 'modules/blog/entrypoint-a.js',
                'css'  => ['modules/blog/entrypoint-a.css'],
            ],
        ]);

        $vite = $this->makeResolver();
        $assets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.js', $assets['js'][0]['url']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.css', $assets['css'][0]['url']);
    }

    public function test_dev_server_disabled_ignores_dev_json(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":"http://localhost:5174","pid":123,"mode":"plugin"}');
        $this->writeManifest($this->tmp.'/plugins/acme/blog/assets/.vite/manifest.json', [
            'resources/modules/blog/entrypoint.ts' => [
                'file' => 'modules/blog/entrypoint-a.js',
                'css'  => ['modules/blog/entrypoint-a.css'],
            ],
        ]);

        $vite = $this->makeResolver(devServerEnabled: false);
        $assets = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.js', $assets['js'][0]['url']);
        $this->assertSame('/plugins/acme/blog/assets/modules/blog/entrypoint-a.css', $assets['css'][0]['url']);
    }

    public function test_dev_vite_client_is_deduped_across_resolve_calls(): void
    {
        $this->writeFile($this->tmp.'/plugins/acme/blog/.vite-dev.json', '{"origin":"http://localhost:5174","pid":123,"mode":"plugin"}');

        $vite = $this->makeResolver();
        $first = $vite->resolveAssets(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog');
        $second = $vite->resolveAssets(['resources/modules/other/entrypoint.ts'], 'plugin', 'Acme.Blog');

        $clientUrls = array_filter(
            array_merge(array_column($first['js'], 'url'), array_column($second['js'], 'url')),
            static fn (string $url): bool => str_ends_with($url, '/@vite/client'),
        );

        $this->assertCount(1, $clientUrls);
    }

    private function makeResolver(bool $devServerEnabled = true): Vite
    {
        $tmp = $this->tmp;

        return new class($tmp, $devServerEnabled) extends Vite
        {
            public function __construct(
                private string $tmp,
                private bool $devServerEnabled,
            ) {}

            protected function isDevServerEnabled(): bool
            {
                return $this->devServerEnabled;
            }

            protected function getActiveThemeDir(): string
            {
                return 'labaz';
            }

            protected function getThemeManifestAndBase(): array
            {
                $manifest = $this->readManifest($this->tmp.'/themes/labaz/assets/.vite/manifest.json');

                return [$manifest, '/themes/labaz/assets/'];
            }

            protected function getPluginManifestAndBase(string $author, string $name): array
            {
                $manifest = $this->readManifest($this->tmp.'/plugins/'.strtolower($author).'/'.strtolower($name).'/assets/.vite/manifest.json');

                return [$manifest, '/plugins/'.strtolower($author).'/'.strtolower($name).'/assets/'];
            }

            protected function getThemeDevJsonFile(string $themeDir): string
            {
                return $this->tmp.'/themes/'.strtolower($themeDir).'/.vite-dev.json';
            }

            protected function getPluginDevJsonFile(string $author, string $name): string
            {
                return $this->tmp.'/plugins/'.strtolower($author).'/'.strtolower($name).'/.vite-dev.json';
            }

            protected function cfg(string $key, mixed $default = null): mixed
            {
                if ($key === 'debug_logging') {
                    return false;
                }

                return $default;
            }

            protected function shouldDebugLogging(): bool
            {
                return false;
            }
        };
    }

    private function writeManifest(string $path, array $manifest): void
    {
        $this->writeFile($path, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function writeFile(string $path, string $content): void
    {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
