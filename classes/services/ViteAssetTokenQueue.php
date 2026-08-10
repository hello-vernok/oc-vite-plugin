<?php

namespace Vernok\Vite\Classes\Services;

/**
 * Request-scoped queue of vite: asset tokens intercepted before HTML rendering.
 */
class ViteAssetTokenQueue
{
    /**
     * @var array<int, array{path: string, type: string}>
     */
    protected array $items = [];

    /**
     * @param  string  $path
     * @param  string  $type
     *
     * @return void
     */
    public function push(string $path, string $type): void
    {
        $this->items[] = [
            'path' => $path,
            'type' => strtolower($type),
        ];
    }

    /**
     * @param  string|null  $type
     *
     * @return array<int, array{path: string, type: string}>
     */
    public function drain(?string $type = null): array
    {
        if ($type === null) {
            $items = $this->items;
            $this->items = [];

            return $items;
        }

        $type = strtolower($type);
        $matched = [];
        $remaining = [];

        foreach ($this->items as $item) {
            if ($item['type'] === $type) {
                $matched[] = $item;

                continue;
            }

            $remaining[] = $item;
        }

        $this->items = $remaining;

        return $matched;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
