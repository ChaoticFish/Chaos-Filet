# Chaos Filet (pkg_chaosfilet)

Pull fields from one Joomla article into another article or a Custom HTML module with a short tag, by **Chaotic Fish Design**.

`{article 12:title}` drops in article 12's title; `{article 12|card}` serves a whole Bootstrap card. An editor button builds the tags for you.

- **Version:** 1.4.0
- **Requires:** Joomla 5 and 6, PHP 8.2+
- **Package:** `pkg_chaosfilet`, holding the content plugin `plg_content_chaosfilet` and the editor button `plg_editors-xtd_chaosfilet`
- **Updates:** through Joomla's own updater, from the Chaotic Fish Design update server
- **Dependencies:** none. Nothing is added to your site's pages; the editor button loads one small script in the editor only.
- **Prefix:** `cf-filet` / `cf-field` on the site, `cfp-` in the editor popup
- **License:** GPL-2.0-or-later

---

## Install

1. Install `pkg_chaosfilet-1.4.0.zip` from **System → Install → Extensions**.
   - **New site:** it installs both plugins and switches both on.
   - **Site with the separate plugins** (content 1.2.x, button 1.0.x): install the package over them. Both are updated in place; their settings and their on/off state stay exactly as they were.
2. Put a tag in an article, or use the **Chaos Filet** button under the editor (in TinyMCE it's in the **CMS Content** menu).
3. For Custom HTML modules, set **Advanced → Prepare Content = Yes**.

Uninstall the package to remove both plugins. Joomla won't uninstall one of them on its own while the package is installed.

---

## Tag syntax

```
{article REF:field1,field2|layout}
```

| Part     | Required | What it is |
|----------|----------|------------|
| `REF`    | yes      | Article ID (`12`), alias (`tancho-profile`), or category-alias/article-alias (`residents/tancho-profile`) |
| `fields` | no       | Comma-separated field names. Omit it (and the colon) to get `body` |
| `layout` | no       | Layout name, e.g. `card`. Omit it (and the bar) for the default |

Keywords are case-insensitive, except custom-field aliases after
`field:`/`fieldraw:`, which are matched exactly. Spaces around `:`, `,` and
`|` are fine.

### Examples

```
{article 12}                        body of article 12
{article 12:title,image,date}       three fields, in that order
{article 12:common}                 title, image, body, date, author, link
{article tancho-profile:excerpt}    by alias
{article 12|card}                   Bootstrap card layout
Meet {article 12:link!} today.      inline link inside a sentence
<a href="{article 12:url!}">…</a>   URL inside your own markup
```

### Referring to articles by alias

IDs can differ between a staging site and the live one; aliases usually
don't. Joomla only keeps aliases unique *within a category*, so an alias
can match several articles. When that happens the lowest ID wins, and
editors see a badge saying so. Use `category-alias/article-alias` to pin it
down. On multilingual sites, only articles in the visitor's language or
"All" are considered.

### Raw fields: the `!` suffix

Every field normally comes out in its own `<div>`. That's handy for
styling, but a div can't sit inside a `<p>`, so a plain
`{article 12:title}` in the middle of a sentence splits the paragraph.
End a field with `!` to output it with no wrapper:

```
<p>Our governor, {article 12:title!}, says hi.</p>
```

- `!` applies per field, so `{article 12:title!,date}` mixes them.
- `!` on a shortcut (`common!`) applies to every field it expands to.
- `link`, `readmore`, `image` and `fulltext_image` are already inline
  elements (`<a>`, `<img>`), so `!` makes no difference to them.
- When every field in a tag is raw, the outer wrapper is skipped too.

**Using a tag inside an attribute** (`href="{article 12:url!}"`): this
works reliably in a Custom HTML module or with the editor in code/no-editor
mode. A WYSIWYG editor (TinyMCE) may rewrite or encode URLs it doesn't
recognise; if a tag in an `href` stops working, that's the first thing to
check.

## Fields

| Field            | Output |
|------------------|--------|
| `id`             | Article ID |
| `title`          | Title, HTML-escaped |
| `alias`          | Article alias |
| `url`            | The article's URL (SEF route), as text. Use with `!` inside an `href` |
| `link`           | `<a>` to the article, with its title as the text |
| `readmore`       | "Read more →" `<a>` to the article. Includes the title in visually-hidden text so screen readers hear "Read more: Title" rather than a row of identical links. Change the wording with a language override of `PLG_CONTENT_CHAOSFILET_READMORE` |
| `intro`          | Intro text (raw HTML, as written in the editor) |
| `fulltext`       | Read-more text (raw HTML) |
| `body`           | `intro` + `fulltext`. **The default when no field is given** |
| `excerpt`        | Intro text with HTML stripped, cut to the Excerpt Limit, `…` appended if cut |
| `image`          | Intro image as `<img>` |
| `fulltext_image` | Full-article image as `<img>` |
| `date`           | Created date, in the Date Format setting |
| `modified`       | Last-modified date (blank if never modified) |
| `author`         | Author name, or the Author Alias if one is set |
| `category`       | Category title |
| `tags`           | Published tags the visitor can see, comma-separated |
| `hits`           | View count |
| `field:ALIAS`    | Custom field, rendered the way that field type normally displays |
| `fieldraw:ALIAS` | Custom field's stored value, HTML-escaped (multi-value fields joined with `, `). Useful for putting a URL, colour or number into an attribute |
| `common`         | `title`, `image`, `body`, `date`, `author`, `link` |
| `all`            | Every built-in field above, then every custom field, each preceded by a bold label, even when blank. A debugging tool: it repeats text, since `body` contains `intro` + `fulltext` |

### Images

Images are output with:

- Joomla's `#joomlaImage://…` suffix removed (it's stripped with Joomla's
  own `cleanImageURL`)
- `width` and `height`, when Joomla recorded them (images picked through
  the Media Manager in Joomla 4+), so the page doesn't jump while they load
- `loading="lazy" decoding="async"`, unless you turn off **Lazy-load
  Images**. Turn it off if you pull an image into the top of a page, where
  lazy-loading delays it.

The `<img>` is a normal image tag, so Shrinkray's front-end rewriter should
pick it up like any other image. I haven't tested that combination yet.

## Markup and styling

```html
<div class="cf-filet cf-filet--12">
  <div class="cf-field cf-field--title title">Tancho</div>
  <img class="cf-field cf-field--image image" src="…" alt="…" width="800" height="600" loading="lazy" decoding="async">
  <div class="cf-field cf-field--body body">…</div>
</div>
```

- **Outer wrapper**: `cf-filet cf-filet--ID`, plus `cf-filet--LAYOUT` when
  a layout is named. Turn it off with **Wrap Tag Output**.
- **Each field** carries a namespaced class (`cf-field--title`) and a plain
  one matching the field name (`title`, or the custom field's alias). Style
  either.

```css
.cf-filet{border:1px solid #ddd;padding:1rem;}
.cf-field--image{height:auto;max-width:100%;}
.cf-filet .author{font-style:italic;}
```

## Layouts

`{article 12|card}` renders through `tmpl/card.php` instead of the
default. Two ship with the plugin:

- **default**: the fields in tag order, inside the wrapper.
- **card**: a Bootstrap 5 card (image, linked title, date · author,
  excerpt, read more). It builds itself from the article, so the field list
  is ignored: `{article 12|card}`.

### Overriding or adding layouts

Copy a layout to your site template and edit it there. The plugin update
won't overwrite it:

```
templates/YOUR_TEMPLATE/html/plg_content_chaosfilet/default.php
templates/YOUR_TEMPLATE/html/plg_content_chaosfilet/card.php
templates/YOUR_TEMPLATE/html/plg_content_chaosfilet/teaser.php   ← new, used as |teaser
```

Layout names may use `a–z`, `0–9`, `-` and `_`. If a layout isn't found,
the default is used and editors see a badge.

Variables available in a layout:

| Variable        | Contents |
|-----------------|----------|
| `$fields`       | Rendered fields in tag order: each `['name' => …, 'raw' => bool, 'html' => …]` |
| `$wrap`         | Whether the default layout would add the wrapper |
| `$wrapperClass` | e.g. `cf-filet cf-filet--12 cf-filet--teaser` |
| `$layout`       | The layout name |
| `$article`      | Plain values: `id`, `title`, `alias`, `catid`, `category`, `language`, `url`, `author`, `created`, `modified`, `date` (formatted), `hits` |

Any layout other than the plugin's own `default.php` also gets
`$article->intro`, `fulltext`, `body`, `excerpt`, `image` and
`fulltext_image` (each `null` or `{src, alt, width, height}`), `tags`
(array of titles), and `fields` (custom fields keyed by name, each with
`->label`, `->value` rendered and `->rawvalue`). These cost extra queries,
so the plain default skips them. Values in `$article` are not
HTML-escaped (except `date`), so escape them in your layout, as `card.php`
does.

## Showing tag syntax on a page

- Tags inside `<code>` or `<pre>` are never expanded. That covers
  documentation pages and code samples.
- Anywhere else, a backslash prints the tag literally:
  `\{article 12:title}` → `{article 12:title}`.

## Using it in a module

To use tags inside a **Custom HTML** module, open that module and set:

**Advanced → Prepare Content = Yes**

Without it, Joomla never runs the module's content through the plugin, and
the tag prints as literal text.

## Unpublished, restricted and missing articles

A tag shows nothing to the public when the source article:

- doesn't exist (wrong ID or alias)
- isn't published (unpublished, archived or trashed), or is outside its
  publish-up/publish-down window
- is in an unpublished category (only the article's own category is
  checked, not the categories above it)
- has an access level the visitor doesn't have, or its category does
- is the article the tag sits in, or would loop back to an article already
  being shown

Users who can edit articles see a small badge instead, saying why. They
also see badges for unknown field names, ambiguous aliases, and missing
layouts. The public never sees these badges. (They use Bootstrap `badge`
classes and `cf-note cf-note--TYPE`.)

## Plugin settings

| Setting | Default | What it does |
|---------|---------|--------------|
| Wrap Tag Output | Yes | Adds the `cf-filet` wrapper div around each tag's output |
| Excerpt Limit | 40 | Length of `excerpt` |
| Excerpt Limit Unit | Words | Words or Characters |
| Date Format | `DATE_FORMAT_LC3` | For `date` and `modified`. A Joomla language key (follows the site language) or a PHP date format such as `j F Y` |
| Card Heading Level | H3 | The heading level of the title in the `\|card` layout. Pick the one that fits under the heading the cards sit beneath; it looks the same at every level |
| Lazy-load Images | Yes | Adds `loading="lazy" decoding="async"` to images |
| Count Embeds as Hits | No | Increments the source article's hits, once per article per page load. Off because a tag in a sitewide module would otherwise inflate hits on every page view |
| Run Content Plugins on Pulled Text | No | See below |

### Run Content Plugins on Pulled Text

By default, `intro`, `fulltext` and `body` are output exactly as stored.
Other content plugins (email cloaking, `{loadmodule}`, and so on) only
reach that text if they happen to run *after* Chaos Filet in the plugin
order, and tags inside pulled-in text stay as literal text.

Turn this on and pulled text goes through all content plugins before it's
inserted, as if it were the article being viewed. Tags inside it expand
too, up to 3 levels deep, and a tag that would loop back to an article
already being shown is stopped.

Caveats: a plugin that runs after Chaos Filet will see that text a second
time. Most plugins are fine with that, but I haven't tested every core
plugin against it, which is why it's off by default. Turn it on, check a
page or two, and turn it off if anything doubles up.

## Notes

- The plugin skips Smart Search indexing entirely (`com_finder.indexer`),
  so restricted or unpublished content can't leak into the search index
  through a tag.
- Article, alias, tag and custom-field lookups are cached for the page
  request, so the same article used several times on a page is only
  queried once.
- Tags only run on the front end.

---

## Editor button

**Chaos Filet – Insert Tag** (`plg_editors-xtd_chaosfilet`) adds a button that builds tags for you. It shows for users who can create or edit articles. The tags it writes need the content plugin enabled to render; the dialog warns you when it isn't.

### Using the dialog

The popup follows the shared CFD editor style: Joomla's own controls, the admin's light or dark theme, a scrolling body with folding groups, and a pinned footer. A teal number on a group says how much you've picked in it.

1. **Article.** The list starts with the 25 most recently modified articles. Type to search titles and aliases; a number also matches that exact ID. Unpublished and archived articles are listed and labelled, since you might be preparing them. The picked article's title shows in the group's header.
2. **Fields.** Tick them in the order you want them output; ticking none gives the article body. `common` and `all` are the plugin's shortcuts.
3. **Custom fields** (only when the site has article custom fields). Each has a menu: **Rendered** (`field:`) or **Stored value** (`fieldraw:`).
4. **Options.**
   - **Refer to it by** ID (default) or Alias. Aliases survive moving content from a staging site to the live one. If the alias isn't unique, the button writes `category-alias/article-alias` for you.
   - **Layout** lists the content plugin's layouts plus any overrides in your default site template.
   - **Inline (no wrapper divs)** adds `!` to every field, for a tag that sits inside a sentence or an attribute.
5. **Your tag** shows exactly what will be inserted. **Copy** puts it on the clipboard, for a Custom HTML module or anywhere without editor buttons. **Insert** drops it where your cursor was.

**Reset** clears every choice. **×**, **Cancel** or Escape closes the popup without inserting anything. If the editor refuses the insert (it can happen if focus moved while the popup was open), the tag is copied to your clipboard and a message says so.

### How the search works

The dialog calls a small JSON endpoint on this plugin through Joomla's
`com_ajax`:

```
index.php?option=com_ajax&group=editors-xtd&plugin=chaosfiletSearch&format=json&q=…
```

- It uses the logged-in session, so there's no API token to set up.
- It requires Joomla's CSRF token and create or edit permission on
  articles; anyone else gets a 403.
- It returns at most 25 articles: ID, title, alias, state, language,
  category, and whether the alias is unique.
- It works from the administrator and from front-end editing.

If the endpoint fails, the dialog says so; you can still type a tag by
hand.

---

## Building

```
python3 build.py            # checks, minifies, builds dist/pkg_chaosfilet-<version>.zip and the source zip
python3 build.py --no-min   # skip minifying
```

- **Version check:** the version in `pkg_chaosfilet.xml` must match both plugin manifests, the `/*!` header of `insert-tag.js`, the asset versions in the button's PHP, the `@version` line of every PHP file, and a `### <version>` entry in this changelog.
- **CSS check:** the popup's CSS (the `CSS_TEXT` block in `insert-tag.js`) keeps properties in alphabetical order in every rule.
- **Minify:** `insert-tag.js` → `insert-tag.min.js` (Node's `npx` fetches terser). The readable file ships alongside; Joomla loads the `.min` one.
- **Update server:** if the `cfd-updates` folder is found (`$CFD_UPDATES`, else `../cfd-updates`), `cfd_update.release('pkg_chaosfilet', <zip>)` writes `pkg_chaosfilet.xml` and `cfd.xml`. If the registry entry isn't confirmed yet, the build still finishes and says why the XML was skipped.

### Files

```
chaosfilet/
├─ pkg_chaosfilet.xml        Package manifest (update server tag lives here only)
├─ script.php                PHP/Joomla guard, enable-on-first-install, leftover clean-up
├─ language/en-GB/pkg_chaosfilet.sys.ini
├─ build.py
├─ plg_content_chaosfilet/   The content plugin (src, services, tmpl, language)
└─ plg_editors-xtd_chaosfilet/  The editor button (src, services, media, language)
```

---

## Test checklist (1.4.0)

1. **Update.** Install `pkg_chaosfilet-1.4.0.zip` over 1.3.0 (or let Joomla's Update screen offer it). Both plugins show 1.4.0; on/off state and settings unchanged. `media/plg_editors_xtd_chaosfilet/css/` is gone.
2. **Popup, light theme.** Open an article, click **Chaos Filet** (TinyMCE: **CMS Content** menu).
   - Header with the title and **×**; groups Article, Fields, (Custom fields), Options; **Your tag** strip; footer **Reset** on the left, **Cancel** and **Insert** on the right.
   - Article titles, field names and every label are readable.
3. **Popup, dark theme.** Switch the administrator to dark (user menu → Edit Account → Accessibility Settings → Dark, or follow the OS). Same check as 2; this is the screenshot that was broken.
4. **Counts and tag.** Pick an article (its title appears in the Article header), tick `title` then `date` (Fields shows **2**, tag ends `:title,date`), set Alias and Inline (Options shows **2** and both labels get a teal dot).
5. **Copy.** Click **Copy**: it says **Copied** for a moment; paste into a text field to check. **Insert** puts the same tag in the editor.
6. **Reset** clears everything; Insert and Copy go grey again.
7. **Close** with ×, Cancel and Escape: nothing is inserted.
8. **Phone width** (DevTools, under 560px): field grids drop to one column; Reset sits on its own row above Cancel and Insert.
9. **Keyboard only:** Tab through search, results (arrow keys move between articles), checkboxes, selects, Copy, Reset, Cancel, Insert.
10. **Card heading.** Set **Card Heading Level** to H2 in the content plugin; a `{article 12|card}` title is now an `<h2>` and looks the same.
11. **Debug dump.** `{article 13:all}` shows each label in bold.
12. **Search failure** (optional): with the browser offline, typing in search shows the red "Search failed" line, not an alert.

---

## Test checklist (1.3.0)

1. **Upgrade in place.** On a site with content 1.2.0 and button 1.0.0, note each plugin's settings and on/off state, then switch the button **off**. Install the package.
   - Both plugins show 1.3.0 under **System → Manage → Extensions**, with **Package: Chaos Filet**.
   - The button is still **off**; the content plugin is still on. Settings are unchanged.
   - The message says the existing plugins were updated in place.
2. **No duplicates.** Extensions → Manage shows one content plugin and one editors-xtd plugin named Chaos Filet, plus the package.
3. **Update site.** **System → Update Sites** shows one "Chaotic Fish Design" entry, not a second one for Chaos Filet.
4. **Leftovers gone.** `administrator/language/en-GB/en-GB.plg_content_chaosfilet.ini` (and `.sys.ini`) and any `README*` files in `plugins/content/chaosfilet/` and `plugins/editors-xtd/chaosfilet/` are deleted.
5. **Still works.** The landing page renders as before; the button opens and inserts a tag. In the page source, the editor loads `insert-tag.min.js?1.3.0`-style URLs.
6. **Fresh install** (a test site with neither plugin): both plugins installed and switched on.
7. **Uninstall** the package on the test site: both plugins go.
8. **Update server** (once the zip is in jDownloads and the XML is uploaded): install 1.3.0, publish a higher test version, and check **System → Update → Extensions** offers it.

---

## Changelog

### 1.4.0 — November 2026

Editor popup restyled to the shared CFD editor style, plus two small plugin improvements.

- **New popup layout:** header with a close button, folding groups (Article, Fields, Custom fields, Options) with teal counts, a tag strip, and a footer with Reset, Cancel and Insert.
- **Dark mode fixed:** the popup takes its colours from the administrator's theme and uses Joomla's own form controls and buttons, so labels and article titles are readable in dark mode.
- **Copy button** beside the tag, for Custom HTML modules and anywhere without editor buttons.
- **Reset** clears every choice in one click.
- Search errors show inline, in the popup.
- The popup's CSS travels inside its script (one file instead of two); the old stylesheet is deleted on update.
- **Card Heading Level** setting for the `|card` layout (H2 to H6, default H3).
- The `all` debug dump shows its labels in bold.

### 1.3.0 — November 2026

Packaged. No changes to what either plugin does.

- **Package `pkg_chaosfilet`** holding both plugins, installed and updated together, with updates from the Chaotic Fish Design update server.
- **Installs over the separate plugins** and updates them in place, keeping their settings and on/off state. On a new site both are switched on.
- **Needs PHP 8.2+** and Joomla 5 or 6; the installer says so instead of failing part-way.
- **Clean-up:** deletes the 1.1.0 language files (old `en-GB.` names) and READMEs that are no longer shipped.
- The editor button loads minified copies of its script and stylesheet, versioned for cache-busting. The readable copies ship alongside.

### 1.2.0 (content plugin) — 9 October 2026

Added
- `!` suffix for raw (wrapper-free) fields, for inline use in sentences and
  attributes.
- Outer wrapper `<div class="cf-filet cf-filet--ID">` around each tag's
  output, with a **Wrap Tag Output** setting (on by default).
- Fields: `url`, `readmore` (screen-reader friendly), `alias`, `id`.
- `fieldraw:ALIAS` for a custom field's stored value.
- `all` now includes custom fields and the new fields.
- Article references by alias and by `category-alias/article-alias`.
- Layouts: `|layout` in the tag, overridable from the site template. Ships
  with `default` and a Bootstrap `card` layout.
- **Date Format**, **Lazy-load Images** and **Run Content Plugins on Pulled
  Text** settings.
- Editor badges for missing articles, unknown fields, ambiguous aliases,
  missing layouts, and loops.
- Tags inside `<code>`/`<pre>` are left alone; `\{article …}` prints
  literally.

Changed
- **Markup:** output is now wrapped in a `cf-filet` div (see above). Turn
  off **Wrap Tag Output** to get exactly the 1.1.0 markup.
- Images: the `#joomlaImage://` suffix is stripped, and `width`, `height`,
  `loading="lazy"` and `decoding="async"` are added.
- The article's category is now checked for published state and access
  level, not just the article.
- `tags` only lists published tags the visitor is allowed to see.
- `excerpt` no longer double-escapes entities such as `&amp;`.
- Language files use Joomla 4+ naming (no `en-GB.` prefix) and install with
  the plugin.
- Documentation is now this README; the separate HTML reference page was
  removed, and the plugin's Documentation tab carries a quick reference.

Fixed
- A tag inside its own article now shows nothing (with an editor badge)
  instead of repeating the article's text.
- The previous README undercounted the plugin's settings.

### 1.0.0 (editor button) — 9 October 2026

- First release.

### 1.1.0 and earlier

No changelog was kept before 1.2.0.
