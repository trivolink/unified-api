<?php

namespace Spaseossr\UnifiedApi;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Inertia\DevTools\DevTools;
use Inertia\ProvidesInertiaProperties;
use Inertia\ResponseFactory;
use InvalidArgumentException;
use UnitEnum;

class UnifiedResponseFactory extends ResponseFactory
{
    /**
     * Create an Inertia response that negotiates its format per client.
     *
     * Mirrors Inertia\ResponseFactory::render(), replacing only the
     * instantiated response class. Keep in sync with upstream when
     * bumping the inertia-laravel constraint.
     *
     * @param  BackedEnum|UnitEnum|string  $component
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>|ProvidesInertiaProperties  $props
     */
    public function render($component, $props = []): UnifiedResponse
    {
        $component = $this->transformComponent($component);

        $component = match (true) {
            $component instanceof BackedEnum => $component->value,
            $component instanceof UnitEnum => $component->name,
            default => $component,
        };

        if (! is_string($component)) {
            throw new InvalidArgumentException('Component argument must be of type string or a string BackedEnum');
        }

        if (config('inertia.pages.ensure_pages_exist', false)) {
            $this->findComponentOrFail($component);
        }

        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        } elseif ($props instanceof ProvidesInertiaProperties) {
            $props = [$props];
        }

        $response = new UnifiedResponse(
            $component,
            $this->sharedProps,
            $props,
            $this->rootView,
            $this->getVersion(),
            $this->encryptHistory ?? config('inertia.history.encrypt', false),
            $this->urlResolver,
        );

        DevTools::recorder()?->pageRendering($component, $response, $this->sharedProps);

        return $response;
    }
}
