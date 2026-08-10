# Vernok.Vite

PHP asset resolver for October CMS themes and plugins. Built for agencies and developers who ship production October CMS projects and need predictable Vite integration on the server — dev-server URLs during local development, manifest-driven hashed assets in production.

This plugin reads `.vite-dev.json` and `assets/.vite/manifest.json`, then exposes Twig functions, global helpers, traits, and the **`vite:`** asset protocol for `addJs` / `addCss`.

**Companion package (Node/Vite):** [`@vernok/vite-plugin-october`](https://www.npmjs.com/package/@vernok/vite-plugin-october) — install this in each theme or plugin repository for Vite config, entry autodiscovery, build output, and `.vite-dev.json` during local development. **Vernok.Vite** (this plugin) handles the PHP side only.

| Surface | Use when |
|---|---|
| **`vite:` + `addJs` / `addCss`** | **Default for PHP** — components, controllers, FormWidgets; no trait required |
| `vite_scripts()` / `vite_styles()` | Theme layouts and partials (Twig) |
| `HasViteAssets` | Optional `addViteAssets()` when your team prefers a named method |
| `vite_assets()` | Manual `addJs` / `addCss` loops (advanced, e.g. backend skin registration) |

## Table of contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick start](#quick-start)
- [Registering assets with `addJs` and `addCss`](#registering-assets-with-addjs-and-addcss)
- [Twig and PHP helpers](#twig-and-php-helpers)
- [Scope resolution](#scope-resolution)
- [Development vs production](#development-vs-production)
- [Docker and multi-owner setups](#docker-and-multi-owner-setups)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Known limitations](#known-limitations)
- [Related packages](#related-packages)

## Overview

October CMS frontend assets are split across two packages:

| Package | Runs in | Responsibility |
|---|---|---|
| [`@vernok/vite-plugin-october`](https://www.npmjs.com/package/@vernok/vite-plugin-october) | Plugin or theme repository (Node) | Vite config, entry autodiscovery, `assets/` build output, `.vite-dev.json` during `vite dev` |
| **Vernok.Vite** (this plugin) | October CMS application (PHP) | Dev-server detection, manifest resolution, rendering assets in Twig and PHP |

Agencies benefit from shared conventions — the same folder layout, manifest location, and cache-busting behaviour in every client repository. Developers reference **source entry paths** (for example `resources/modules/blog/entrypoint.ts`) from Twig or PHP; the resolver maps those paths to dev-server URLs or hashed production files. You never hard-code manifest hashes in templates or PHP.

- **Development:** run `npm run dev` in the plugin or theme directory. The npm package writes `.vite-dev.json`. **Vernok.Vite** reads `origin` and emits module scripts (including `@vite/client` once per origin per request).
- **Production:** run `npm run build`. Output lands under `assets/` with `assets/.vite/manifest.json`. **Vernok.Vite** resolves hashed filenames, shared chunks (`imports`), and CSS linked to each entry.

> **Node / Vite setup:** this README covers PHP only. For `vite.config.ts`, entry discovery, folder layout, Docker `hostUrl`, and build output paths, see the **[`@vernok/vite-plugin-october` documentation on npm](https://www.npmjs.com/package/@vernok/vite-plugin-october)**.

### Agency checklist

Before shipping a theme or plugin with Vite assets:

1. **October CMS app** — `composer require vernok/oc-vite-plugin:^0.0.1` (Packagist) or `php artisan plugin:install Vernok.Vite`
2. **Your plugin/theme** — `require: [Vernok.Vite]` in `plugin.yaml` or `theme.yaml`
3. **Asset repo** — `npm i -D @vernok/vite-plugin-october vite` and a `vite.config.ts` using `definePluginConfig()` or `defineThemeConfig()`
4. **Local dev** — `npm run dev` from the theme/plugin directory (not the October root)
5. **Deploy** — `npm run build` so `assets/.vite/manifest.json` exists; add `.vite-dev.json` to `.gitignore`

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.3 |
| October CMS | ^4.0 |
| Node.js | ≥ 24.11.1 (per theme/plugin repo; see npm package) |
| Vite | ^8.0 (per theme/plugin repo) |
| [`@vernok/vite-plugin-october`](https://www.npmjs.com/package/@vernok/vite-plugin-october) | latest compatible release (per theme/plugin repo) |

Each theme or plugin with frontend assets needs its own `package.json`, `vite.config.ts` (or `.js`), and `npm run dev` / `npm run build` scripts. Run those commands from the theme or plugin directory — not from the October project root.

## Installation

### October CMS application

**Via Composer (Packagist):**

```bash
composer require vernok/oc-vite-plugin:^0.0.1
php artisan october:up
```

**Via October CLI or marketplace:**

```bash
php artisan plugin:install Vernok.Vite
```

#### Declaring `Vernok.Vite` as a dependency

| You maintain | Declare in | Required? |
|---|---|---|
| October CMS **plugin** with Vite assets | `plugin.yaml` → `require` | **Yes** |
| Same plugin via **Composer** | Plugin `composer.json` → `require` | **Yes** — keep in sync with `plugin.yaml` |
| October CMS **theme** with Vite assets | `theme.yaml` → `require` | **Yes** |
| Same theme via **Composer** | Theme `composer.json` → `require` | **Yes** |

**Plugin — `plugin.yaml`:**

```yaml
require:
  - Vernok.Vite
```

**Plugin — `composer.json`** (Composer-distributed plugins only):

```json
{
  "require": {
    "vernok/oc-vite-plugin": "^0.0.1"
  }
}
```

**Theme — `theme.yaml`:**

```yaml
require:
  - Vernok.Vite
```

If the plugin or theme lives only under `plugins/` or `themes/` in a project repo, `plugin.yaml` or `theme.yaml` is sufficient. Add the Composer entry when you publish through Packagist or a private registry.

### Plugin or theme repository (Node)

```bash
npm i -D @vernok/vite-plugin-october vite
```

Use `definePluginConfig()` or `defineThemeConfig()` in `vite.config.ts`. Full setup, entry discovery rules, and output paths: **[`@vernok/vite-plugin-october` on npm](https://www.npmjs.com/package/@vernok/vite-plugin-october)**.

### Entry paths (what to pass to Twig / `vite:`)

Pass the **source path** as discovered by the npm package — the same string appears as a key in `assets/.vite/manifest.json`:

| Context | Typical entry path (examples) |
|---|---|
| Theme site bundle | `resources/entrypoint.ts` |
| Theme module | `resources/modules/<name>/entrypoint.ts` |
| Plugin module | `resources/modules/<name>/entrypoint.ts` |
| Plugin FormWidget | `resources/formwidgets/<name>/entrypoint.ts` |

```php
$this->addJs('vite:resources/formwidgets/color-picker/entrypoint.ts');
```

```twig
{{ vite_scripts(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog') }}
```

If a path 404s or the manifest reports "entry not found", open `assets/.vite/manifest.json` and copy the key exactly. Details on which folders are autodiscovered: [npm package docs](https://www.npmjs.com/package/@vernok/vite-plugin-october).

## Configuration

Config file: `plugins/vernok/vite/config/vite.php`  
October key prefix: `vernok.vite::vite`

| Variable | Default | Description |
|---|---|---|
| `VITE_DEV_SERVER_ENABLED` | `null` (auto) | `false` forces production manifest resolution even when `.vite-dev.json` exists |
| `VITE_RESOLVER_DEBUG` | `false` | When `true` and `app.debug=true`, writes resolver details to `Log::debug` |

| Scenario | Action |
|---|---|
| CI/staging must never use the dev server | `VITE_DEV_SERVER_ENABLED=false` |
| Wrong mode locally | Check for a stale `.vite-dev.json`; enable `VITE_RESOLVER_DEBUG=true` |

The dev-server `origin` is **not** configured in this plugin — it comes from `.vite-dev.json` (written by the npm package). For Docker, set `hostUrl` in the Vite project; see [Docker and multi-owner setups](#docker-and-multi-owner-setups).

## Quick start

**1. Build assets** in the theme or plugin repo:

```bash
cd themes/my-theme   # or plugins/vendor/plugin
npm install
npm run dev          # local development — writes .vite-dev.json
npm run build        # production — writes assets/.vite/manifest.json
```

**2. Theme layout (Twig):**

```twig
{% styles %}
    {{ vite_styles(['resources/entrypoint.ts']) }}
{% endstyles %}

{% scripts %}
    {{ vite_scripts(['resources/entrypoint.ts']) }}
{% endscripts %}
```

**3. PHP (recommended)** — components, controllers, FormWidgets:

```php
$this->addJs('vite:resources/modules/checkout/entrypoint.ts');
```

Entry paths match the **source paths** under `resources/` in your repository (as registered in the manifest), not hashed output filenames.

## Registering assets with `addJs` and `addCss`

The **`vite:`** prefix is the recommended way to load Vite bundles from PHP. Use it anywhere October's `addJs()` and `addCss()` are available — no trait, no `vite_assets()` loop, and no knowledge of manifest hashes or dev-server URLs.

```php
$this->addJs('vite:resources/modules/admin/entrypoint.ts');
$this->addCss('vite:resources/scss/admin.scss');
```

Works on the **frontend** (`{% scripts %}` / `{% styles %}`) and in the **backend** layout `<head>`.

### Where agencies use this

| Context | Typical location | Example |
|---|---|---|
| CMS component | `onRun()` | `$this->addJs('vite:resources/modules/shop/entrypoint.ts');` |
| Backend controller | `__construct()` or action | `$this->addJs('vite:resources/modules/reports/entrypoint.ts');` |
| FormWidget | `loadAssets()` | `$this->addJs('vite:resources/formwidgets/color-picker/entrypoint.ts');` |
| Global event listener | `cms.page.init`, etc. | `$controller->addJs('vite:resources/modules/analytics/entrypoint.ts');` |

Scope is inferred from the calling class namespace (plugin code from `Vendor\Plugin\...`). FormWidgets and backend widgets resolve to their owning plugin automatically.

### Explicit scope in the token

Use when autodetection is ambiguous — shared partials, loading a theme bundle from a plugin, or cross-plugin references:

| Token | Resolves to |
|---|---|
| `vite:resources/entrypoint.ts` | Autodetect (plugin namespace or theme heuristics) |
| `vite:theme:resources/entrypoint.ts` | Active theme |
| `vite:plugin:Vendor.Plugin:resources/modules/foo/entrypoint.ts` | Named plugin |

```php
$this->addJs('vite:theme:resources/entrypoint.ts');
$this->addJs('vite:plugin:Acme.Blog:resources/modules/blog/entrypoint.ts');
```

### CSS behaviour

- **Development:** CSS imported by a JS entry loads through the Vite module graph. Separate `addCss('vite:…scss')` entries are supported for dedicated stylesheet entrypoints.
- **Production:** CSS arrays on the manifest entry (and shared chunks via `imports`) are emitted as `<link>` tags automatically.

### Alternatives

| Approach | When to use |
|---|---|
| `vite:` + `addJs` / `addCss` | **Default** — least boilerplate |
| `HasViteAssets` → `addViteAssets()` | Team convention for a named enqueue method |
| `vite_assets()` + manual loop | Per-URL control over `addJs` attributes |
| Twig `vite_scripts()` / `vite_styles()` | Theme layouts |

Under the hood, `vite:` tokens are intercepted via `system.assets.beforeAddAsset`, then resolved on `cms.assets.render` (frontend) or `backend.layout.extendHead` (backend).

## Twig and PHP helpers

### Twig functions

| Function | Output |
|---|---|
| `vite_scripts($entries, $scope, $plugin)` | `<script type="module">` tags (`@vite/client` in dev) |
| `vite_styles($entries, $scope, $plugin)` | `<link rel="stylesheet">` tags |
| `vite($entries, $scope, $plugin, $type)` | Both JS and CSS (or filter with `$type`) |
| `vite_asset($path, $scope, $plugin)` | Public URL for a static asset (image, font) |

**Theme — site bundle:**

```twig
{{ vite_scripts(['resources/entrypoint.ts']) }}
{{ vite_styles(['resources/entrypoint.ts']) }}
<img src="{{ vite_asset('resources/images/logo.svg') }}" alt="">
```

**Plugin — module entrypoint:**

```twig
{{ vite_scripts(['resources/modules/blog/entrypoint.ts'], 'plugin', 'Acme.Blog') }}
```

### Global PHP helpers

```php
vite_scripts(['resources/entrypoint.ts']);
vite_styles(['resources/entrypoint.ts']);
vite_asset('resources/images/hero.webp');

$assets = vite_assets(['resources/modules/admin/entrypoint.ts'], 'plugin', 'Vendor.Plugin');
foreach ($assets['js'] as $js) {
    $controller->addJs($js['url'], array_filter(['type' => $js['type'] ?? null]));
}
foreach ($assets['css'] as $css) {
    $controller->addCss($css['url']);
}
```

### `HasViteAssets` trait (optional)

```php
use Vernok\Vite\Classes\Traits\HasViteAssets;

class BlogList extends ComponentBase
{
    use HasViteAssets;

    public function onRun(): void
    {
        $this->addViteAssets(['resources/modules/blog/entrypoint.ts']);
        // Or explicit scope:
        // $this->addViteAssets([...], 'plugin', 'Acme.Blog');
    }
}
```

### Event listeners

Prefer `vite:` on the controller when possible:

```php
Event::listen('cms.page.init', function ($controller) {
    $controller->addJs('vite:resources/modules/analytics/entrypoint.ts');
});
```

Or wrap the host with `Vernok\Vite\Classes\Assets\ViteAssetHostAdapter` and call `enqueue()` for scope inference from the host class.

## Scope resolution

`ViteScopeResolver` determines `theme` vs `plugin` and the `Vendor.Plugin` code:

1. Explicit `$scope` and/or plugin code from the caller (Twig, helper, or `vite:plugin:…` token)
2. Namespace of the calling class (components, controllers, widgets)
3. Theme heuristics (`resources/entrypoint.ts`, `resources/images/*`, `resources/fonts/*`)
4. Call stack path (Twig partials under `themes/` or `plugins/`)
5. Default: **theme**

When in doubt — especially in shared Twig partials — pass scope and plugin code explicitly.

## Development vs production

| Mode | Signal | Asset source |
|---|---|---|
| **Dev** | Valid `.vite-dev.json` with `origin` in theme/plugin root | Vite dev server (`origin` + `/resources/...`) |
| **Build** | No valid dev file, or `VITE_DEV_SERVER_ENABLED=false` | `assets/.vite/manifest.json` |

Example `.vite-dev.json` (written while `npm run dev` runs):

```json
{
  "origin": "http://localhost:5173",
  "pid": 12345,
  "mode": "dev"
}
```

Invalid or incomplete JSON (missing `origin`) falls back to build mode.

### Manifest locations

| Owner | Manifest | Public URL base |
|---|---|---|
| Theme `my-theme` | `themes/my-theme/assets/.vite/manifest.json` | `/themes/my-theme/assets/` |
| Plugin `Vendor.Plugin` | `plugins/vendor/plugin/assets/.vite/manifest.json` | `/plugins/vendor/plugin/assets/` |

Manifest keys are source paths (e.g. `resources/entrypoint.ts`). Values include `file`, optional `css`, and `imports` for shared chunks.

Add `.vite-dev.json` to `.gitignore` in each theme/plugin repo. Whether you commit built `assets/` depends on your deployment workflow.

## Docker and multi-owner setups

**Multiple Vite dev servers:** each theme or plugin with its own `vite.config.ts` writes its own `.vite-dev.json` and `origin`. A theme on port 5173 and a plugin on 5174 work simultaneously — pass the correct scope when registering entries.

**PHP in Docker, Vite on the host** — configure in the Vite project (not in Vernok.Vite):

```ts
import { defineThemeConfig, octoberTheme } from '@vernok/vite-plugin-october';

export default defineThemeConfig({
  plugins: [
    octoberTheme({ hostUrl: 'http://host.docker.internal:5173' }),
  ],
});
```

The npm package writes that origin to `.vite-dev.json`; **Vernok.Vite** reads it automatically.

## Testing

From the October CMS project root:

```bash
php artisan plugin:test Vernok.Vite
```

From this plugin directory:

```bash
composer test
composer pint:test
```

## Troubleshooting

| Symptom | Likely cause | Action |
|---|---|---|
| `<!-- Vite: entry not found in manifest -->` | Path mismatch or missing build | Run `npm run build`; verify manifest key equals the path passed to Twig/`vite:` |
| Dev assets not loading | Missing or invalid `.vite-dev.json` | Run `npm run dev` in the correct directory; confirm `origin` is set |
| Production works, dev does not | `VITE_DEV_SERVER_ENABLED=false` | Remove or set to `null` in `.env` |
| Wrong plugin manifest | Autodetection chose theme | Use `vite:plugin:Vendor.Plugin:…` or explicit Twig scope |
| `vite:` appears literally in HTML | Plugin not installed or cache | `php artisan plugin:install Vernok.Vite`; `php artisan cache:clear` |
| Stale dev mode after crash | Leftover `.vite-dev.json` | Delete the file manually |

Enable debug logging:

```env
APP_DEBUG=true
VITE_RESOLVER_DEBUG=true
```

Inspect `storage/logs` for `[Vernok.Vite]` entries.

## Known limitations

- Manifest path is fixed to `assets/.vite/manifest.json` per theme or plugin.
- A stale `.vite-dev.json` after a crashed dev server keeps dev mode active until removed.
- `vite_asset()` resolves only paths known to the manifest (or dev URLs for tracked source paths).
- The `vite:` protocol hooks October's `AssetMaker` pipeline only — assets registered outside `addJs` / `addCss` are not intercepted.

## License

MIT — see [LICENSE](LICENSE).

## Related packages

| Package | Role |
|---|---|
| **[`@vernok/vite-plugin-october`](https://www.npmjs.com/package/@vernok/vite-plugin-october)** | Vite config, entry discovery, build output, `.vite-dev.json` lifecycle — **required in every theme/plugin repo that uses Vite** |
| **Vernok.Vite** (this plugin) | PHP resolver — Twig, helpers, traits, `vite:` protocol — **install once in the October CMS application** |

Typical workflow: [`@vernok/vite-plugin-october`](https://www.npmjs.com/package/@vernok/vite-plugin-october) in each asset repository + **Vernok.Vite** in the October project. Read both READMEs — Node setup on npm, PHP integration here.
