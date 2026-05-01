# Local WordPress Testing

This project uses Docker and `@wordpress/env` to run a disposable local WordPress site for testing the Superanimate GSAP Elementor plugin.

## Requirements

- Docker Desktop installed and running
- Node.js and npm installed

## First Setup

Install npm dependencies:

```bash
npm install
```

Start WordPress:

```bash
npm run wp:start
```

Open:

```text
http://localhost:8888
```

Default login:

```text
Username: admin
Password: password
```

## Daily Commands

Start the local WordPress site:

```bash
npm run wp:start
```

Stop the local WordPress site:

```bash
npm run wp:stop
```

Reset the local site database and files:

```bash
npm run wp:reset
```

Run WP-CLI inside the WordPress container:

```bash
npm run wp:cli -- plugin list
```

Activate Elementor:

```bash
npm run wp:cli -- plugin activate elementor
```

Then activate `Superanimate GSAP Elementor` from the WordPress Plugins screen. If you want to activate it with WP-CLI, first check the exact plugin slug:

```bash
npm run wp:cli -- plugin list
```

## Adding More Plugins

Free WordPress.org plugins can be added to `.wp-env.json` as ZIP URLs:

```json
"https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip"
```

Paid or private plugins, such as Elementor Pro, should be added locally and ignored by Git:

```text
local-plugins/elementor-pro/
```

Then reference the local folder in `.wp-env.json`:

```json
"./local-plugins/elementor-pro"
```

Do not commit paid plugin files or private ZIPs.

## Manual Test Checklist

- WordPress loads at `http://localhost:8888`.
- Elementor is active.
- Superanimate GSAP Elementor is active.
- Elementor editor shows the Superanimate controls.
- Frontend pages load `animation-preset-plugin.css`, `animation-preset-plugin.js`, GSAP, ScrollTrigger, and SplitType.
- Test one widget for each animation category:
  - Scroll Transform
  - Split Text
  - Image Reveal
  - Container Reveal
  - Scroll Fill Text
- Confirm editor preview behavior and frontend animation behavior.
