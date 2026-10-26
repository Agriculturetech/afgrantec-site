# AGENTS.md

Static site for **Afgrantec** (poultry/swine equipment, Latin America). Astro 7 + Tailwind CSS 4 + TypeScript 6, content in Spanish. No backend, no database. **Two pages, two separate designs, intentionally not linked to each other:**

- `src/pages/index.astro` — original Tailwind page, placeholder ("Sitio en construcción"), content in `src/data/site.ts`.
- `src/pages/inicio.astro` — full page converted from the standalone static site in a sibling folder (`afgrantec-site/`), plain CSS (not Tailwind), content inline.

## Commands

```bash
pnpm dev        # dev server at http://localhost:4321
pnpm check      # type check (astro check) — the only verification step
pnpm build      # static output to dist/
pnpm preview    # serve the built site
```

- **Node `>=22.22.0` is required** and Astro 7 refuses to run otherwise. The default `node` on this machine is `v22.15.0` and **fails immediately**. Run commands through fnm's Node 24 instead:

  ```bash
  fnm exec --using v24.21.0 -- node node_modules/astro/bin/astro.mjs build   # or check / dev / preview
  ```
- **Use pnpm only** (`packageManager: pnpm@12.4.2`; `pnpm-lock.yaml` is the source of truth).
- There is **no lint, format, test, or CI config**. Do not invent `pnpm test` / `pnpm lint`; `pnpm check` + `pnpm build` is the full verification.
- `pnpm-workspace.yaml` exists only to pre-approve builds for `esbuild` and `sharp`. If a new dependency needs postinstall scripts, pnpm will block it until approved there.

## Architecture

- `index.astro` gets its **editable content from `src/data/site.ts`** (SEO metadata, contact `email`, `services[]`, `countries[]`) and is wrapped in `src/layouts/Layout.astro` (which also imports the Tailwind `global.css`). `inicio.astro` does **not** use `site.ts` or `Layout` — it is a self-contained HTML document with hero, brands, equipment, services, about and a contact form; its copy is inline.
- **`Header.astro` and `Footer.astro` are shared by both pages** and now render the new (blue) `afgrantec-site` design (logo image, `site-nav`, `.footer-*`). Both components import the new CSS, so **`index.astro` also loads `site.css`/`opening-a.css`** and its Tailwind styling coexists with them.
- `inicio.astro` imports `src/styles/site.css` + `src/styles/opening-a.css` (copied verbatim from `afgrantec-site/styles.css` and `opening-a.css`; only the `@font-face` `url()`s were rewritten to `/assets/...`). Static files live under `public/assets/`, and the contact-form behavior is `public/app.js` (loaded with `<script is:inline src="/app.js" defer>`).
- To add a Tailwind page, create a file in `src/pages/` and wrap it in `Layout`. `inicio.astro` is the exception (standalone).

## Tailwind 4 quirks (easy to get wrong)

- Configured as a **Vite plugin** in `astro.config.mjs` (`@tailwindcss/vite`). There is **no `tailwind.config` file** and no PostCSS config — don't look for one, don't add one.
- Design tokens are CSS variables in the `@theme` block of `src/styles/global.css`: `--color-ink`, `--color-brand`, `--color-sand`, etc. Renaming a variable renames the utility (`bg-sand`, `text-brand`).
- `wrap` is a **custom `@utility`** in `global.css` (max-width 1080px centered container) — the standard wrapper for every section. Use it instead of inventing a new container class.
- **`.wrap` name collision:** `src/styles/site.css` (loaded on both pages via `Header`/`Footer`) also defines a global, *unlayered* `.wrap` (max-width 1534px). Unlayered CSS beats Tailwind's layered utilities, so on `index.astro` the unlayered `.wrap` wins and widens the layout. This is expected after sharing the new design and is a known thing to fix, not a bug to chase.

## Conventions

- **All user-facing content and copy is Spanish** (`lang="es"`); keep new text in Spanish and match the existing tone in `site.ts`.
- Strict TypeScript: `tsconfig.json` extends `astro/tsconfigs/strict`.
- `dist/` and `.astro/` are generated — never edit them by hand.
- The site currently shows a "Sitio en construcción" badge in the hero; removing it is a deliberate content change in `src/pages/index.astro`.
- This directory is **not a git repository** — no branch/commit/PR workflow exists yet.
