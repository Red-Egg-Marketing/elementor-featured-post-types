# Elementor Featured Post Types

An Elementor widget that shows one featured post per post type, rotated one at a
time, with a labelled countdown tab for each. Adds sticky support to custom post
types so editors can choose what gets featured.

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

1. Add the **Featured Post Types** widget to a page (General category).
2. Under **Query**, pick the post types to include. One item is shown per type,
   in the order listed.
3. Feature an item by editing it and ticking **Feature this {Post Type}** in the
   Publish box.

Only one item per post type can be featured at a time — ticking a new one clears
the previous. If nothing is featured for a type, the widget falls back to that
type's most recent published post, so a tab is never empty. Disable that with
**Fall Back To Latest**.

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
| `re_featured_post_type_options` | filter | Post types offered in the widget's Query control. |

## Structure

```
elementor-featured-post-types.php   Bootstrap, constants, Elementor version guard
includes/
  class-plugin.php                  Widget registration, asset handles
  class-sticky-posts.php            CPT sticky support, slide resolution
  class-widget.php                  Controls and render
templates/
  slide.php                         Per-slide markup
assets/
  css/rotator.css
  js/rotator.js
```

## Relationship to elementor-post-types

This is a clean-room replacement for the Featured Insights portion of
`Red-Egg-Marketing/elementor-post-types`, which was a fork of the UAEL posts
widget carrying roughly 12,000 lines of skins, masonry, pagination, filters and
schema support that this pattern never used.

Deliberate differences:

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

## License

GPL-2.0-or-later.
