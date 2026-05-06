# Extending `Magebit_McpCmsTools`

Every read tool in this module composes its response from a DI-injected
array of **field resolvers**. Each resolver owns one named slice of the
output; 3rd parties add, replace, or remove slices from their own
`etc/di.xml` without touching this module.

The same pattern applies symmetrically to pages and blocks — each with its
own entity-scoped interface.

## Add a new field to `cms.page.get` / `cms.page.list`

### 1. Implement `PageFieldResolverInterface`

```php
<?php
declare(strict_types=1);

namespace Vendor\Seo\Mcp\Resolver;

use Magebit\McpCmsTools\Api\PageFieldResolverInterface;
use Magento\Cms\Api\Data\PageInterface;

final class CanonicalUrlResolver implements PageFieldResolverInterface
{
    public function getKey(): string
    {
        return 'canonical_url';
    }

    public function getSortOrder(): int
    {
        // 100 = default; 50 renders earlier than built-ins, 150 later.
        return 45;
    }

    public function resolve(PageInterface $page, array $args): array
    {
        return [
            'canonical_url' => (string) $page->getData('canonical_url'),
        ];
    }
}
```

### 2. Register the resolver in `etc/di.xml`

```xml
<type name="Magebit\McpCmsTools\Tool\Cms\Page\PageGet">
    <arguments>
        <argument name="fieldResolvers" xsi:type="array">
            <item name="canonical_url" xsi:type="object">
                Vendor\Seo\Mcp\Resolver\CanonicalUrlResolver
            </item>
        </argument>
    </arguments>
</type>

<!-- Optional: also surface it on list rows. -->
<type name="Magebit\McpCmsTools\Tool\Cms\Page\PageList">
    <arguments>
        <argument name="fieldResolvers" xsi:type="array">
            <item name="canonical_url" xsi:type="object">
                Vendor\Seo\Mcp\Resolver\CanonicalUrlResolver
            </item>
        </argument>
    </arguments>
</type>
```

DI-array items are merged across modules, so Magebit-shipped resolvers stay
in place — you only pay for what you add.

### 3. Run `bin/magento setup:upgrade && setup:di:compile`

No other changes needed. The next `cms.page.get` call will include a new
`canonical_url` key.

### Opt-out from a caller

The two arguments every read tool accepts:

- `fields: ["identity", "state"]` — only render those keys.
- `exclude: ["content"]` — everything except those keys.

Useful when an LLM over-fetches or when a specific slice (like `content`)
is expensive.

## Add a new filter to `cms.page.list` / `cms.block.list`

Built-in filters cover every `PageInterface` / `BlockInterface` column. For
custom attributes or cross-table joins, implement the matching filter
translator interface and register it:

```php
<?php
declare(strict_types=1);

namespace Vendor\Seo\Mcp\Filter;

use Magebit\McpCmsTools\Api\PageFilterTranslatorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

final class CanonicalUrlFilter implements PageFilterTranslatorInterface
{
    public function supports(string $key): bool
    {
        return $key === 'canonical_url';
    }

    public function translate(string $key, mixed $value, SearchCriteriaBuilder $builder): void
    {
        $builder->addFilter('canonical_url', (string) $value);
    }
}
```

```xml
<type name="Magebit\McpCmsTools\Model\Search\PageSearchCriteriaBuilder">
    <arguments>
        <argument name="filterTranslators" xsi:type="array">
            <item name="canonical_url" xsi:type="object">
                Vendor\Seo\Mcp\Filter\CanonicalUrlFilter
            </item>
        </argument>
    </arguments>
</type>
```

Unsupported filter keys fail fast with `INVALID_PARAMS` rather than
silently ignoring — your translator must claim the key via `supports()`
before the built-in dispatch falls through. Use
`BlockFilterTranslatorInterface` + `BlockSearchCriteriaBuilder` for the
block side.

## Per-entity resolver interfaces

| Entity | Interface | Built-in tool uses it |
|---|---|---|
| Page | `PageFieldResolverInterface` | `cms.page.get`, `cms.page.list` |
| Block | `BlockFieldResolverInterface` | `cms.block.get`, `cms.block.list` |

Both extend the base module's `Magebit\Mcp\Api\FieldResolverInterface`
marker, so the same `ResolverPipeline` in `Magebit_Mcp` sorts + filters
them for every tool.

## Write-tool ACL layering

Every write tool in this module implements both `ToolInterface` and
`Magebit\Mcp\Api\UnderlyingAclAwareInterface`. The handler enforces two
ACL checks per call:

1. The tool's own MCP-scoped resource — e.g.
   `Magebit_McpCmsTools::tool_cms_page_create`.
2. The underlying Magento resource returned by
   `getUnderlyingAclResource()` — e.g. `Magento_Cms::save`.

Both must pass. This preserves "MCP cannot do what the admin UI cannot" —
an MCP-only role denied `Magento_Cms::save` cannot create or update CMS
pages via MCP, even if you granted the MCP-specific resource by accident.

Core CMS only exposes two admin ACLs relevant to this module:
`Magento_Cms::save` (page save), `Magento_Cms::page_delete` (page delete),
and a single `Magento_Cms::block` parent resource used for every block
write. We reuse those rather than invent new ones.

## Custom field types / computed slices

Resolvers are free to call into any service or repository during
`resolve()` — DI is wired up by the time the tool dispatches, so heavier
slices (e.g. "resolve widget instances referenced from the block content")
can run on demand. Keep them isolated per key so callers can drop them
with `exclude`.
