<?php

declare(strict_types=1);

namespace KomodoPeriphery;

require_once "/usr/local/emhttp/plugins/dynamix/include/Helpers.php";
require_once "/usr/local/emhttp/plugins/dynamix/include/Wrappers.php";

const PLUGIN = "komodo-periphery";
const STATUS_SCRIPT = "/usr/local/emhttp/plugins/komodo-periphery/scripts/status.sh";
const CONTROL_SCRIPT = "/etc/rc.d/rc.komodo-periphery";

function getPage(string $page, bool $unused = false, array $context = []): string
{
    [$cfg, $status] = loadState();
    [$message, $messageType, $status] = maybeHandleServiceAction($context['var'] ?? null, $status);

    ob_start();
    renderStyles();

    if ($page === "Overview") {
        renderOverview($status, $message, $messageType);
    } elseif ($page === "Status") {
        renderStatus($status, $message, $messageType, $context['var'] ?? null);
    } elseif ($page === "Settings") {
        renderSettings($cfg, $status);
    } elseif ($page === "Info") {
        renderInfo($status);
    } else {
        echo "<div>Unknown page.</div>";
    }

    return (string) ob_get_clean();
}

function loadState(): array
{
    $cfg = parse_plugin_cfg(PLUGIN);
    $statusOutput = shell_exec(STATUS_SCRIPT . " 2>/dev/null");
    $status = [];
    if (is_string($statusOutput) && trim($statusOutput) !== "") {
        $status = parse_ini_string($statusOutput, false, INI_SCANNER_RAW) ?: [];
    }

    return [$cfg, $status];
}

function maybeHandleServiceAction($var, array $status): array
{
    $message = "";
    $messageType = "normal";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [$message, $messageType, $status];
    }

    if (!isset($_POST['service_action'], $_POST['csrf_token']) || !is_array($var) || ($_POST['csrf_token'] ?? '') !== ($var['csrf_token'] ?? null)) {
        return [$message, $messageType, $status];
    }

    $action = $_POST['service_action'];
    $allowed = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowed, true)) {
        return [$message, $messageType, $status];
    }

    exec(CONTROL_SCRIPT . " " . escapeshellarg($action) . " 2>&1", $cmdOutput, $code);
    $message = implode("\n", $cmdOutput);
    $messageType = $code === 0 ? "success" : "error";

    [, $status] = loadState();

    return [$message, $messageType, $status];
}

function renderStyles(): void
{
    ?>
    <style>
    .komodo-card {
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 18px;
      margin-bottom: 18px;
      background: linear-gradient(135deg, rgba(16, 42, 67, 0.06), rgba(31, 122, 140, 0.08));
    }
    .komodo-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 12px 18px;
    }
    .komodo-kv strong {
      display: block;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .08em;
      opacity: .7;
      margin-bottom: 4px;
    }
    .komodo-pubkey {
      width: 100%;
      min-height: 92px;
      font-family: monospace;
    }
    .komodo-message {
      white-space: pre-wrap;
      padding: 10px 12px;
      border-radius: 10px;
      margin-bottom: 12px;
    }
    .komodo-message.success {
      background: rgba(44, 160, 44, .12);
      border: 1px solid rgba(44, 160, 44, .35);
    }
    .komodo-message.error {
      background: rgba(192, 57, 43, .12);
      border: 1px solid rgba(192, 57, 43, .35);
    }
    .komodo-links a {
      margin-right: 16px;
      font-weight: 600;
    }
    .komodo-note {
      margin-top: 12px;
      opacity: .85;
    }
    </style>
    <?php
}

function renderOverview(array $status, string $message, string $messageType): void
{
    $running = ($status['running'] ?? 'no') === 'yes';
    ?>
    <div class="komodo-card">
      <div class="komodo-grid">
        <div class="komodo-kv">
          <strong>Status</strong>
          <?= $running ? "Running" : "Stopped"; ?>
        </div>
        <div class="komodo-kv">
          <strong>Connect As</strong>
          <?= htmlspecialchars($status['connect_as'] ?? ''); ?>
        </div>
        <div class="komodo-kv">
          <strong>Core Address</strong>
          <?= htmlspecialchars($status['core_address'] ?? ''); ?>
        </div>
        <div class="komodo-kv">
          <strong>Plugin Version</strong>
          <?= htmlspecialchars($status['plugin_version'] ?? 'unknown'); ?>
        </div>
      </div>
    </div>

    <?php if ($message !== ""): ?>
      <div class="komodo-message <?= $messageType === 'success' ? 'success' : 'error'; ?>"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="komodo-card">
      <p>Komodo Periphery runs on the Unraid host and connects outbound to Komodo Core. Use the tabs above for live service control, configuration, and onboarding guidance.</p>
      <div class="komodo-links">
        <a href="/Settings/Komodo%20Periphery/Status">Open Status</a>
        <a href="/Settings/Komodo%20Periphery/Settings">Open Settings</a>
        <a href="/Settings/Komodo%20Periphery/Info">Open Info</a>
      </div>
      <p class="komodo-note">Persistent state lives under <code>/boot/config/komodo/periphery-agent</code>. Runtime work trees can be moved to cache or appdata with <code>PERIPHERY_ROOT_DIRECTORY</code>.</p>
    </div>
    <?php
}

function renderStatus(array $status, string $message, string $messageType, $var): void
{
    $running = ($status['running'] ?? 'no') === 'yes';
    $publicKey = $status['public_key'] ?? '';
    ?>
    <div class="komodo-card">
      <div class="komodo-grid">
        <div class="komodo-kv">
          <strong>Status</strong>
          <?= $running ? "Running" : "Stopped"; ?>
        </div>
        <div class="komodo-kv">
          <strong>PID</strong>
          <?= htmlspecialchars($status['pid'] ?? ''); ?>
        </div>
        <div class="komodo-kv">
          <strong>Connect As</strong>
          <?= htmlspecialchars($status['connect_as'] ?? ''); ?>
        </div>
        <div class="komodo-kv">
          <strong>Core Address</strong>
          <?= htmlspecialchars($status['core_address'] ?? ''); ?>
        </div>
      </div>
    </div>

    <?php if ($message !== ""): ?>
      <div class="komodo-message <?= $messageType === 'success' ? 'success' : 'error'; ?>"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(is_array($var) ? ($var['csrf_token'] ?? '') : ''); ?>">
      <?php if ($running): ?>
        <input type="submit" name="service_action" value="stop">
        <input type="submit" name="service_action" value="restart">
      <?php else: ?>
        <input type="submit" name="service_action" value="start">
      <?php endif; ?>
      <input type="button" value="Reload" onclick="location.reload();">
    </form>

    <br>

    <form markdown="1">
    _(Public Key)_:
    : <textarea class="komodo-pubkey" readonly><?= htmlspecialchars($publicKey); ?></textarea>

    _(Runtime Config File)_:
    : `<?= htmlspecialchars($status['runtime_config_file'] ?? '/boot/config/komodo/periphery-agent/config/periphery.config.toml'); ?>`

    _(Runtime Log File)_:
    : `<?= htmlspecialchars($status['log_file'] ?? '/var/log/komodo-periphery.log'); ?>`
    </form>
    <?php
}

function renderSettings(array $cfg, array $status): void
{
    ?>
    <form method="POST" action="/update.php" target="progressFrame" markdown="1">
      <input type="hidden" name="#file" value="komodo-periphery/komodo-periphery.cfg">

    _(Service Enabled)_:
    : <select name="SERVICE_ENABLED" class="narrow">
        <option value="no" <?= ($cfg['SERVICE_ENABLED'] ?? 'no') === 'no' ? 'selected' : ''; ?>>Disabled</option>
        <option value="yes" <?= ($cfg['SERVICE_ENABLED'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Enabled</option>
      </select>

    > When enabled, the plugin starts Komodo Periphery after install, update, and boot.

    _(PERIPHERY_CORE_ADDRESS)_:
    : <input type="text" name="PERIPHERY_CORE_ADDRESS" class="wide" value="<?= htmlspecialchars($cfg['PERIPHERY_CORE_ADDRESS'] ?? ''); ?>">

    _(PERIPHERY_CONNECT_AS)_:
    : <input type="text" name="PERIPHERY_CONNECT_AS" class="wide" value="<?= htmlspecialchars($cfg['PERIPHERY_CONNECT_AS'] ?? ''); ?>">

    _(PERIPHERY_ONBOARDING_KEY)_:
    : <input type="password" name="PERIPHERY_ONBOARDING_KEY" class="wide" value="<?= htmlspecialchars($cfg['PERIPHERY_ONBOARDING_KEY'] ?? ''); ?>">

    > Leave this empty if the target server already exists in Komodo Core. Use it only for first-time outbound enrollment.

    _(PERIPHERY_ROOT_DIRECTORY)_:
    : <input type="text" name="PERIPHERY_ROOT_DIRECTORY" class="wide" value="<?= htmlspecialchars($cfg['PERIPHERY_ROOT_DIRECTORY'] ?? '/boot/config/komodo/periphery-agent/data'); ?>">

    > For production use on Unraid, a cache or appdata path is recommended for active repos, stacks, and builds.

    _(PERIPHERY_LOG_LEVEL)_:
    : <select name="PERIPHERY_LOG_LEVEL" class="narrow">
        <?php foreach (['error', 'warn', 'info', 'debug', 'trace'] as $level): ?>
          <option value="<?= $level; ?>" <?= ($cfg['PERIPHERY_LOG_LEVEL'] ?? 'info') === $level ? 'selected' : ''; ?>><?= strtoupper($level); ?></option>
        <?php endforeach; ?>
      </select>

    _(Disable Terminals)_:
    : <select name="PERIPHERY_DISABLE_TERMINALS" class="narrow">
        <option value="no" <?= ($cfg['PERIPHERY_DISABLE_TERMINALS'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
        <option value="yes" <?= ($cfg['PERIPHERY_DISABLE_TERMINALS'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
      </select>

    _(Disable Container Terminals)_:
    : <select name="PERIPHERY_DISABLE_CONTAINER_TERMINALS" class="narrow">
        <option value="no" <?= ($cfg['PERIPHERY_DISABLE_CONTAINER_TERMINALS'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
        <option value="yes" <?= ($cfg['PERIPHERY_DISABLE_CONTAINER_TERMINALS'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
      </select>

    _(PERIPHERY_CORE_PUBLIC_KEYS)_:
    : <input type="text" name="PERIPHERY_CORE_PUBLIC_KEYS" class="wide" value="<?= htmlspecialchars($cfg['PERIPHERY_CORE_PUBLIC_KEYS'] ?? ''); ?>">

    _(Include Disk Mounts)_:
    : <input type="text" name="PERIPHERY_INCLUDE_DISK_MOUNTS" class="wide" value="<?= htmlspecialchars($cfg['PERIPHERY_INCLUDE_DISK_MOUNTS'] ?? ''); ?>">

    _(Exclude Disk Mounts)_:
    : <input type="text" name="PERIPHERY_EXCLUDE_DISK_MOUNTS" class="wide" value="<?= htmlspecialchars($cfg['PERIPHERY_EXCLUDE_DISK_MOUNTS'] ?? ''); ?>">

    _(Current Runtime Config)_:
    : `<?= htmlspecialchars($status['runtime_config_file'] ?? '/boot/config/komodo/periphery-agent/config/periphery.config.toml'); ?>`

    &nbsp;
    : <span class="buttons-spaced">
        <input type="submit" value="_(Apply)_">
        <input type="button" value="_(Done)_" onclick="done()">
      </span>
    </form>
    <?php
}

function renderInfo(array $status): void
{
    ?>
    <div class="komodo-card">
      <h3>About Komodo Periphery</h3>
      <p>Komodo Periphery is the host-side agent for Komodo. On Unraid it runs natively as a managed plugin service and connects outbound to Komodo Core over WebSocket.</p>
      <div class="komodo-links">
        <a href="https://github.com/moghtech/komodo" target="_blank" rel="noopener noreferrer">Komodo on GitHub</a>
        <a href="https://komo.do/docs" target="_blank" rel="noopener noreferrer">Komodo Documentation</a>
        <a href="https://komo.do/docs/setup/connect-servers" target="_blank" rel="noopener noreferrer">Connect Servers Guide</a>
      </div>
    </div>

    <div class="komodo-card">
      <h3>Onboarding Flow</h3>
      <ol>
        <li>Create or identify the target server in Komodo Core.</li>
        <li>Set <code>PERIPHERY_CORE_ADDRESS</code> to the public or internal URL of Komodo Core.</li>
        <li>Set <code>PERIPHERY_CONNECT_AS</code> to the exact Komodo server name or ID you want this Unraid host to use.</li>
        <li>If the server does not exist yet, generate an onboarding key in Komodo Core and paste it into <code>PERIPHERY_ONBOARDING_KEY</code>.</li>
        <li>Apply settings and start the service from the Status tab.</li>
        <li>Copy the public key shown on the Status tab if you prefer explicit key-based approval instead of onboarding.</li>
      </ol>
      <p>Once connected, Komodo Core will recognize this Unraid host as the configured server identity.</p>
    </div>

    <div class="komodo-card">
      <h3>Unraid-Specific Notes</h3>
      <ul>
        <li>The plugin stores durable identity and runtime config under <code>/boot/config/komodo/periphery-agent</code>.</li>
        <li>The active working directory for repos, stacks, and builds is controlled by <code>PERIPHERY_ROOT_DIRECTORY</code>.</li>
        <li>For light setups, a flash-backed path works. For sustained activity, prefer a cache or appdata path such as <code>/mnt/cache/appdata/komodo-periphery</code>.</li>
        <li>Service lifecycle is tied to the Unraid plugin system, not <code>systemd</code>.</li>
      </ul>
      <p>Current public key file: <code><?= htmlspecialchars($status['public_key_file'] ?? '/boot/config/komodo/periphery-agent/keys/periphery.pub'); ?></code></p>
    </div>
    <?php
}
