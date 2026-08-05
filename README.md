# Elementor Featured Post Types

Two Elementor widgets that rotate featured posts one at a time, with a labelled
countdown tab for each. They share an identical panel and rotator; they differ
only in how the list of posts is decided.

| Widget | How posts are chosen |
|--------|----------------------|
| **Featured Post Types** | One per post type, resolved from a sticky flag set by editors |
| **Featured Posts (Manual)** | Hand-picked, in the order you list them, from any public post type |

Adds sticky support to custom post types so editors can choose what gets
featured.

Built for the Red Egg "Featured Insights" pattern: a large panel that cycles
between a Case Study, a Whitepaper and an Event, with a progress bar under each
label showing which is on screen and how long is left.

## Requirements

- WordPress 6.0+
- Elementor 3.5+
- PHP 7.4+ (developed against 8.3)

No Elementor Pro. No Ultimate Addons. No build step.

## Install

Drop the folder in `wp-content/plugins/` and activate, or upload a zip of it.

If Elementor is missing or too old, the plugin shows an admin notice and does
nothing else — it will not fatal.

## Usage

Both widgets live in the **General** category.

### Featured Post Types

Editors control what appears; the page just says which types to include.

1. Under **Query**, pick the post types. One item is shown per type, in the order
   listed.
2. Feature an item using any of the three controls below.

Only one item per post type can be featured at a time — featuring a new one clears
the previous. If nothing is featured for a type, the widget falls back to that
type's most recent published post, so a tab is never empty. Disable that with
**Fall Back To Latest**.

#### Where editors set the featured flag

| Where | Control |
|-------|---------|
| Single edit screen | **Feature this {Post Type}** checkbox in the Publish box |
| Posts list → Quick Edit | **Featured** checkbox |
| Posts list → Bulk Edit | **Featured** dropdown: no change / featured / not featured |

The list table also gains a **Featured** column (★ or —) so you can see the
current state at a glance, and it is what the Quick Edit script reads to pre-check
the box.

Core only offers its own sticky checkbox for the built-in `post` type — it is
hardcoded in `WP_Posts_List_Table::inline_edit()` — so these are additions rather
than core behaviour being extended.

One wrinkle worth knowing in Bulk Edit: because exclusivity still applies, setting
**Featured** on several items of the *same* post type in one action leaves only the
last one featured. Across different post types they all stick. There is a note to
that effect in the Bulk Edit panel.

Use this when the rotator should stay current without anyone editing the page.

### Featured Posts (Manual)

The page controls what appears.

1. Under **Posts**, add a row per item and pick the post. Any public post type is
   selectable, and you can mix freely — two case studies and an event is fine.
2. Optionally set a **Tab Label**. Left blank it uses the post type's singular
   name, so two picks sharing a type produce two identical tabs; the override is
   how you distinguish them.

Picks that are later trashed, unpublished, or have their post type made
non-public are skipped silently, so a stale pick degrades to a missing tab rather
than a broken panel.

Use this for a curated, deliberately ordered set — a campaign landing page rather
than an evergreen index.

#### A note on the post picker

Elementor's free SELECT2 has no AJAX search, so the picker prefetches its list,
capped at 100 posts per type. Raise or lower it with the
`re_featured_picker_limit` filter. The list is only built in an editing context —
on the frontend, saved picks are read back by ID, so nothing is queried for
options nobody will see.

If that cap ever becomes limiting, Elementor Pro's `query` control does proper
autocomplete and would be a drop-in replacement for that one control, at the cost
of a Pro dependency.

## How featuring works

WordPress keeps sticky posts in a single global `sticky_posts` option and only
renders the "stick this post" checkbox for the built-in `post` type. This plugin
adds an equivalent checkbox to public custom post types and writes through core's
`stick_post()` / `unstick_post()`, so the option stays in a shape `WP_Query`
already understands rather than inventing a parallel meta flag.

Two consequences worth knowing:

- Your CPT IDs share the option with the blog's real sticky posts. That should be
  inert on the blog index, because `WP_Query`'s sticky handling constrains by
  `post_type` — but verify on staging rather than taking it on faith.
- Deactivating this plugin leaves the option populated. Nothing breaks; the
  checkbox just disappears and those IDs sit unused.

## Why one query per post type

A single `WP_Query` with `post_type => [ 'case-study', 'whitepaper', 'event' ]`
returns the N most recent posts across all three, with no guarantee of one of
each. If the three newest posts are all case studies, that is what you get.

`Sticky_Posts::get_slides()` therefore runs one small query per post type and
assembles the slides in control order. This is the whole reason the plugin exists
rather than using Elementor's Posts widget.

## Frontend

Swiper drives the slide transition and autoplay clock. Elementor already ships
and registers a `swiper` handle, so on a normal install no third-party request is
made; the CDN copy is only registered as a fallback if that handle is absent.

The countdown bars use CSS width transitions rather than Swiper's
`autoplayTimeLeft` event, because that event only exists in Swiper 8.4+ and
Elementor bundles different versions across releases. The cost is pause-on-hover,
which freezes and resumes each bar by reading computed width and recomputing the
remaining duration — the fiddliest part of `rotator.js`, and the first place to
look if a bar desyncs from its slide.

`prefers-reduced-motion: reduce` stops autoplay and fills every bar.

Assets are declared via `get_script_depends()` / `get_style_depends()`, so
Elementor loads them only on pages where the widget is present.

## Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `re_featured_sticky_post_types` | filter | Post types that get the featured checkbox. Defaults to public types minus `post`, `page`, `attachment`. |
| `re_featured_sticky_exclusive` | filter | Return `false` to allow more than one featured item per post type. |
| `re_featured_slides` | filter | The resolved slide set, for reordering or injecting entries. |
| `re_featured_post_type_options` | filter | Post types offered in the Featured Post Types query control. |
| `re_featured_selected_slides` | filter | The resolved slide set for the manual widget. |
| `re_featured_picker_limit` | filter | Posts listed per type in the manual picker. Default 100. |
| `re_featured_picker_options` | filter | The manual picker's full option list. |

## Structure

```
elementor-featured-post-types.php   Bootstrap, constants, Elementor version guard
includes/
  class-plugin.php                  Widget registration, asset handles
  class-sticky-posts.php            CPT sticky support, slide resolution
  class-sticky-admin.php            Featured column, Quick Edit, Bulk Edit
  abstract-rotator-widget.php       Shared controls, render, panel + tab markup
  class-widget-post-types.php       Query section + one-per-post-type resolution
  class-widget-selected-posts.php   Query section + hand-picked resolution
templates/
  slide.php                         Per-slide markup
assets/
  css/rotator.css
  js/rotator.js
  js/sticky-quick-edit.js
```

## Relationship to elementor-post-types

This is a clean-room replacement for the Featured Insights portion of
`Red-Egg-Marketing/elementor-post-types`, which was a fork of the UAEL posts
widget carrying roughly 12,000 lines of skins, masonry, pagination, filters and
schema support that this pattern never used.

Deliberate differences:

- **Shared base class.** Both widgets extend `Rotator_Widget`, which owns every
  control except the query section plus all the rendering. Adding a third variant
  means implementing four methods, not copying 650 lines.
- **No skin layer.** The old code routed everything through Elementor skins,
  which implicitly prefix every control key with the skin ID — a steady source of
  settings lookups that silently resolve to `null`. Control IDs here are literal.
- **No UAEL dependency.** The old plugin referenced `UAEL_DOMAIN` and
  `UAEL_MODULES_DIR`, neither of which it defined, so on PHP 8 it only loaded if
  Ultimate Addons happened to be active.
- **Different widget name.** `re-featured-post-types` rather than `post-types`,
  so both plugins can be active simultaneously without Elementor rejecting a
  duplicate registration. Existing pages using the old widget keep working.

## Status

Syntax-checked against PHP 8.3 and `node --check`, but not yet exercised in a
live Elementor editor. Worth verifying first:

- The Query control's post type list, and whether tabs render in the order picked
- `thumbnail_size` resolving to a real registered image size
- Autoplay and bar sync across a full cycle, including the loop back to the first
  slide
- The manual widget's repeater: row titles, and whether the picker list is long
  enough for the site's content

`get_slides()` on the manual widget and the Quick Edit / Bulk Edit save routing
are both covered by ad-hoc tests against stubbed WordPress functions — valid
picks, drafts, non-public types, deleted posts, malformed settings, ordering,
column placement, nonce routing and the bulk exclusivity interaction. None of it
is committed as a suite yet.

The Quick Edit script wraps `inlineEditPost.edit`, which is still the only hook
WordPress offers for populating custom inline fields. It is stable but undocumented,
so it is worth re-checking after a major WP release.

## License

GPL-2.0-or-later.
