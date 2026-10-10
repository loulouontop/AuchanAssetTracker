<?php

class PluginAuchanassettrackerMailhelper
{
    public static function notifyAllocationPending(int $users_id, int $equipment_id): void
    {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Equipment awaiting confirmation', 'auchanassettracker');
        $name = (string) ($eq->fields['name'] ?? ('#' . $equipment_id));
        $body = sprintf(
            __('You have equipment awaiting confirmation: %s. Please confirm receipt in Auchan Asset Tracker.', 'auchanassettracker'),
            $name
        );
        $link = plugin_auchanassettracker_web_dir() . '/front/confirm.php';
        self::send($users_id, $subject, $body, $link);
    }

    /**
     * One event notice for the allocator (not duplicated in Active alerts when shelf restored).
     */
    public static function notifyAllocationRejected(int $users_id, int $equipment_id, bool $container_restored = true): void
    {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Allocation rejected by user', 'auchanassettracker');
        $name = (string) ($eq->fields['name'] ?? ('#' . $equipment_id));
        if ($container_restored) {
            $body = sprintf(
                __('User reported they did not receive: %s. Item returned to its previous container.', 'auchanassettracker'),
                $name
            );
        } else {
            $body = sprintf(
                __('User reported they did not receive: %s. Previous container unavailable — assign a container (see Active alerts).', 'auchanassettracker'),
                $name
            );
        }
        $link = plugin_auchanassettracker_web_dir() . '/front/equipment.form.php?id=' . $equipment_id;
        self::send($users_id, $subject, $body, $link);
    }

    public static function send(int $users_id, string $subject, string $body, string $link = ''): void
    {
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
                PluginAuchanassettrackerPluginlog::info(
                    "No email for user $users_id — in-app notice stored. Subject: $subject"
                );
                return;
            }

            if (class_exists('GLPIMailer', false)) {
                $mmail = new GLPIMailer();
                $mmail->AddAddress($email);
                $mmail->Subject = '[' . __('Auchan Asset Tracker', 'auchanassettracker') . '] ' . $subject;
                $mmail->Body = $body . ($link !== '' ? "\n\n" . $link : '');
                @$mmail->Send();
                return;
            }

            PluginAuchanassettrackerPluginlog::info(
                "Mail backend unavailable; in-app notice stored for $email — $subject"
            );
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'mail');
        }
    }
}
