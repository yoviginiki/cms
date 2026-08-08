# Projection Vocabulary

The semantic vocabulary used by the Content Projection Layer. Rule: we use
schema.org terms wherever schema.org has coverage, and our own `stillopress:*`
namespace **only** where it does not. We do not build a parallel universe of
terms.

## Canonical addressing (Gate 0 decision)

```
block:  {block_uuid}
field:  {block_uuid}#{path}
```

- `block_uuid` is `blocks.id` (the row is UUID-keyed; there is no separate uuid
  column).
- `path` is a dot-path **relative to `blocks.data`** — e.g. `content`,
  `items.0.title`. **No `data.` prefix.** This is byte-identical to the
  editor's existing `data-sp-field` attribute (`app/Support/Rendering/sp_helpers.php`)
  and the inline-edit API's `field` key, so there is exactly one addressing
  scheme across editor, projection, AI change proposals and RAG provenance
  (Prime Directive 1). No translation layer.

## schema.org types we emit

| Block / entity      | schema.org @type | Why                                             |
|---------------------|------------------|-------------------------------------------------|
| image               | `ImageObject`    | Standard media type; `contentUrl`, `caption`, `name`. |
| heading             | *(none)*         | A heading has no standalone type; it structures the outline. |
| rich-text           | *(none)*         | Body content; contributes text, not a typed node. |

Page/post-level types (`WebPage`, `Article`, `BlogPosting`, `WebSite`,
`BreadcrumbList`, `FAQPage`, `LocalBusiness`) are already produced by the
existing `StructuredDataService` and remain owned by it (Gate 0 decision:
projection produces sidecars, head JSON-LD stays with StructuredDataService).

## Field participation flags

Each declared field opts into one or more projection views:

| Flag        | Feeds view          | Meaning                                        |
|-------------|---------------------|------------------------------------------------|
| `schema`    | schema.org / JSON-LD | Field value becomes a property of the schema node. |
| `rag`       | RAG segments        | Field text is indexed for retrieval.           |
| `inventory` | asset / link inventory | Field is an asset ref, url, or rich body to scan. |
| `segment`   | RAG segments        | Field is itself a segment boundary.            |

## `stillopress:*` namespace

Reserved for structural facts schema.org does not model. Used in the internal
projection, never guessed:

| Term                        | Meaning                                             |
|-----------------------------|-----------------------------------------------------|
| `stillopress:blockAddress`  | Canonical `{uuid}` or `{uuid}#{path}` address.      |
| `stillopress:blockType`     | The block's registry `type()` (e.g. `heading`).     |
| `stillopress:path`          | Positional path in the tree (e.g. `0.2.1`).         |
| `stillopress:depth`         | Nesting depth of the block.                         |
| `stillopress:pageVersionId` | Source `page_versions.id` for provenance.           |
| `stillopress:contentHash`   | Content hash of the projected source, for reindex.  |

## Builder representation notes (within-spec)

- **`schema_org` is a `@graph` of block-level nodes.** Because Gate 0 keeps the
  page-level `Article`/`WebPage` node with `StructuredDataService`, the
  projection's own `schema_org` aggregates the nodes declared by block
  descriptors (e.g. `ImageObject`) under the schema.org-standard `@graph`
  container. Empty page → `@graph: []`. This is the faithful rendering of the
  declared descriptors into the named `schema_org` field, not a separate scheme.
- **`inventory.outbound_links[].internal`** in the pure builder is computed
  without a site host (none is available to a pure function): a URL is
  `internal` when it is relative (no scheme, not protocol-relative, not
  mailto/tel). The Phase 4 integration layer may refine this against the real
  site host.

## FieldType catalogue

`Text`, `RichText`, `AssetRef`, `Url`, `Number`, `Date`, `Boolean`. RichText is
HTML-parsed by the builder for outbound links, inline assets and word count;
AssetRef resolves against the asset inventory; Url becomes an outbound link.
