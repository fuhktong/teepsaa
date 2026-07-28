# Should teepsaa build an Amazon-style customizable storefront?

You looked at Nintendo's Amazon page — big single product, then square pics,
then a banner, then a grid of products — and asked whether that's a system
where the seller chooses each image's shape, and whether we should let vendors
customize their pics like that.

Short answer: **that's exactly what Amazon does, but copying it wholesale would
be a mistake for teepsaa right now.** Here's the full reasoning.

---

## What that Amazon page actually is

That Nintendo page is an **Amazon Store** — not a normal seller page. It's built
with Amazon's **Store Builder**, which is a drag-and-drop **page builder** (a
mini-CMS). The brand assembles the page out of **tiles/widgets**, each with a
type:

- Full-width banner
- Half-width or quarter-width image tiles (the "square pics")
- Product grids
- Text blocks, video, "shop the look," etc.

So yes — it **is** a system where the seller chooses each image's shape and
arranges them. The 16:9 banner, the square tiles, the product rows are all
*blocks the brand placed themselves*. Amazon isn't picking that layout;
Nintendo's marketing team built it.

---

## Why I'd NOT build that for teepsaa (yet)

Two reasons, and they're both about who your vendors are.

**1. It's a massive build.** A real page builder means: a block-type system,
per-block ordering, image cropping/aspect-ratio enforcement per block type, a
live preview, mobile reflow rules, a storage schema for the layout, and an
editing UI. That's weeks of work and a permanent maintenance surface — and it's
the single most complex feature in Amazon's seller tooling, backed by a large
team.

**2. It fights your core goal.** Your vendors are small Phnom Penh clothing
shops uploading phone photos, and your whole design goal is *simple and
idiot-proof, handoff-able to a bilingual employee*. A blank-canvas page builder
produces **great pages for Nintendo and ugly, broken pages for everyone else.**
Amazon can absorb that because they have millions of brands; a young marketplace
can't — every ugly shop drags down the whole site's credibility. Freedom is a
liability here, not a feature.

---

## What to build instead: a constrained, opinionated template

Keep *one* good-looking layout (the one we're refining) and give vendors a
**small number of idiot-proof knobs** — never a blank canvas. This gets you 80%
of the visual richness with ~5% of the build, and it's impossible to make ugly:

| Knob | What the vendor does | Guardrail |
|---|---|---|
| **Banner** (done) | Upload 1 wide image | Fixed full-width slot, auto-cropped |
| **Featured product** | Tick 1–2 products as "featured" | Rendered as a big hero tile at top — this is your "large single product" |
| **Gallery** | Upload up to N photos | We render them in a fixed square grid + lightbox — this is your "square pics" |
| **Category order** (later) | Reorder their product sections | Templated, can't break |

That reproduces the Amazon *feel* you liked — big hero product, square image
tiles, banner, then the product grid — but the **layout is ours, so it always
looks right**. The vendor supplies content, not design decisions.

---

## Recommendation

Don't build the page builder. Instead build the **"featured product" hero + a
proper square gallery grid**, which together give you the exact Amazon structure
you described (single big product → square tiles → products) without any of the
risk.

Two phases:

- **Phase 1 (small, high value):** featured-product hero tile + convert the
  gallery from the current full-width stack to a square thumbnail grid with a
  lightbox. Reorder so products come before the gallery.
- **Phase 2 (only if vendors ask):** let vendors reorder their product sections
  or pick a second accent image. Still templated.

If you ever truly need the full builder, it's a post-traction, "we have 500
vendors and big brands are asking" feature — not a launch feature.

**Next step:** say "go" and I'll build Phase 1 (featured-product hero + square
gallery grid). Or I can mock the new storefront layout as a visual page first so
you can see it before committing.
