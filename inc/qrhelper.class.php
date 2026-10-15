<?php

/**
 * QR helpers: absolute scan URL + printable HTML label (browser print → PDF).
 */
class PluginAuchanassettrackerQrhelper
{
    /**
     * Absolute GLPI root (scheme + host + root_doc).
     * Prefer the live request host so QR links match how the user opens GLPI
     * (avoids a misconfigured url_base like http://localhost).
     */
    public static function getGlpiBaseUrl(): string
    {
        global $CFG_GLPI;

        $root_doc = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');

        $host = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
        } elseif (!empty($_SERVER['HTTP_HOST'])) {
            $host = (string) $_SERVER['HTTP_HOST'];
        }

        if ($host !== '') {
            $https = false;
            if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                $proto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
                $https = ($proto === 'https');
            } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $https = true;
            } elseif ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
                $https = true;
            }

            return ($https ? 'https' : 'http') . '://' . $host . $root_doc;
        }

        $configured = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/');
        if ($configured !== '' && !str_contains($configured, '/plugins/')) {
            return $configured;
        }

        return $root_doc;
    }

    /**
     * Public scan URL.
     * GLPI 11 exposes plugin public/ files at /plugins/<dir>/qr.php (no "/public/" in URL).
     * That path is served without the authenticated front controller.
     */
    public static function getScanUrl(string $token): string
    {
        $token = trim($token);
        // IMPORTANT: do not include "/public/" — that route goes through GLPI login.
        $path = '/plugins/' . plugin_auchanassettracker_dir() . '/qr.php?t=' . urlencode($token);

        $base = self::getGlpiBaseUrl();
        if ($base === '') {
            return $path;
        }

        return rtrim($base, '/') . $path;
    }

    /** @deprecated Use getScanUrl() */
    public static function getPublicUrl(string $token): string
    {
        return self::getScanUrl($token);
    }

    /**
     * PNG QR as a data URI.
     * Writes to a temp file so phpqrcode does NOT send Content-Type: image/png
     * (that was breaking the PDF label HTML page).
     */
    public static function getQrPngDataUri(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (!function_exists('imagecreate') || !function_exists('imagepng')) {
            return '';
        }

        $lib = dirname(__DIR__) . '/inc/phpqrcode/qrlib.php';
        if (!is_readable($lib)) {
            return '';
        }
        require_once $lib;

        $tmp = tempnam(sys_get_temp_dir(), 'aatqr');
        if ($tmp === false) {
            return '';
        }
        // phpqrcode appends nothing; ensure .png path for clarity
        $png_path = $tmp . '.png';
        @unlink($tmp);

        try {
            QRcode::png($content, $png_path, QR_ECLEVEL_M, 6, 2);
            if (!is_readable($png_path)) {
                return '';
            }
            $png = file_get_contents($png_path);
        } catch (Throwable $e) {
            $png = false;
        } finally {
            if (is_file($png_path)) {
                @unlink($png_path);
            }
        }

        if ($png === false || $png === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Render a printable label (HTML that browsers can print to PDF).
     */
    public static function renderLabelHtml(array $container): string
    {
        $raw_name = trim((string) ($container['name'] ?? ''));
        if ($raw_name === '') {
            $raw_name = __('Physical container', 'auchanassettracker');
        }
        $raw_code = trim((string) ($container['code'] ?? ''));
        $name = Html::entities_deep($raw_name);
        $code = Html::entities_deep($raw_code !== '' ? $raw_code : '—');
        $token = (string) ($container['qr_token'] ?? '');
        $url = self::getScanUrl($token);
        $img = self::getQrPngDataUri($url);
        $loc = Dropdown::getDropdownName('glpi_locations', (int) ($container['locations_id'] ?? 0));
        if ($loc === '' || $loc === '&nbsp;' || $loc === '-1') {
            $loc = '—';
        }
        $title = Html::entities_deep(__('Physical container', 'auchanassettracker')) . ' — ' . $name;

        // Never run Html::entities_deep on the data URI (can break base64).
        $img_html = $img !== ''
            ? '<img src="' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '" alt="QR" width="180" height="180"/>'
            : '<p class="missing">' . Html::entities_deep(__(
                'QR image could not be generated (PHP GD extension required).',
                'auchanassettracker'
            )) . '</p>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $code . '</title>
<style>
html,body{background:#fff;color:#111;margin:0}
body{font-family:Arial,sans-serif;text-align:center;padding:24px}
.box{border:2px solid #222;padding:20px;display:inline-block;min-width:280px;background:#fff}
.title{font-size:18px;font-weight:700;margin:0 0 6px;line-height:1.3}
.code{font-size:14px;font-weight:600;letter-spacing:0.02em;color:#334155;margin:0 0 4px;
font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
.loc{font-size:13px;color:#667382;margin:0 0 12px}
img{width:180px;height:180px;margin:8px 0 12px;background:#fff}
.url{font-size:10px;word-break:break-all;color:#333}
.missing{color:#a00;font-size:12px;margin:12px 0}
@media print{button{display:none}}
</style></head><body>
<div class="box">
<div class="title">' . $title . '</div>
<div class="code">' . $code . '</div>
<div class="loc">' . Html::entities_deep($loc) . '</div>
' . $img_html . '
<div class="url">' . Html::entities_deep($url) . '</div>
</div>
<p><button type="button" onclick="window.print()">' . __('Print', 'auchanassettracker') . '</button></p>
</body></html>';
    }
}
