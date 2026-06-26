# Komodo Periphery Unraid Plugin

Native Unraid plugin for running [Komodo Periphery](https://github.com/moghtech/komodo) directly on the host without Docker or `systemd`.

The plugin is designed for production-style Unraid usage:

- installation through a `.plg`
- host-native daemon management via Unraid `rc.d`
- event-driven boot/shutdown handling via Unraid `event/started` and `event/stopping`
- persistent config in `/boot/config/plugins/komodo-periphery/`
- persistent Komodo state and keys in `/boot/config/komodo/periphery-agent/`
- outbound connection model to Komodo Core
- update-safe key handling so binary upgrades do not rotate identities by accident

## What This Repository Contains

- `komodo-periphery.plg`
  - the install entrypoint Unraid consumes
- `plugins/komodo-periphery.xml`
  - Community Applications plugin wrapper
- `ca_profile.xml`
  - repository-level Community Applications profile metadata used by the CA submission portal
- `src/`
  - files that end up on the Unraid host
- `scripts/`
  - local build and release helpers
- `meta/`
  - release metadata, upstream Komodo version pin, and CA text

## Architecture

The plugin intentionally separates active files from persistent state:

- Active plugin files in RAM:
  - `/usr/local/emhttp/plugins/komodo-periphery/`
  - `/usr/local/etc/rc.d/rc.komodo-periphery`
  - `/etc/rc.d/rc.komodo-periphery` as a compatibility symlink for Unraid WebGUI service checks
- Persistent user config on the flash:
  - `/boot/config/plugins/komodo-periphery/komodo-periphery.cfg`
  - `/boot/config/plugins/komodo-periphery/komodo-periphery-<version>-x86_64-1.tgz`
- Persistent Komodo state on the flash:
  - `/boot/config/komodo/periphery-agent/keys/periphery.key`
  - `/boot/config/komodo/periphery-agent/keys/periphery.pub`
  - `/boot/config/komodo/periphery-agent/config/periphery.config.toml`
  - `/boot/config/komodo/periphery-agent/data/`
- Volatile runtime files:
  - `/var/run/komodo-periphery.pid`
  - `/var/log/komodo-periphery.log`

This keeps the Unraid plugin lifecycle conventional while ensuring Komodo identity survives reinstall, reboot, and updates.
It also avoids starting Periphery too early during boot, before shares and the array are ready.

## Supported Settings

The settings page and persisted config support at least:

- `PERIPHERY_CORE_ADDRESS`
- `PERIPHERY_CONNECT_AS`
- `SERVICE_ENABLED`

It also exposes a few pragmatic extras:

- `PERIPHERY_ONBOARDING_KEY`
- `PERIPHERY_ROOT_DIRECTORY`
- `PERIPHERY_LOG_LEVEL`
- `PERIPHERY_DISABLE_TERMINALS`
- `PERIPHERY_DISABLE_CONTAINER_TERMINALS`
- `PERIPHERY_CORE_PUBLIC_KEYS`
- include / exclude disk mount filters

## Onboarding

The plugin supports the two common Komodo onboarding models:

1. Existing server identity in Komodo Core
   - set `PERIPHERY_CORE_ADDRESS`
   - set `PERIPHERY_CONNECT_AS` to the existing server name or ID
   - leave `PERIPHERY_ONBOARDING_KEY` empty

2. First-time enrollment with onboarding key
   - generate an onboarding key in Komodo Core
   - set `PERIPHERY_CORE_ADDRESS`
   - set `PERIPHERY_CONNECT_AS`
   - paste the onboarding key into `PERIPHERY_ONBOARDING_KEY`
   - start the service

You can also review the generated Periphery public key from the plugin UI and use it for an approval flow based on explicit public keys.

## Build A Release

This repository does not commit the upstream Komodo binary. Instead, the build step downloads the pinned official Linux `x86_64` artifact from the current stable Komodo release and packages it into the plugin bundle.

Current upstream pin:

- Komodo release: `v2.2.0`
- binary asset: `periphery-x86_64`
- upstream SHA256: `ace9007805dbfe75ad73c75c36bb26852fa909d825577f31f5d13eecd3c52660`

Run:

```bash
bash ./scripts/build-bundle.sh
bash ./scripts/render-metadata.sh
```

Or:

```bash
bash ./scripts/release-prep.sh
```

That produces:

- `dist/komodo-periphery-<version>-x86_64-1.tgz`
- refreshed `komodo-periphery.plg`
- refreshed `komodo-periphery.xml`

## Install On Unraid

After publishing a GitHub release that contains the generated bundle:

1. Open `Plugins` in Unraid.
2. Choose `Install Plugin`.
3. Paste the raw GitHub URL to [`komodo-periphery.plg`](https://raw.githubusercontent.com/MephistoJB/unraid-komodo-client-plugin/main/komodo-periphery.plg).
4. Open `Settings -> Network Services -> Komodo Periphery`.
5. Set:
   - `PERIPHERY_CORE_ADDRESS`
   - `PERIPHERY_CONNECT_AS`
   - optionally `PERIPHERY_ONBOARDING_KEY`
   - `Service Enabled = Enabled`
6. Click `Apply`, then start the service if it is not already running.

The UI is split into:

- `Overview`
- `Status`
- `Settings`
- `Info`

This now follows the Tailscale-style Unraid pattern more closely: a single root page under `Network Services` with numbered tab pages for `Settings`, `Status`, and `Info`.
Internally, the child `.page` files use `Menu="KomodoPeriphery"` so Dynamix attaches them to the parent page name instead of the display title.

## Update Strategy

The plugin update strategy is intentionally conservative:

- the shipped binary is replaced on plugin update
- the bundle is extracted with `--no-same-owner --no-same-permissions` so host directory metadata is not polluted by the build machine
- `periphery.key` and `periphery.pub` live outside the package under `/boot/config/komodo/periphery-agent/keys/`
- uninstall preserves persistent config and keys by default
- runtime config is rendered idempotently from the user config each time the service is started

This avoids accidental re-keying when the plugin bundle changes.

## Tradeoff Chosen

The default `PERIPHERY_ROOT_DIRECTORY` is `/boot/config/komodo/periphery-agent/data` so the setup is fully self-contained and survives reboot without depending on array timing.

Tradeoff:

- pro: simplest and safest first install
- con: heavier Komodo repo / stack / build activity may write too much to the USB flash

For larger or more active installations, change `PERIPHERY_ROOT_DIRECTORY` to a cache or appdata path such as `/mnt/cache/appdata/komodo-periphery`.

## Community Applications / App Store Readiness

This repository already includes the core pieces CA expects:

- stable `.plg` URL
- plugin wrapper under `plugins/`
- icon URL
- project/support links
- minimum Unraid version

To actually get listed, you still need:

1. A public GitHub release containing the generated bundle referenced by `komodo-periphery.plg`.
2. A public support thread on the Unraid forums.
3. A repository-level `ca_profile.xml` and a plugin wrapper under `plugins/komodo-periphery.xml`. The release prep script now generates both.
4. Submission through the current CA portal flow at `https://ca.unraid.net/submit`, then `Validate`, `Scan`, and final review submission.
5. Consistent long-term `pluginURL` ownership. Do not change the plugin identity or URL casually, because CA treats that as a security-sensitive event.
6. Basic review readiness:
   - clean install/update/remove behavior
   - no secrets in repo
   - clear support and project metadata

## Validation

The repository includes a GitHub Actions workflow that:

- syntax-checks shell scripts
- installs PHP CLI on Ubuntu for page linting
- builds the bundle
- regenerates plugin metadata
- validates the generated XML

## License

MIT for the plugin wrapper code in this repository.

Komodo Periphery itself remains distributed under its upstream license from the official Komodo release assets.
