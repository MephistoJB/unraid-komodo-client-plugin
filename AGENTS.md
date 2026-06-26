# AGENTS.md

- You find relevant tokens in token.md. Never upload this to git or github
- You run on a Mac-mini Host.
- The applications you are interested in are docker containers, which are administrated by a Komodo instance.
- IMPORTANT: You are only allowed to run docker commands on the host without my explicit approval.
- Whenever you need access to n8n use the n8n MCP and the n8n skill.
    - The n8n MCP is registered globally in Codex as `n8n` at `http://127.0.0.1:5679/mcp`.
    - The n8n MCP runs as service `n8n-mcp` in the Komodo stack named `assistant` on server `Macserver`.
    - The n8n MCP requires `WEBHOOK_SECURITY_MODE=permissive` so it can reach n8n over the private Docker network; cloud metadata endpoints remain blocked.
    - n8n MCP authentication uses the local environment variable `N8N_MCP_AUTH_TOKEN`. Its login-persistent LaunchAgent is `~/Library/LaunchAgents/org.codex.n8n-mcp-token.plist`; never copy the token into this repository.
    - If the n8n MCP token changes, retrieve `AUTH_TOKEN` from the running `n8n-mcp` container through the Komodo MCP and update the local LaunchAgent.
    - The n8n skills from `czlonkowski/n8n-skills` are installed globally under `~/.codex/skills/n8n-*`. Always consult `n8n-mcp-tools-expert` before using n8n MCP tools.    
- Try to act with native docker commands. If necessary, you can  Komodo use the Komodo MCP and the komodo skill.
- IMPORTANT: run type check after every code change (prevents broken types).
- Make minimal changes, don't refactor unrelated code.
- you have also access to other servers via ssh. ask for access when needed. if i give you access, save it here.
- Create separate commits per logical change. Upload to github only when asked by me.
- When unsure, explain both approaches and let me choose.
- When you update the code, update this AGENTS.md and the README.md if necessary to reflect the changes.
- Fill out the next chapters and keep them up to date. You find an example in AGENTS.md.example

## Project

Native Unraid plugin for running `komodo periphery` directly on the Unraid host as a managed service.

- No Docker container for the agent itself
- Installation through `.plg`
- Persistent settings in `/boot/config/plugins/komodo-periphery/`
- Persistent Komodo state and keys in `/boot/config/komodo/periphery-agent/`
- GitHub-release-based bundle distribution
- Community Applications metadata kept in-repo

## Stack

- Unraid plugin packaging via `.plg`
- Shell scripts for install/update/service control
- Unraid `.page` PHP page for settings and status
- GitHub Releases as distribution channel for the plugin bundle
- Upstream Komodo Periphery Linux `x86_64` binary pinned via SHA256 in `meta/plugin.env`

## Commands

- Build bundle: `bash ./scripts/build-bundle.sh`
- Regenerate `.plg` and CA XML: `bash ./scripts/render-metadata.sh`
- Prepare release artifacts: `bash ./scripts/release-prep.sh`
- Shell syntax check: `find scripts src -type f \( -name '*.sh' -o -name 'rc.*' \) -print0 | xargs -0 -n1 bash -n`
- PHP page lint on Linux/CI: `php -l src/usr/local/emhttp/plugins/komodo-periphery/KomodoPeriphery.page`

## Architecture

- Repository metadata:
  - `meta/plugin.env` pins plugin metadata and upstream Komodo artifact
- GitHub-facing artifacts:
  - `komodo-periphery.plg`
  - `komodo-periphery.xml`
- Unraid runtime files:
  - `src/usr/local/emhttp/plugins/komodo-periphery/`
  - `src/etc/rc.d/rc.komodo-periphery`
- Persistent user config:
  - `/boot/config/plugins/komodo-periphery/komodo-periphery.cfg`
- Persistent Komodo identity and runtime config:
  - `/boot/config/komodo/periphery-agent/keys/`
  - `/boot/config/komodo/periphery-agent/config/periphery.config.toml`
- Volatile runtime state:
  - `/var/run/komodo-periphery.pid`
  - `/var/log/komodo-periphery.log`

## Rules

- Never commit `token.md` or secrets derived from it
- Do not use Docker for the plugin runtime
- Do not add `systemd`; Unraid service control must stay on `rc.d`
- Preserve existing keys on update and default uninstall path
- Keep config, keys, logs, and runtime code clearly separated
- Run syntax or type checks after changes
- Keep CA metadata and `.plg` consistent with the released bundle URL

## Workflow

1. Update `VERSION`, `CHANGELOG.md`, and `meta/plugin.env` if the release changes.
2. Run `bash ./scripts/release-prep.sh`.
3. Verify generated `komodo-periphery.plg` and `komodo-periphery.xml`.
4. Publish the generated bundle from `dist/` in a GitHub release matching `v<version>`.
5. Only after the release asset exists, use the raw `.plg` URL for installation and CA submission.

## Out of scope

- Managing Komodo Core itself
- Shipping the plugin runtime in Docker
- Writing secrets or logs persistently to the repo
- Automatic CA submission from this repository
