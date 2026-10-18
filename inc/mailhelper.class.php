<?php

/**
 * Plugin event notices + HTML emails (not GLPI native Notification / NotificationTemplate).
 *
 * Config thresholds drive Active alerts only; these emails fire on allocate / reject events.
 */
class PluginAuchanassettrackerMailhelper
{
    public static function notifyAllocationPending(
        int $users_id,
        int $equipment_id,
        int $allocator_id = 0
    ): void {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Equipment awaiting confirmation', 'auchanassettracker');
        $name = trim((string) ($eq->fields['name'] ?? ''));
        if ($name === '') {
            $name = '#' . $equipment_id;
        }
        $serial = trim((string) ($eq->fields['serial'] ?? ''));
        $type = (string) ($eq->fields['itemtype'] ?? '');
        $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;

        $recipient_name = self::formatUserDisplayName($users_id);
        if ($allocator_id <= 0) {
            $allocator_id = (int) Session::getLoginUserID();
        }
        $tech_name = self::formatUserDisplayName($allocator_id);
        if ($tech_name === '') {
            $tech_name = __('a technician', 'auchanassettracker');
        }

        $greeting = $recipient_name !== ''
            ? sprintf(__('Hello %s,', 'auchanassettracker'), $recipient_name)
            : __('Hello,', 'auchanassettracker');
        $intro = sprintf(
            __(
                '%s has assigned equipment to you. It is awaiting your confirmation in GLPI. Please confirm or decline receipt of this equipment:',
                'auchanassettracker'
            ),
            $tech_name
        );
        $body = $intro . "\n" . $name;
        $link = self::absolutePluginUrl('/front/confirm.php');

        self::send($users_id, $subject, $body, $link, [
            'badge'    => __('Confirm receipt', 'auchanassettracker'),
            'greeting' => $greeting,
            'intro'    => $intro,
            'cta'      => __('Confirm or decline', 'auchanassettracker'),
            'rows'     => [
                [__('Equipment'), $name],
                [__('Type'), $type_label],
                [__('Serial number'), $serial],
                [__('Technician', 'auchanassettracker'), $tech_name],
            ],
        ]);
    }

    /**
     * One event notice for the allocator (not duplicated in Active alerts when shelf restored).
     */
    public static function notifyAllocationRejected(
        int $users_id,
        int $equipment_id,
        bool $container_restored = true,
        string $comment = ''
    ): void {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Allocation rejected by user', 'auchanassettracker');
        $name = trim((string) ($eq->fields['name'] ?? ''));
        if ($name === '') {
            $name = '#' . $equipment_id;
        }
        $serial = trim((string) ($eq->fields['serial'] ?? ''));
        $recipient_name = self::formatUserDisplayName($users_id);
        $greeting = $recipient_name !== ''
            ? sprintf(__('Hello %s,', 'auchanassettracker'), $recipient_name)
            : __('Hello,', 'auchanassettracker');

        if ($container_restored) {
            $body = sprintf(
                __('User reported they did not receive: %s. Item returned to its previous container.', 'auchanassettracker'),
                $name
            );
            $intro = __(
                'The recipient reported they did not receive this equipment. It was returned to its previous container.',
                'auchanassettracker'
            );
        } else {
            $body = sprintf(
                __('User reported they did not receive: %s. Previous container unavailable — assign a container (see Active alerts).', 'auchanassettracker'),
                $name
            );
            $intro = __(
                'The recipient reported they did not receive this equipment. Previous container unavailable — assign a container (see Active alerts).',
                'auchanassettracker'
            );
        }
        $comment = trim($comment);
        if ($comment !== '') {
            $body .= "\n" . __('Comment') . ': ' . $comment;
        }
        $link = self::absolutePluginUrl('/front/equipment.form.php?id=' . $equipment_id);
        $rows = [
            [__('Equipment'), $name],
            [__('Serial number'), $serial],
        ];
        if ($comment !== '') {
            $rows[] = [__('Comment'), $comment];
        }
        self::send($users_id, $subject, $body, $link, [
            'badge'    => __('Allocation rejected by user', 'auchanassettracker'),
            'greeting' => $greeting,
            'intro'    => $intro,
            'cta'      => __('Open equipment', 'auchanassettracker'),
            'rows'     => $rows,
        ]);
    }

    public static function notifyTransferRejected(int $users_id, int $transfer_id, string $comment = ''): void
    {
        $subject = __('Transfer rejected', 'auchanassettracker');
        $body = sprintf(
            __('Transfer #%d was rejected by the destination location.', 'auchanassettracker'),
            $transfer_id
        );
        $comment = trim($comment);
        if ($comment !== '') {
            $body .= "\n" . __('Comment') . ': ' . $comment;
        }
        $recipient_name = self::formatUserDisplayName($users_id);
        $greeting = $recipient_name !== ''
            ? sprintf(__('Hello %s,', 'auchanassettracker'), $recipient_name)
            : __('Hello,', 'auchanassettracker');
        $link = self::absolutePluginUrl('/front/transfer.form.php?id=' . $transfer_id);
        $rows = [
            [__('Transfer', 'auchanassettracker'), '#' . $transfer_id],
        ];
        if ($comment !== '') {
            $rows[] = [__('Comment'), $comment];
        }
        self::send($users_id, $subject, $body, $link, [
            'badge'    => __('Transfer rejected', 'auchanassettracker'),
            'greeting' => $greeting,
            'intro'    => __(
                'A transfer was rejected by the destination location.',
                'auchanassettracker'
            ),
            'cta'      => __('Open transfer', 'auchanassettracker'),
            'rows'     => $rows,
        ]);
    }

    /**
     * Absolute plugin URL (scheme + host + GLPI root + plugin path).
     */
    public static function absolutePluginUrl(string $path): string
    {
        $base = rtrim(PluginAuchanassettrackerQrhelper::getGlpiBaseUrl(), '/');
        $plugin = '/' . trim(plugin_auchanassettracker_web_dir(false), '/');
        $path = '/' . ltrim($path, '/');
        return $base . $plugin . $path;
    }

    public static function formatUserDisplayName(int $users_id): string
    {
        if ($users_id <= 0) {
            return '';
        }
        $user = new User();
        if (!$user->getFromDB($users_id)) {
            return '';
        }
        $first = trim((string) ($user->fields['firstname'] ?? ''));
        $last = trim((string) ($user->fields['realname'] ?? ''));
        $full = trim($first . ' ' . $last);
        if ($full !== '') {
            return $full;
        }
        $login = trim((string) ($user->fields['name'] ?? ''));
        return $login;
    }

    /**
     * Sample allocation-pending email HTML for config preview (final UI).
     */
    public static function renderPreviewHtml(): string
    {
        $subject = __('Equipment awaiting confirmation', 'auchanassettracker');
        $tech = self::formatUserDisplayName((int) Session::getLoginUserID());
        if ($tech === '') {
            $tech = __('Technician', 'auchanassettracker');
        }
        $greeting = sprintf(__('Hello %s,', 'auchanassettracker'), __('Recipient', 'auchanassettracker'));
        $intro = sprintf(
            __(
                '%s has assigned equipment to you. It is awaiting your confirmation in GLPI. Please confirm or decline receipt of this equipment:',
                'auchanassettracker'
            ),
            $tech
        );
        $link = self::absolutePluginUrl('/front/confirm.php');

        return self::renderHtmlEmail($subject, $intro, $link, [
            'badge'    => __('Confirm receipt', 'auchanassettracker'),
            'greeting' => $greeting,
            'intro'    => $intro,
            'cta'      => __('Confirm or decline', 'auchanassettracker'),
            'rows'     => [
                [__('Equipment'), 'Laptop-Demo-01'],
                [__('Type'), __('Computer')],
                [__('Serial number'), 'SN-DEMO-12345'],
                [__('Technician', 'auchanassettracker'), $tech],
            ],
        ]);
    }

    /**
     * @param array{
     *   badge?: string,
     *   greeting?: string,
     *   intro?: string,
     *   cta?: string,
     *   rows?: list<array{0: string, 1: string}>
     * } $template
     */
    public static function send(
        int $users_id,
        string $subject,
        string $body,
        string $link = '',
        array $template = []
    ): void {
        if ($users_id <= 0) {
            return;
        }

        // Event inbox for the recipient (reject / pending). Not used for overdue lists.
        PluginAuchanassettrackerNotice::addForUser(
            $users_id,
            $subject . ' — ' . $body,
            $link
        );

        try {
            $user = new User();
            if (!$user->getFromDB($users_id)) {
                return;
            }
            $email = $user->getDefaultEmail();
            if (!$email) {
                $login = PluginAuchanassettrackerPluginlog::userRef($users_id);
                PluginAuchanassettrackerPluginlog::info(
                    "No email for user $login — in-app notice stored. Subject: $subject"
                );
                return;
            }

            $html = self::renderHtmlEmail($subject, $body, $link, $template);
            $plain = $body . ($link !== '' ? "\n\n" . $link : '');
            $mail_subject = '[' . __('Auchan Asset Tracker', 'auchanassettracker') . '] ' . $subject;

            if (!self::sendViaGlpiMailer($email, $mail_subject, $html, $plain)) {
                PluginAuchanassettrackerPluginlog::info(
                    "In-app notice stored for $email (mail not delivered) — $subject"
                );
            }
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'mail');
        }
    }

    /**
     * Send via GLPI 11 GLPIMailer (Symfony Mailer wrapper).
     */
    public static function sendViaGlpiMailer(
        string $email,
        string $subject,
        string $html,
        string $plain
    ): bool {
        global $CFG_GLPI;

        if (!class_exists('GLPIMailer') && !class_exists(\GLPIMailer::class)) {
            PluginAuchanassettrackerPluginlog::info(
                'GLPIMailer class not found — cannot send mail to ' . $email
            );
            return false;
        }

        try {
            $mmail = new GLPIMailer();
            $from = self::resolveMailFrom();
            if ($from['email'] === null || $from['email'] === '') {
                PluginAuchanassettrackerPluginlog::info(
                    'No From/Sender email in GLPI config (Setup → Notifications → Email followups:'
                    . ' Admin email / From email). Cannot send to ' . $email
                );
                return false;
            }

            if (method_exists($mmail, 'getEmail')) {
                $symfony = $mmail->getEmail();
                self::applyFromToSymfonyEmail($symfony, $from['email'], $from['name']);
                $sender = trim((string) ($CFG_GLPI['smtp_sender'] ?? ''));
                if ($sender === '') {
                    $sender = $from['email'];
                }
                if (method_exists($symfony, 'sender') && $sender !== '') {
                    $symfony->sender($sender);
                }
                $symfony->to($email);
                $symfony->subject($subject);
                $symfony->html($html);
                $symfony->text($plain);
            } else {
                if (method_exists($mmail, 'SetFrom') || method_exists($mmail, 'setFrom')) {
                    $mmail->SetFrom($from['email'], (string) ($from['name'] ?? ''));
                }
                $mmail->AddAddress($email);
                $mmail->Subject = $subject;
                $mmail->Body = $html;
                $mmail->AltBody = $plain;
            }

            $ok = (bool) $mmail->send();
            if (!$ok) {
                $err = method_exists($mmail, 'getError') ? (string) ($mmail->getError() ?? '') : '';
                PluginAuchanassettrackerPluginlog::info(
                    'GLPIMailer SMTP rejected mail for ' . $email
                    . ' (From=' . $from['email'] . ')'
                    . ($err !== '' ? (': ' . $err) : '')
                    . ' — check Setup → Notifications → Email followups (SMTP auth, From must be allowed to send).'
                );
            }
            return $ok;
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'mail');
            return false;
        }
    }

    /**
     * @return array{email: ?string, name: ?string}
     */
    public static function resolveMailFrom(): array
    {
        global $CFG_GLPI;

        $entity = null;
        if (isset($_SESSION['glpiactive_entity'])) {
            $entity = (int) $_SESSION['glpiactive_entity'];
        }

        if (class_exists('Config') && method_exists('Config', 'getEmailSender')) {
            $sender = Config::getEmailSender($entity, false);
            if (is_array($sender) && !empty($sender['email'])) {
                return [
                    'email' => (string) $sender['email'],
                    'name'  => isset($sender['name']) ? (string) $sender['name'] : null,
                ];
            }
        }

        foreach (['from_email', 'admin_email', 'noreply_email'] as $key) {
            $addr = trim((string) ($CFG_GLPI[$key] ?? ''));
            if ($addr !== '') {
                $name_key = $key . '_name';
                $name = trim((string) ($CFG_GLPI[$name_key] ?? ''));
                return [
                    'email' => $addr,
                    'name'  => $name !== '' ? $name : null,
                ];
            }
        }

        $smtp_sender = trim((string) ($CFG_GLPI['smtp_sender'] ?? ''));
        if ($smtp_sender !== '') {
            return ['email' => $smtp_sender, 'name' => null];
        }

        return ['email' => null, 'name' => null];
    }

    /**
     * @param object $symfony Symfony\Component\Mime\Email
     */
    private static function applyFromToSymfonyEmail(object $symfony, string $from, ?string $from_name): void
    {
        if (!method_exists($symfony, 'from')) {
            return;
        }
        if ($from_name !== null && $from_name !== ''
            && class_exists(\Symfony\Component\Mime\Address::class)
        ) {
            $symfony->from(new \Symfony\Component\Mime\Address($from, $from_name));
            return;
        }
        $symfony->from($from);
    }

    /**
     * Auchan HTML email — compact header, named greeting, one CTA with absolute link.
     * Empty table values stay blank (not "—"). Language follows GLPI/plugin translations.
     *
     * @param array{
     *   badge?: string,
     *   greeting?: string,
     *   intro?: string,
     *   cta?: string,
     *   rows?: list<array{0: string, 1: string}>
     * } $template
     */
    public static function renderHtmlEmail(
        string $subject,
        string $body,
        string $link = '',
        array $template = []
    ): string {
        $badge = (string) ($template['badge'] ?? __('Notification', 'auchanassettracker'));
        $greeting = (string) ($template['greeting'] ?? __('Hello,', 'auchanassettracker'));
        $intro = (string) ($template['intro'] ?? $body);
        $cta   = (string) ($template['cta'] ?? __('Open in GLPI', 'auchanassettracker'));
        /** @var list<array{0: string, 1: string}> $rows */
        $rows  = $template['rows'] ?? [];

        $lang = 'en';
        $glpi_lang = (string) ($_SESSION['glpilanguage'] ?? ($GLOBALS['CFG_GLPI']['language'] ?? 'en_GB'));
        if (stripos($glpi_lang, 'ro') === 0) {
            $lang = 'ro';
        }

        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $rows_html = '';
        $i = 0;
        foreach ($rows as $row) {
            $label = $esc((string) ($row[0] ?? ''));
            // Keep missing data blank (do not show "—").
            $raw_value = trim((string) ($row[1] ?? ''));
            if ($raw_value === '—' || $raw_value === '-') {
                $raw_value = '';
            }
            $value = $esc($raw_value);
            $bg = ($i % 2 === 0) ? '#FFFFFF' : '#FFF5F5';
            $rows_html .= '<tr>'
                . '<td style="padding:10px 14px;background:' . $bg
                . ';border-bottom:1px solid #F3D6D6;font-size:13px;color:#5C5C5C;width:38%;vertical-align:top;">'
                . $label . '</td>'
                . '<td style="padding:10px 14px;background:' . $bg
                . ';border-bottom:1px solid #F3D6D6;font-size:14px;color:#1A1A1A;font-weight:600;vertical-align:top;">'
                . $value . '</td>'
                . '</tr>';
            $i++;
        }
        if ($rows_html === '') {
            $rows_html = '<tr>'
                . '<td colspan="2" style="padding:12px 14px;background:#FFFFFF;font-size:14px;color:#1A1A1A;line-height:1.6;">'
                . nl2br($esc($body))
                . '</td></tr>';
        }

        // Single working CTA (absolute URL). No second “button” that only shows the raw link.
        $cta_html = '';
        $link = self::ensureAbsoluteUrl($link);
        if ($link !== '') {
            $href = $esc($link);
            $cta_html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0 0;">'
                . '<tr><td style="border-radius:6px;background:#E30613;">'
                . '<a href="' . $href . '" target="_blank" rel="noopener noreferrer"'
                . ' style="display:inline-block;padding:12px 24px;font-size:15px;font-weight:700;color:#FFFFFF;'
                . 'text-decoration:none;border-radius:6px;background:#E30613;">'
                . $esc($cta)
                . '</a></td></tr></table>';
        }

        $footer = $esc(__(
            'Automatic notification from GLPI — Auchan Asset Tracker.',
            'auchanassettracker'
        ));

        return '<!DOCTYPE html>
<html lang="' . $esc($lang) . '">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>' . $esc($subject) . '</title>
</head>
<body style="margin:0;padding:0;background:#F0F0F0;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F0F0F0;padding:12px 8px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
             style="max-width:620px;background:#FFFFFF;border-radius:8px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,0.08);">
        <tr><td style="background:#E30613;padding:14px 20px 12px;">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
              <td style="font-size:28px;letter-spacing:3px;font-weight:800;color:#FFFFFF;line-height:1;vertical-align:middle;">AUCHAN</td>
              <td align="right" style="vertical-align:middle;">
                <span style="display:inline-block;padding:3px 9px;border-radius:4px;background:rgba(255,255,255,0.2);font-size:11px;font-weight:700;color:#FFFFFF;">'
                  . $esc($badge) . '</span>
              </td>
            </tr>
          </table>
          <div style="margin-top:8px;font-size:15px;line-height:1.3;font-weight:600;color:#FFFFFF;">'
            . $esc($subject) . '</div>
        </td></tr>
        <tr><td style="padding:16px 20px 14px;">
          <p style="margin:0 0 6px;font-size:15px;line-height:1.45;color:#1A1A1A;font-weight:600;">'
            . $esc($greeting) . '</p>
          <p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#5C5C5C;">'
            . $esc($intro) . '</p>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                 style="border:1px solid #F3D6D6;border-radius:6px;overflow:hidden;">'
            . $rows_html . '</table>'
            . $cta_html . '
        </td></tr>
        <tr><td style="padding:12px 20px 14px;background:#FFF5F5;border-top:1px solid #F3D6D6;font-size:12px;line-height:1.4;color:#5C5C5C;">'
            . $footer . '
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
    }

    /**
     * Ensure href is absolute (scheme + host + path) for email clients.
     */
    public static function ensureAbsoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $base = rtrim(PluginAuchanassettrackerQrhelper::getGlpiBaseUrl(), '/');
        if (str_starts_with($url, '/')) {
            return $base . $url;
        }
        return $base . '/' . ltrim($url, '/');
    }
}
