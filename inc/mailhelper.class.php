<?php

/**
 * Plugin event notices + HTML emails (not GLPI native Notification / NotificationTemplate).
 *
 * Config thresholds drive Active alerts only; these emails fire on allocate / reject events.
 */
class PluginAuchanassettrackerMailhelper
{
    public static function notifyAllocationPending(int $users_id, int $equipment_id): void
    {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Equipment awaiting confirmation', 'auchanassettracker');
        $name = (string) ($eq->fields['name'] ?? ('#' . $equipment_id));
        $serial = (string) ($eq->fields['serial'] ?? '');
        $type = (string) ($eq->fields['itemtype'] ?? '');
        $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
        $body = sprintf(
            __('You have equipment awaiting confirmation: %s. Please confirm receipt in Auchan Asset Tracker.', 'auchanassettracker'),
            $name
        );
        $link = plugin_auchanassettracker_web_dir(true) . '/front/confirm.php';
        self::send($users_id, $subject, $body, $link, [
            'badge'   => __('Confirm receipt', 'auchanassettracker'),
            'intro'   => __(
                'You have equipment awaiting confirmation. Please confirm receipt in Auchan Asset Tracker.',
                'auchanassettracker'
            ),
            'cta'     => __('Confirm receipt', 'auchanassettracker'),
            'rows'    => [
                [__('Equipment'), $name],
                [__('Type'), $type_label],
                [__('Serial number'), $serial !== '' ? $serial : '—'],
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
        $name = (string) ($eq->fields['name'] ?? ('#' . $equipment_id));
        $serial = (string) ($eq->fields['serial'] ?? '');
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
        $link = plugin_auchanassettracker_web_dir(true) . '/front/equipment.form.php?id=' . $equipment_id;
        $rows = [
            [__('Equipment'), $name],
            [__('Serial number'), $serial !== '' ? $serial : '—'],
        ];
        if ($comment !== '') {
            $rows[] = [__('Comment'), $comment];
        }
        self::send($users_id, $subject, $body, $link, [
            'badge' => __('Allocation rejected by user', 'auchanassettracker'),
            'intro' => $intro,
            'cta'   => __('Open equipment', 'auchanassettracker'),
            'rows'  => $rows,
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
        $link = plugin_auchanassettracker_web_dir(true) . '/front/transfer.form.php?id=' . $transfer_id;
        $rows = [
            [__('Transfer', 'auchanassettracker'), '#' . $transfer_id],
        ];
        if ($comment !== '') {
            $rows[] = [__('Comment'), $comment];
        }
        self::send($users_id, $subject, $body, $link, [
            'badge' => __('Transfer rejected', 'auchanassettracker'),
            'intro' => __(
                'A transfer was rejected by the destination location.',
                'auchanassettracker'
            ),
            'cta'   => __('Open transfer', 'auchanassettracker'),
            'rows'  => $rows,
        ]);
    }

    /**
     * @param array{
     *   badge?: string,
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
                    "Mail backend unavailable; in-app notice stored for $email — $subject"
                );
            }
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'mail');
        }
    }

    /**
     * Send via GLPI 11 GLPIMailer (Symfony Mailer wrapper).
     * Must allow autoload — class_exists(..., false) skipped loading and caused false "unavailable".
     */
    public static function sendViaGlpiMailer(
        string $email,
        string $subject,
        string $html,
        string $plain
    ): bool {
        global $CFG_GLPI;

        // Autoload GLPIMailer (do NOT pass false — that skipped the class on GLPI 11).
        if (!class_exists('GLPIMailer') && !class_exists(\GLPIMailer::class)) {
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

            // Prefer Symfony Email API (no deprecation warnings).
            if (method_exists($mmail, 'getEmail')) {
                $symfony = $mmail->getEmail();
                self::applyFromToSymfonyEmail($symfony, $from['email'], $from['name']);
                // Sender header: GLPIMailer may already set smtp_sender; ensure one exists.
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
                // Legacy PHPMailer-style wrappers.
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
                    'GLPIMailer send failed for ' . $email
                    . ($err !== '' ? (': ' . $err) : '')
                );
            }
            return $ok;
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'mail');
            return false;
        }
    }

    /**
     * Resolve From address the same way GLPI notifications do.
     *
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
     * Auchan RO HTML email (red gradient header), inspired by previous plugin templates.
     *
     * @param array{
     *   badge?: string,
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
        $intro = (string) ($template['intro'] ?? $body);
        $cta   = (string) ($template['cta'] ?? __('Open in GLPI', 'auchanassettracker'));
        /** @var list<array{0: string, 1: string}> $rows */
        $rows  = $template['rows'] ?? [];

        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $rows_html = '';
        $i = 0;
        foreach ($rows as $row) {
            $label = $esc((string) ($row[0] ?? ''));
            $value = $esc((string) ($row[1] ?? ''));
            $bg = ($i % 2 === 0) ? '#FFFFFF' : '#FFF5F5';
            $rows_html .= '<tr>'
                . '<td style="padding:12px 16px;background:' . $bg
                . ';border-bottom:1px solid #F3D6D6;font-size:13px;color:#5C5C5C;width:38%;vertical-align:top;">'
                . $label . '</td>'
                . '<td style="padding:12px 16px;background:' . $bg
                . ';border-bottom:1px solid #F3D6D6;font-size:14px;color:#1A1A1A;font-weight:600;vertical-align:top;">'
                . $value . '</td>'
                . '</tr>';
            $i++;
        }
        if ($rows_html === '') {
            $rows_html = '<tr>'
                . '<td colspan="2" style="padding:12px 16px;background:#FFFFFF;font-size:14px;color:#1A1A1A;line-height:1.6;">'
                . nl2br($esc($body))
                . '</td></tr>';
        }

        $cta_html = '';
        if ($link !== '') {
            $href = $esc($link);
            $cta_html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0 8px;">'
                . '<tr><td style="border-radius:6px;background:#E30613;">'
                . '<a href="' . $href . '" target="_blank" rel="noopener noreferrer"'
                . ' style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#FFFFFF;'
                . 'text-decoration:none;border-radius:6px;background:#E30613;">'
                . $esc($cta)
                . '</a></td></tr></table>'
                . '<p style="margin:8px 0 0;font-size:12px;color:#5C5C5C;word-break:break-all;">'
                . $href . '</p>';
        }

        $footer = $esc(__(
            'Automatic notification from GLPI — Auchan Asset Tracker.',
            'auchanassettracker'
        ));

        return '<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>' . $esc($subject) . '</title>
</head>
<body style="margin:0;padding:0;background:#F0F0F0;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F0F0F0;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
             style="max-width:620px;background:#FFFFFF;border-radius:10px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,0.08);">
        <tr><td style="background:linear-gradient(135deg,#E30613 0%,#B8050F 100%);padding:28px 32px 24px;">
          <div style="font-size:13px;letter-spacing:2px;font-weight:700;color:rgba(255,255,255,0.92);">AUCHAN</div>
          <div style="margin-top:10px;font-size:24px;line-height:1.25;font-weight:700;color:#FFFFFF;">'
            . $esc($subject) . '</div>
          <div style="margin-top:8px;display:inline-block;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,0.18);font-size:12px;font-weight:700;color:#FFFFFF;letter-spacing:0.4px;">'
            . $esc($badge) . '</div>
        </td></tr>
        <tr><td style="padding:32px 32px 24px;">
          <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#1A1A1A;">'
            . $esc(__('Hello,', 'auchanassettracker')) . '</p>
          <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#5C5C5C;">'
            . $esc($intro) . '</p>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                 style="border:1px solid #F3D6D6;border-radius:8px;overflow:hidden;">'
            . $rows_html . '</table>'
            . $cta_html . '
        </td></tr>
        <tr><td style="padding:18px 32px 24px;background:#FFF5F5;border-top:1px solid #F3D6D6;font-size:12px;line-height:1.5;color:#5C5C5C;">'
            . $footer . '
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
    }
}
