# Solo Estate

A WordPress plugin for real estate developers, made by [Solo Studio](https://solostudio.ge).

Visitors move through **projects → buildings → floors → apartments** on the developer's own images. Clickable polygons on each image show status, counts and prices. Everything is managed from wp-admin.

## Features

- Interactive masterplans, building facades and floor plans with polygons drawn in the admin. Tooltips show availability; a full screen view keeps polygons and tooltips working.
- Projects, phases, buildings, floors, parkings, apartments, commercial spaces and villas.
- Statuses with colours (available, reserved, sold…), with counts for every building and floor.
- Apartment pages with plans, photos, specifications, price and a currency switch.
- Apartment search by building, rooms, area, floor and price.
- Projects catalog with status tabs (ongoing / completed).
- Lead form. Leads are stored in the admin and can be sent by email or webhook.
- Multilingual. Works with Polylang and WPML; front-end texts and admin labels are editable per language under **Settings → Localization**. Georgian translation included.
- CSV import and export of units.
- Style settings: colours, font and radius.
- User roles: manager, marketing and sales.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later

## Installation

1. Download this repository as a ZIP (**Code → Download ZIP**). Unpack it and rename the folder to `solo-estate`, then zip the folder again.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the ZIP and activate the plugin.
3. Add projects, buildings and floors under **Solo Estate** in the admin.
4. Put the shortcode on a page.

## Shortcodes

| Shortcode | What it shows |
| --- | --- |
| `[solo_estate]` | The catalog of all projects, with navigation down to apartments |
| `[solo_estate id="N"]` | A single project |
| `[solo_estate_lead_form]` | The lead form on its own |

A **Solo Estate project** block is also available in the block editor.

## Templates

Front-end templates can be overridden by copying them from `templates/` to `yourtheme/solo-estate/`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
