<?php

class PluginAuchanassettrackerMailhelper
{
    public static function notifyAllocationPending(int $users_id, int $equipment_id): void
    {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Equipment awaiting confirmation', 'auchanassettracker');
        $body = sprintf(
            __('You have equipment awaiting confirmation: %s (%s). Please confirm receipt in Auchan Asset Tracker.', 'auchanassettracker'),
            $eq->fields['name'] ?? ('#' . $equipment_id),
            $eq->fields['serial'] ?? ''
        );
        self::send($users_id, $subject, $body);
    }

    public static function notifyAllocationRejected(int $users_id, int $equipment_id): void
    {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Allocation rejected by user', 'auchanassettracker');
        $body = sprintf(
            __('User reported they did not receive: %s (%s). Please place it back in a container.', 'auchanassettracker'),
            $eq->fields['name'] ?? ('#' . $equipment_id),
            $eq->fields['serial'] ?? ''
        );
        self::send($users_id, $subject, $body);
    }

    public static function send(int $users_id, string $subject, string $body): void
    {
        if ($users_id <= 0) {
            return;
        }

        try {
            $user = new User();
            if (!$user->getFromDB($users_id)) {
                return;
            }
            $email = $user->getDefaultEmail();
            if (!$email) {
                PluginAuchanassettrackerPluginlog::info(
                    "No email for user $users_id — in-app notification only. Subject: $subject"
                );
                return;
            }

            if (class_exists('GLPIMailer', false)) {
                $mmail = new GLPIMailer();
                $mmail->AddAddress($email);
                $mmail->Subject = '[' . __('Auchan Asset Tracker', 'auchanassettracker') . '] ' . $subject;
                $mmail->Body = $body;
                @$mmail->Send();
                return;
            }

            // GLPI 11 may expose Notification_Mailing / Symfony mailer only — log and rely on in-app notices.
            PluginAuchanassettrackerPluginlog::info(
                "Mail backend unavailable; queued notice for $email — $subject"
            );
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'mail');
        }
    }
}
