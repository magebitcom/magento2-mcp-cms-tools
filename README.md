# Magento2 MCP - CMS Tools

This is a sub-module for the [Magento2 MCP module](https://github.com/magebitcom/magento2-mcp-module)

----

CMS-domain MCP tools for `Magebit_Mcp`. Reads and writes against CMS pages
and blocks.

Each tool is a thin wrapper over a Magento service contract
(`PageRepositoryInterface`, `BlockRepositoryInterface`,
`GetPageByIdentifierInterface`, `GetBlockByIdentifierInterface`) and composes
its read response from field resolvers that 3rd-party modules can extend.

## Install

```bash
composer require magebitcom/magento2-mcp-cms-tools
bin/magento module:enable Magebit_McpCmsTools
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Ships with `Magebit_Mcp` as its only Magebit dependency. If you only want
the base MCP transport (no CMS tools), install `Magebit_Mcp` alone; this
module is designed to be optional.

## Tool catalog

### Read tools

| Tool | What it does |
|---|---|
| `cms.page.list` | Paginated CMS-page search; filter by identifier (exact / glob / array), title substring, is_active, store_id, website_id (expanded to store-view ids), creation_time range, update_time range. |
| `cms.page.get` | Single CMS page by numeric id or identifier (+ optional `store_id` for identifier lookup). |
| `cms.block.list` | Paginated CMS-block search with the same filter vocabulary as pages. |
| `cms.block.get` | Single CMS block by numeric id or identifier. |

### Write tools

All writes require the global `magebit_mcp/general/allow_writes` flag **and**
the token's own `allow_writes` flag to be `1`. Every write sets
`requires_confirmation` so MCP clients (Claude Desktop, etc.) prompt before
firing.

| Tool | Confirm? | Delegates to | Underlying ACL |
|---|---|---|---|
| `cms.page.create` | yes | `PageRepositoryInterface::save()` | `Magento_Cms::save` |
| `cms.page.update` | yes | `PageRepositoryInterface::save()` (PATCH) | `Magento_Cms::save` |
| `cms.page.delete` | yes | `PageRepositoryInterface::delete()` | `Magento_Cms::page_delete` |
| `cms.block.create` | yes | `BlockRepositoryInterface::save()` | `Magento_Cms::block` |
| `cms.block.update` | yes | `BlockRepositoryInterface::save()` (PATCH) | `Magento_Cms::block` |
| `cms.block.delete` | yes | `BlockRepositoryInterface::delete()` | `Magento_Cms::block` |

Every write tool also implements `Magebit\Mcp\Api\UnderlyingAclAwareInterface`
so the handler blocks calls from admins who wouldn't be allowed to perform
the same action in the admin UI.

## Identifier lookups and store scope

`cms.page.get`, `cms.page.update`, `cms.page.delete` (and the block siblings)
accept either `id` (numeric primary key) **or** `identifier` (the admin URL
key). Identifier lookups require a store scope:

- Omit `store_id` → defaults to `0` ("All Store Views"), which is the scope
  CMS pages and blocks created from the admin UI default to. This is what
  most identifier-only lookups want.
- Pass `store_id: <N>` → look the entity up under store view `N`.

Pages / blocks assigned to a specific store view are invisible from other
stores by design.

## Extending

See `docs/EXTENDING.md` for:
- adding a new field to any tool response via `PageFieldResolverInterface`
  / `BlockFieldResolverInterface`;
- adding a new filter to `cms.page.list` / `cms.block.list` via
  `PageFilterTranslatorInterface` / `BlockFilterTranslatorInterface`;
- the ACL layering rules for custom write tools.

## License

Released under the [MIT License](LICENSE).

---

![Magebit](https://github.com/user-attachments/assets/cdc904ce-e839-40a0-a86f-792f7ab7961f)

Magebit - Full-service e-commerce agency

[magebit.com](https://magebit.com)
