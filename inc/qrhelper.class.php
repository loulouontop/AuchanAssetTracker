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

        // Fallback: Setup → General → URL of the application
        $configured = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/');
        if ($configured !== '' && !str_contains($configured, '/plugins/')) {
            return $configured;
        }

        return $root_doc;
    }

    /**
     * Internal scan URL (GLPI front — requires login on the LAN).
     */
    public static function getScanUrl(string $token): string
    {
        $token = trim($token);
        $path = '/plugins/' . plugin_auchanassettracker_dir()
            . '/front/container.qrview.php?t=' . urlencode($token);

        $base = self::getGlpiBaseUrl();
        if ($base === '') {
            return rtrim(plugin_auchanassettracker_web_dir(true), '/')
                . '/front/container.qrview.php?t=' . urlencode($token);
        }

        return rtrim($base, '/') . $path;
    }

    /** @deprecated Use getScanUrl() */
    public static function getPublicUrl(string $token): string
    {
        return self::getScanUrl($token);
    }

    /**
     * PNG QR as a data URI (no second HTTP request — works in label + form).
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

        try {
            ob_start();
            QRcode::png($content, false, QR_ECLEVEL_M, 6, 2);
            $png = ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            return '';
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
        $name = Html::entities_deep($container['name'] ?? '');
        $code = Html::entities_deep($container['code'] ?? '');
        $token = (string) ($container['qr_token'] ?? '');
        $url = self::getScanUrl($token);
        $img = self::getQrPngDataUri($url);
        $loc = Dropdown::getDropdownName('glpi_locations', (int) ($container['locations_id'] ?? 0));

        $img_html = $img !== ''
            ? '<img src="' . Html::entities_deep($img) . '" alt="QR" width="180" height="180"/>'
            : '<p class="missing">' . Html::entities_deep(__(
                'QR image could not be generated (PHP GD extension required).',
                'auchanassettracker'
            )) . '</p>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $code . '</title>
<style>
body{font-family:Arial,sans-serif;text-align:center;padding:24px}
.box{border:2px solid #222;padding:20px;display:inline-block;min-width:280px}
h1{font-size:18px;margin:0 0 8px}
.code{font-size:22px;font-weight:bold;letter-spacing:1px}
.loc{color:#555;margin:8px 0}
img{width:180px;height:180px;margin:12px 0}
.url{font-size:10px;word-break:break-all;color:#333}
.missing{color:#a00;font-size:12px;margin:12px 0}
@media print{button{display:none}}
</style></head><body>
<div class="box">
<h1>' . $name . '</h1>
<div class="code">' . $code . '</div>
<div class="loc">' . Html::entities_deep($loc) . '</div>
' . $img_html . '
<div class="url">' . Html::entities_deep($url) . '</div>
</div>
<p><button onclick="window.print()">' . __('Print', 'auchanassettracker') . '</button></p>
</body></html>';
    }
}
