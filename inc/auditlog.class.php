<?php

/**
 * Immutable audit trail (who / when / what / on which entity).
 */
class PluginAuchanassettrackerAuditlog extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return _n('Audit log', 'Audit logs', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_auditlogs';
    }

    /**
     * GLPI login name (User.name), never bare numeric id.
     */
    public static function userLogin(int $users_id): string
    {
        if ($users_id <= 0) {
            return '';
        }
        $user = new User();
        if ($user->getFromDB($users_id)) {
            $login = trim((string) ($user->fields['name'] ?? ''));
            if ($login !== '') {
                return $login;
            }
        }
        return '';
    }

    public static function record(
        string $action,
        string $itemtype,
        int $items_id,
        string $details = ''
    ): void {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return;
        }

        $DB->insert(self::getTable(), [
            'users_id'      => (int) Session::getLoginUserID(),
            'action'        => $action,
            'itemtype'      => $itemtype,
            'items_id'      => $items_id,
            'details'       => $details,
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getRecent(int $limit = 15): array
    {
        global $DB;
        if (!$DB->tableExists(self::getTable())) {
            return [];
        }
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'ORDER' => 'date_creation DESC',
            'LIMIT' => max(1, $limit),
        ]) as $row) {
            $uid = (int) ($row['users_id'] ?? 0);
            $row['user_login'] = self::userLogin($uid);
            $rows[] = $row;
        }
        return $rows;
    }
}
