<?php

declare(strict_types=1);

namespace KomodoPeriphery;

require_once "/usr/local/emhttp/plugins/dynamix/include/Helpers.php";
require_once "/usr/local/emhttp/plugins/dynamix/include/Wrappers.php";

const PLUGIN = "komodo-periphery";
const STATUS_SCRIPT = "/usr/local/emhttp/plugins/komodo-periphery/scripts/status.sh";
const CONTROL_SCRIPT = "/etc/rc.d/rc.komodo-periphery";
const DEFAULT_ROOT_DIRECTORY = "/boot/config/komodo/periphery-agent/data";
const DEFAULT_RUNTIME_CONFIG = "/boot/config/komodo/periphery-agent/config/periphery.config.toml";
const DEFAULT_PUBLIC_KEY_FILE = "/boot/config/komodo/periphery-agent/keys/periphery.pub";
const DEFAULT_LOG_FILE = "/var/log/komodo-periphery.log";

function getPage(string $page, bool $unused = false, array $context = []): string
{
    [$cfg, $status] = loadState();
    [$message, $messageType, $status] = maybeHandleServiceAction($context['var'] ?? null, $status);

    ob_start();
    renderStyles();

    switch ($page) {
        case "Komodo Periphery":
            renderOverview($cfg, $status, $message, $messageType);
            break;
        case "Settings":
            renderSettings($cfg, $status);
            break;
        case "Status":
            renderStatus($cfg, $status, $message, $messageType, $context['var'] ?? null);
            break;
        case "Info":
            renderInfo($cfg, $status);
            break;
        default:
            echo "<div class=\"komodo-empty\">Unknown page.</div>";
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

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return [$message, $messageType, $status];
    }

    if (!isset($_POST['service_action'], $_POST['csrf_token']) || !is_array($var) || ($_POST['csrf_token'] ?? '') !== ($var['csrf_token'] ?? null)) {
        return [$message, $messageType, $status];
    }

    $action = (string) $_POST['service_action'];
    $allowed = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowed, true)) {
        return [$message, $messageType, $status];
    }

    exec(CONTROL_SCRIPT . " " . escapeshellarg($action) . " 2>&1", $cmdOutput, $code);
    $message = trim(implode("\n", $cmdOutput));
    $messageType = $code === 0 ? "success" : "error";

    [, $status] = loadState();

    return [$message, $messageType, $status];
}

function renderStyles(): void
{
    ?>
    <style>
    .komodo-stack {
      display: grid;
      gap: 18px;
    }
    .komodo-card {
      border: 1px solid rgba(80, 116, 146, 0.22);
      border-radius: 14px;
      padding: 18px 20px;
      background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(240,246,250,0.92));
      box-shadow: 0 10px 26px rgba(36, 62, 87, 0.06);
    }
    .komodo-hero {
      display: grid;
      grid-template-columns: minmax(0, 1.3fr) minmax(260px, 0.7fr);
      gap: 16px;
      align-items: start;
    }
    .komodo-title {
      margin: 0 0 8px;
      font-size: 28px;
      line-height: 1.1;
      color: #16324a;
    }
    .komodo-subtitle {
      margin: 0;
      color: #4f6475;
      line-height: 1.5;
      max-width: 72ch;
    }
    .komodo-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
    }
    .komodo-metric {
      padding: 14px;
      border-radius: 12px;
      background: rgba(22, 50, 74, 0.04);
      border: 1px solid rgba(22, 50, 74, 0.08);
    }
    .komodo-metric-label {
      display: block;
      margin-bottom: 6px;
      font-size: 11px;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #5d7384;
    }
    .komodo-metric-value {
      font-size: 18px;
      line-height: 1.3;
      color: #172b3a;
      word-break: break-word;
    }
    .komodo-status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 13px;
    }
    .komodo-status-badge::before {
      content: "";
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: currentColor;
    }
    .komodo-status-badge.running {
      color: #146c2e;
      background: rgba(25, 135, 84, 0.13);
      border: 1px solid rgba(25, 135, 84, 0.24);
    }
    .komodo-status-badge.stopped {
      color: #8a2f21;
      background: rgba(192, 57, 43, 0.12);
      border: 1px solid rgba(192, 57, 43, 0.24);
    }
    .komodo-actions,
    .komodo-links {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .komodo-section-title {
      margin: 0 0 12px;
      font-size: 18px;
      color: #17364f;
    }
    .komodo-section-copy {
      margin: 0 0 14px;
      color: #516879;
      line-height: 1.55;
    }
    .komodo-message {
      white-space: pre-wrap;
      padding: 12px 14px;
      border-radius: 12px;
      margin: 0 0 18px;
      line-height: 1.45;
    }
    .komodo-message.success {
      background: rgba(25, 135, 84, 0.13);
      border: 1px solid rgba(25, 135, 84, 0.24);
      color: #175b2f;
    }
    .komodo-message.error {
      background: rgba(192, 57, 43, 0.12);
      border: 1px solid rgba(192, 57, 43, 0.24);
      color: #7e2d21;
    }
    .komodo-checklist,
    .komodo-notes {
      margin: 0;
      padding-left: 18px;
      line-height: 1.55;
      color: #425767;
    }
    .komodo-checklist li,
    .komodo-notes li {
      margin-bottom: 8px;
    }
    .komodo-table {
      width: 100%;
      border-collapse: collapse;
    }
    .komodo-table th,
    .komodo-table td {
      text-align: left;
      padding: 11px 0;
      border-bottom: 1px solid rgba(22, 50, 74, 0.1);
      vertical-align: top;
    }
    .komodo-table th {
      width: 220px;
      font-weight: 700;
      color: #27465d;
    }
    .komodo-code,
    .komodo-pubkey {
      width: 100%;
      box-sizing: border-box;
      font-family: Menlo, Monaco, Consolas, monospace;
      font-size: 12px;
      line-height: 1.45;
      border: 1px solid rgba(22, 50, 74, 0.14);
      border-radius: 10px;
      background: rgba(248, 250, 252, 0.95);
      color: #17364f;
      padding: 12px;
    }
    .komodo-pubkey {
      min-height: 92px;
      resize: vertical;
    }
    .komodo-form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 14px 18px;
    }
    .komodo-field {
      display: grid;
      gap: 6px;
    }
    .komodo-field label {
      font-weight: 700;
      color: #214259;
    }
    .komodo-field small {
      color: #627787;
      line-height: 1.45;
    }
    .komodo-field input[type="text"],
    .komodo-field input[type="password"],
    .komodo-field select {
      width: 100%;
      box-sizing: border-box;
    }
    .komodo-form-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-top: 18px;
    }
    .komodo-inline-note {
      color: #617586;
      font-size: 13px;
    }
    .komodo-empty {
      padding: 18px;
      border: 1px dashed rgba(22, 50, 74, 0.25);
      border-radius: 12px;
      color: #5e7283;
    }
    @media (max-width: 960px) {
      .komodo-hero {
        grid-template-columns: 1fr;
      }
      .komodo-table th,
      .komodo-table td {
        display: block;
        width: 100%;
      }
      .komodo-table th {
        border-bottom: 0;
        padding-bottom: 2px;
      }
      .komodo-table td {
        padding-top: 0;
      }
    }
    </style>
    <?php
}

function renderOverview(array $cfg, array $status, string $message, string $messageType): void
{
    $running = isRunning($status);
    $serviceEnabled = isServiceEnabled($cfg, $status);
    $coreAddress = firstNonEmpty($cfg['PERIPHERY_CORE_ADDRESS'] ?? null, $status['core_address'] ?? null, 'Not set');
    $connectAs = firstNonEmpty($cfg['PERIPHERY_CONNECT_AS'] ?? null, $status['connect_as'] ?? null, 'Not set');
    $rootDirectory = firstNonEmpty($cfg['PERIPHERY_ROOT_DIRECTORY'] ?? null, $status['root_directory'] ?? null, DEFAULT_ROOT_DIRECTORY);
    $needsCore = trim((string) ($cfg['PERIPHERY_CORE_ADDRESS'] ?? '')) === '';
    $needsName = trim((string) ($cfg['PERIPHERY_CONNECT_AS'] ?? '')) === '';
    $needsOnboarding = !$running && trim((string) ($cfg['PERIPHERY_ONBOARDING_KEY'] ?? '')) === '';
    ?>
    <?php if ($message !== ''): ?>
      <div class="komodo-message <?= $messageType === 'success' ? 'success' : 'error'; ?>"><?= e($message); ?></div>
    <?php endif; ?>

    <div class="komodo-stack">
      <section class="komodo-card komodo-hero">
        <div>
          <h2 class="komodo-title">Komodo Periphery on this Unraid host</h2>
          <p class="komodo-subtitle">This plugin runs the Periphery agent directly on the host, keeps its identity on the flash drive, and connects outbound to your Komodo Core.</p>
        </div>
        <div>
          <span class="komodo-status-badge <?= $running ? 'running' : 'stopped'; ?>">
            <?= $running ? 'Service running' : 'Service stopped'; ?>
          </span>
          <div style="margin-top:10px" class="komodo-inline-note">
            Autostart: <strong><?= $serviceEnabled ? 'Enabled' : 'Disabled'; ?></strong><br>
            Plugin version: <strong><?= e($status['plugin_version'] ?? 'unknown'); ?></strong>
          </div>
        </div>
      </section>

      <section class="komodo-grid">
        <?= renderMetric('Core address', $coreAddress); ?>
        <?= renderMetric('Connect as', $connectAs); ?>
        <?= renderMetric('Root directory', $rootDirectory); ?>
        <?= renderMetric('Public key file', firstNonEmpty($status['public_key_file'] ?? null, DEFAULT_PUBLIC_KEY_FILE)); ?>
      </section>

      <section class="komodo-card">
        <h3 class="komodo-section-title">What still needs attention?</h3>
        <ul class="komodo-checklist">
          <li><?= $needsCore ? 'Set `PERIPHERY_CORE_ADDRESS` in Settings.' : 'Core address is configured.'; ?></li>
          <li><?= $needsName ? 'Set `PERIPHERY_CONNECT_AS` in Settings.' : 'Server identity is configured.'; ?></li>
          <li><?= !$serviceEnabled ? 'Enable autostart if this host should reconnect automatically after boot.' : 'Autostart is enabled.'; ?></li>
          <li><?= $needsOnboarding ? 'If this is a first-time connection, add an onboarding key before starting.' : 'Onboarding key is optional and only needed for first-time enrollment.'; ?></li>
        </ul>
      </section>

    </div>
    <?php
}

function renderSettings(array $cfg, array $status): void
{
    ?>
    <div class="komodo-stack">
      <section class="komodo-card">
        <h3 class="komodo-section-title">Connection</h3>
        <p class="komodo-section-copy">These values define which Komodo Core this host talks to and which server identity it should use there.</p>
        <form method="POST" action="/update.php" target="progressFrame">
          <input type="hidden" name="#file" value="komodo-periphery/komodo-periphery.cfg">

          <div class="komodo-form-grid">
            <?php renderField('Service Enabled', 'SERVICE_ENABLED', renderSelect('SERVICE_ENABLED', [
                'no' => 'Disabled',
                'yes' => 'Enabled',
            ], (string) ($cfg['SERVICE_ENABLED'] ?? 'no')), 'Start after install, update, and boot.'); ?>

            <?php renderField('PERIPHERY_CORE_ADDRESS', 'PERIPHERY_CORE_ADDRESS', renderInput('text', 'PERIPHERY_CORE_ADDRESS', (string) ($cfg['PERIPHERY_CORE_ADDRESS'] ?? '')), 'Example: https://komodo.example.com'); ?>

            <?php renderField('PERIPHERY_CONNECT_AS', 'PERIPHERY_CONNECT_AS', renderInput('text', 'PERIPHERY_CONNECT_AS', (string) ($cfg['PERIPHERY_CONNECT_AS'] ?? '')), 'Exact Komodo server name or ID for this Unraid host.'); ?>

            <?php renderField('PERIPHERY_ONBOARDING_KEY', 'PERIPHERY_ONBOARDING_KEY', renderInput('password', 'PERIPHERY_ONBOARDING_KEY', (string) ($cfg['PERIPHERY_ONBOARDING_KEY'] ?? '')), 'Only needed for first-time enrollment. Leave empty for existing servers.'); ?>
          </div>

          <section class="komodo-card" style="margin-top:18px; margin-bottom:0;">
            <h3 class="komodo-section-title">Runtime</h3>
            <p class="komodo-section-copy">These values influence where Periphery stores active work and how much host access it exposes.</p>
            <div class="komodo-form-grid">
              <?php renderField('PERIPHERY_ROOT_DIRECTORY', 'PERIPHERY_ROOT_DIRECTORY', renderInput('text', 'PERIPHERY_ROOT_DIRECTORY', (string) ($cfg['PERIPHERY_ROOT_DIRECTORY'] ?? DEFAULT_ROOT_DIRECTORY)), 'Use a cache or appdata path for sustained repo, stack, or build activity.'); ?>

              <?php renderField('PERIPHERY_LOG_LEVEL', 'PERIPHERY_LOG_LEVEL', renderSelect('PERIPHERY_LOG_LEVEL', [
                  'error' => 'ERROR',
                  'warn' => 'WARN',
                  'info' => 'INFO',
                  'debug' => 'DEBUG',
                  'trace' => 'TRACE',
              ], (string) ($cfg['PERIPHERY_LOG_LEVEL'] ?? 'info')), 'INFO is a good default for normal operation.'); ?>

              <?php renderField('Disable Terminals', 'PERIPHERY_DISABLE_TERMINALS', renderSelect('PERIPHERY_DISABLE_TERMINALS', [
                  'no' => 'No',
                  'yes' => 'Yes',
              ], (string) ($cfg['PERIPHERY_DISABLE_TERMINALS'] ?? 'no')), 'Disable shell terminals exposed through Periphery if you want a tighter host profile.'); ?>

              <?php renderField('Disable Container Terminals', 'PERIPHERY_DISABLE_CONTAINER_TERMINALS', renderSelect('PERIPHERY_DISABLE_CONTAINER_TERMINALS', [
                  'no' => 'No',
                  'yes' => 'Yes',
              ], (string) ($cfg['PERIPHERY_DISABLE_CONTAINER_TERMINALS'] ?? 'no')), 'Disable container shell access while still allowing other Periphery features.'); ?>
            </div>
          </section>

          <section class="komodo-card" style="margin-top:18px; margin-bottom:0;">
            <h3 class="komodo-section-title">Advanced</h3>
            <p class="komodo-section-copy">Only change these if your Komodo Core setup or mount visibility needs it.</p>
            <div class="komodo-form-grid">
              <?php renderField('PERIPHERY_CORE_PUBLIC_KEYS', 'PERIPHERY_CORE_PUBLIC_KEYS', renderInput('text', 'PERIPHERY_CORE_PUBLIC_KEYS', (string) ($cfg['PERIPHERY_CORE_PUBLIC_KEYS'] ?? '')), 'Optional. Example: file:/boot/config/komodo/periphery-agent/keys/core.pub'); ?>

              <?php renderField('Include Disk Mounts', 'PERIPHERY_INCLUDE_DISK_MOUNTS', renderInput('text', 'PERIPHERY_INCLUDE_DISK_MOUNTS', (string) ($cfg['PERIPHERY_INCLUDE_DISK_MOUNTS'] ?? '')), 'Comma-separated include filter for visible mounts.'); ?>

              <?php renderField('Exclude Disk Mounts', 'PERIPHERY_EXCLUDE_DISK_MOUNTS', renderInput('text', 'PERIPHERY_EXCLUDE_DISK_MOUNTS', (string) ($cfg['PERIPHERY_EXCLUDE_DISK_MOUNTS'] ?? '')), 'Comma-separated exclude filter for visible mounts.'); ?>
            </div>
          </section>

          <div class="komodo-form-actions">
            <input type="submit" value="_(Apply)_">
            <input type="button" value="_(Done)_" onclick="done()">
            <span class="komodo-inline-note">Current runtime config: <?= e($status['runtime_config_file'] ?? DEFAULT_RUNTIME_CONFIG); ?></span>
          </div>
        </form>
      </section>
    </div>
    <?php
}

function renderStatus(array $cfg, array $status, string $message, string $messageType, $var): void
{
    $running = isRunning($status);
    $publicKey = (string) ($status['public_key'] ?? '');
    ?>
    <?php if ($message !== ''): ?>
      <div class="komodo-message <?= $messageType === 'success' ? 'success' : 'error'; ?>"><?= e($message); ?></div>
    <?php endif; ?>

    <div class="komodo-stack">
      <section class="komodo-card">
        <h3 class="komodo-section-title">Service control</h3>
        <p class="komodo-section-copy">Use this page to start, stop, or restart the host service and to verify the exact runtime files currently in use.</p>
        <div class="komodo-actions" style="margin-bottom:14px;">
          <span class="komodo-status-badge <?= $running ? 'running' : 'stopped'; ?>">
            <?= $running ? 'Running' : 'Stopped'; ?>
          </span>
        </div>
        <form method="POST" class="komodo-actions">
          <input type="hidden" name="csrf_token" value="<?= e(is_array($var) ? ($var['csrf_token'] ?? '') : ''); ?>">
          <?php if ($running): ?>
            <input type="submit" name="service_action" value="stop">
            <input type="submit" name="service_action" value="restart">
          <?php else: ?>
            <input type="submit" name="service_action" value="start">
          <?php endif; ?>
          <input type="button" value="Reload" onclick="location.reload();">
        </form>
      </section>

      <section class="komodo-card">
        <h3 class="komodo-section-title">Live runtime details</h3>
        <table class="komodo-table">
          <tr>
            <th>PID</th>
            <td><?= e(firstNonEmpty($status['pid'] ?? null, 'Not running')); ?></td>
          </tr>
          <tr>
            <th>Core address</th>
            <td><?= e(firstNonEmpty($status['core_address'] ?? null, $cfg['PERIPHERY_CORE_ADDRESS'] ?? null, 'Not set')); ?></td>
          </tr>
          <tr>
            <th>Connect as</th>
            <td><?= e(firstNonEmpty($status['connect_as'] ?? null, $cfg['PERIPHERY_CONNECT_AS'] ?? null, 'Not set')); ?></td>
          </tr>
          <tr>
            <th>Root directory</th>
            <td><?= e(firstNonEmpty($status['root_directory'] ?? null, $cfg['PERIPHERY_ROOT_DIRECTORY'] ?? null, DEFAULT_ROOT_DIRECTORY)); ?></td>
          </tr>
          <tr>
            <th>Runtime config</th>
            <td><div class="komodo-code"><?= e($status['runtime_config_file'] ?? DEFAULT_RUNTIME_CONFIG); ?></div></td>
          </tr>
          <tr>
            <th>Log file</th>
            <td><div class="komodo-code"><?= e($status['log_file'] ?? DEFAULT_LOG_FILE); ?></div></td>
          </tr>
          <tr>
            <th>Public key file</th>
            <td><div class="komodo-code"><?= e($status['public_key_file'] ?? DEFAULT_PUBLIC_KEY_FILE); ?></div></td>
          </tr>
        </table>
      </section>

      <section class="komodo-card">
        <h3 class="komodo-section-title">Public key</h3>
        <p class="komodo-section-copy">Use this key if you want to approve the host explicitly in Komodo Core instead of relying on an onboarding key.</p>
        <textarea class="komodo-pubkey" readonly><?= e($publicKey !== '' ? $publicKey : 'No public key available yet. Start the service once to generate or load the existing key pair.'); ?></textarea>
      </section>
    </div>
    <?php
}

function renderInfo(array $cfg, array $status): void
{
    $coreAddress = firstNonEmpty($cfg['PERIPHERY_CORE_ADDRESS'] ?? null, $status['core_address'] ?? null, 'Not set');
    $connectAs = firstNonEmpty($cfg['PERIPHERY_CONNECT_AS'] ?? null, $status['connect_as'] ?? null, 'Not set');
    ?>
    <div class="komodo-stack">
      <section class="komodo-card">
        <h3 class="komodo-section-title">How onboarding works</h3>
        <ol class="komodo-checklist">
          <li>Decide which Komodo Core this host should connect to.</li>
          <li>Set <code>PERIPHERY_CORE_ADDRESS</code> and <code>PERIPHERY_CONNECT_AS</code> in Settings.</li>
          <li>If the server already exists in Core, leave the onboarding key empty.</li>
          <li>If this is a first-time enrollment, generate an onboarding key in Core and paste it into <code>PERIPHERY_ONBOARDING_KEY</code>.</li>
          <li>Start the service from the Status tab.</li>
          <li>If you prefer manual approval, copy the public key from the Status tab and approve that key in Core.</li>
        </ol>
      </section>

      <section class="komodo-card">
        <h3 class="komodo-section-title">Current intended identity</h3>
        <table class="komodo-table">
          <tr>
            <th>Core address</th>
            <td><?= e($coreAddress); ?></td>
          </tr>
          <tr>
            <th>Connect as</th>
            <td><?= e($connectAs); ?></td>
          </tr>
          <tr>
            <th>Persistent state</th>
            <td><div class="komodo-code">/boot/config/komodo/periphery-agent</div></td>
          </tr>
          <tr>
            <th>Recommended active work path</th>
            <td><div class="komodo-code">/mnt/cache/appdata/komodo-periphery</div></td>
          </tr>
        </table>
      </section>

      <section class="komodo-card">
        <h3 class="komodo-section-title">Useful links</h3>
        <div class="komodo-links" style="margin-bottom:14px;">
          <a href="https://github.com/moghtech/komodo" target="_blank" rel="noopener noreferrer"><input type="button" value="Komodo GitHub"></a>
          <a href="https://komo.do/docs" target="_blank" rel="noopener noreferrer"><input type="button" value="Documentation"></a>
          <a href="https://komo.do/docs/setup/connect-servers" target="_blank" rel="noopener noreferrer"><input type="button" value="Connect Servers Guide"></a>
        </div>
        <ul class="komodo-notes">
          <li>This plugin runs Periphery as a native Unraid service, not as a Docker container.</li>
          <li>Keys survive updates because they live outside the bundle on the flash drive.</li>
          <li>For heavier activity, move <code>PERIPHERY_ROOT_DIRECTORY</code> off the flash drive onto cache or appdata.</li>
        </ul>
      </section>
    </div>
    <?php
}

function renderMetric(string $label, string $value): string
{
    return '<div class="komodo-metric"><span class="komodo-metric-label">' . e($label) . '</span><div class="komodo-metric-value">' . e($value) . '</div></div>';
}

function renderField(string $label, string $name, string $control, string $help): void
{
    echo '<div class="komodo-field">';
    echo '<label for="' . e($name) . '">' . e($label) . '</label>';
    echo $control;
    echo '<small>' . e($help) . '</small>';
    echo '</div>';
}

function renderInput(string $type, string $name, string $value): string
{
    return '<input id="' . e($name) . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '">';
}

function renderSelect(string $name, array $options, string $selected): string
{
    $html = '<select id="' . e($name) . '" name="' . e($name) . '">';
    foreach ($options as $value => $label) {
        $isSelected = $value === $selected ? ' selected' : '';
        $html .= '<option value="' . e((string) $value) . '"' . $isSelected . '>' . e((string) $label) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function isRunning(array $status): bool
{
    return ($status['running'] ?? 'no') === 'yes';
}

function isServiceEnabled(array $cfg, array $status): bool
{
    $value = (string) firstNonEmpty($cfg['SERVICE_ENABLED'] ?? null, $status['service_enabled'] ?? null, 'no');
    return $value === 'yes';
}

function firstNonEmpty(...$values): string
{
    foreach ($values as $value) {
        if (!is_string($value)) {
            continue;
        }
        if (trim($value) !== '') {
            return $value;
        }
    }

    return '';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
