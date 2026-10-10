<?php

/**
 * In-app notices for allocators/managers (shown in the plugin UI).
 */
class PluginAuchanassettrackerNotice extends CommonDBTM
{
    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_notices';
    }

    public static function addForUser(int $users_id, string $message, string $link = ''): void
    {
        global $DB;

        if ($users_id <= 0 || $message === '' || !$DB->tableExists(self::getTable())) {
            return;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->insert(self::getTable(), [
            'users_id'      => $users_id,
            'message'       => $message,
            'link'          => $link,
            'is_read'       => 0,
            'date_creation' => $now,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getUnreadForUser(int $users_id, int $limit = 20): array
    {
        global $DB;

        if ($users_id <= 0 || !$DB->tableExists(self::getTable())) {
            return [];
        }

        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'users_id' => $users_id,
                'is_read'  => 0,
            ],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => $limit,
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    public static function markReadForUser(int $users_id): void
    {
        global $DB;

        if ($users_id <= 0 || !$DB->tableExists(self::getTable())) {
            return;
        }

        $DB->update(self::getTable(), ['is_read' => 1], [
            'users_id' => $users_id,
            'is_read'  => 0,
        ]);
    }

    /** Avoid spamming the same overdue notice. */
    public static function hasSimilarUnread(int $users_id, string $message): bool
    {
        global $DB;

        if ($users_id <= 0 || $message === '' || !$DB->tableExists(self::getTable())) {
            return false;
        }

        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'users_id' => $users_id,
                'is_read'  => 0,
                'message'  => $message,
            ],
            'LIMIT' => 1,
        ]) as $_) {
            return true;
        }
        return false;
    }
}
