<?php

namespace Acme\Blog\Components;

use Cms\Classes\ComponentBase;

/**
 * Test stub with a pre-registered vite asset path.
 */
class AssetRegistrationStub extends ComponentBase
{
    /**
     * @return array<string, mixed>
     */
    public function componentDetails(): array
    {
        return ['name' => 'Stub', 'description' => 'Stub'];
    }

    /**
     * @return array<string, mixed>
     */
    public function defineProperties(): array
    {
        return [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getAssetPaths(): array
    {
        return [
            'js' => ['/plugins/acme/blog/vite:resources/modules/blog/entrypoint.ts'],
            'css' => [],
        ];
    }
}
