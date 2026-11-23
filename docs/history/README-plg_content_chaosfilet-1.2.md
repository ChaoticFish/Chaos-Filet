# Chaos Filet (plg_content_chaosfilet)

Pulls specific fields from one article into another article or a Custom HTML
module, using a simple `{article ID:field}` tag. No bundled libraries beyond
what Joomla itself ships with.

## Requirements

- Joomla 5 or 6
- Works in: article body text, and Custom HTML modules (see below)

## Install

1. Extensions → Manage → Install → upload `plg_content_chaosfilet.zip`
2. Extensions → Plugins → find **Content - Chaos Filet** → enable it (the plugin's admin description also shows the syntax/field summary below, for quick reference)

## Basic usage

In any article body, add a tag like:

```
{article 12:title}
```

This pulls the `title` field from article ID `12` and inserts it at that
spot.

### No field list? Defaults to `body`

```
{article 12}
```

is the same as `{article 12:body}`.

### Multiple fields in one tag

```
{article 12:title,image,date}
```

### Field shortcuts

```
{article 12:common}
```

Renders `title`, `image`, `body`, `date`, `author`, and `link`, in that
order — a curated set for a typical teaser/card layout.

```
{article 12:all}
```

Renders **every** built-in field: `title`, `intro`, `fulltext`, `body`,
`excerpt`, `image`, `fulltext_image`, `link`, `date`, `modified`, `author`,
`category`, `tags`, `hits`. (Note: `body` already contains `intro` +
`fulltext`, so `all` does repeat that text — it's meant as a literal
"everything available" dump, not a deduplicated summary. Custom fields
aren't included either way, since those need an explicit `field:alias`.)

Since `all` is meant as a debugging tool, each field's output is preceded
by a small label naming the field — shown even when that field renders
blank, so you can see at a glance what's populated and what isn't:

```
title:
[title output]
intro:
[intro output]
image:
[nothing — no intro image set]
...
```

### Custom fields

```
{article 12:field:subtitle}
```

Pulls the custom field whose **alias** is `subtitle` (com_fields "Field
Name"), rendered the way that field type normally displays.

## Using it in a module

To use tags inside a **Custom HTML** module, open that module and set:

**Advanced → Prepare Content = Yes**

Without that setting, Joomla never runs the module's content through the
plugin, so the tag will just print as literal text.

## What happens with unpublished or restricted articles

If the source article (the ID in the tag) is unpublished, outside its
publish-up/publish-down window, or restricted to an access level the
visitor doesn't have:

- **Public visitors:** see nothing — the tag just produces no output.
- **Users who can edit that article:** see a small badge instead, telling
  them *why* it didn't render (e.g. "source article is unpublished" or
  "source article is access-restricted"). This is meant as an in-place
  debugging aid for editors/admins, not something the public ever sees.

## Plugin settings

The plugin itself has one configurable option, on the plugin's edit page:

- **Excerpt Limit** — how much of the intro text the `excerpt` field keeps (default `40`)
- **Excerpt Limit Unit** — `Words` or `Characters` (default `Words`)

## Tag syntax reference

```
{article ID:field1,field2,...}
```

- `ID` — the numeric article ID
- Field list is optional — omit it (and the colon) to default to `body`
- Otherwise, one or more comma-separated field names, no spaces required

### Supported fields

| Field           | Output                                                        |
|-----------------|-----------------------------------------------------------------|
| `title`         | The article's title, HTML-escaped                               |
| `intro`         | Intro text (raw HTML, as written in the editor)                 |
| `fulltext`      | Fulltext/"read more" text (raw HTML)                             |
| `body`          | `intro` + `fulltext` combined — **the default if no field is given** |
| `excerpt`       | Intro text, stripped of HTML and truncated to the configured Excerpt Limit (words or characters, set on the plugin's edit page), with `…` appended if cut off |
| `image`         | The intro image as an `<img>` tag                                |
| `fulltext_image`| The "read more" image (image_fulltext) as an `<img>` tag          |
| `link`          | The article's permalink, wrapped around its title                |
| `date`          | The created date, formatted                                      |
| `modified`      | The last-modified date, formatted (blank if never modified)       |
| `author`        | Author name (uses the "Author Alias" if one is set)              |
| `category`      | The article's category title                                     |
| `tags`          | The article's tags, as a comma-separated list                     |
| `hits`          | The article's view count                                          |
| `common`        | `title` + `image` + `body` + `date` + `author` + `link`, in that order |
| `all`           | Every built-in field above, in the order listed in the table       |
| `field:ALIAS`   | A custom field's value, by its alias (e.g. `field:subtitle`)     |

`all` is a literal dump of everything — it includes `intro` and `fulltext`
separately *and* `body` (which is those two combined), so expect some
repeated text if you use it as-is. `common` is the curated, non-redundant
set for everyday use.

### Markup

Every field is rendered as a `<div>` (image and link are the natural
exceptions — `<img>` and `<a>` respectively). Each `<div>` carries **two**
CSS classes: a namespaced one (`cf-field--title`) and a plain one matching
the field name itself (`title`, `image`, `body`, or the custom field's
alias for `field:` tags). Style either one:

```css
.cf-field--image img,
img.image { max-width: 100%; }

.author { font-style: italic; }
```

### A couple of notes

- `intro`, `fulltext`, and `body` are output as raw HTML exactly as stored
  — they are **not** re-processed by other content plugins, and a tag
  inside pulled-in text will not itself be expanded (this is intentional,
  to avoid runaway recursion).
- Custom field values are rendered the same way that field type normally
  displays them on the front end. `field:ALIAS` is case-sensitive (aliases
  are matched exactly); every other field keyword is case-insensitive.
- The plugin skips Smart Search indexing entirely (`com_finder.indexer`
  context), so restricted/unpublished content can't leak into the search
  index through a tag.
- Article and tag lookups are cached for the life of the page request, so
  using the same `{article 12:...}` tag more than once on a page (e.g.
  once in the article, once in a module) only queries the database once.
