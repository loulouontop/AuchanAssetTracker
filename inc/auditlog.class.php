<?php

/**
 * Immutable audit trail (who / when / what / on which entity).
 * Searchable on the admin dashboard (Recent activity).
 */
class PluginAuchanassettrackerAuditlog extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker_equipment';

    public static function getTypeName($nb = 0): string
    {
        return _n('Audit log', 'Audit logs', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_auditlogs';
    }

    public static function getIcon(): string
    {
        return 'ti ti-history';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/auditlog.php';
    }

    public static function getFormURL($full = true): string
    {
        return self::getSearchURL($full);
    }

    public static function canView(): bool
    {
        return PluginAuchanassettrackerRighthelper::isCentralAdmin()
            || PluginAuchanassettrackerRighthelper::canSeeEquipment();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public static function canDelete(): bool
    {
        return false;
    }

    public static function canPurge(): bool
    {
        return false;
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

        $users_id = (int) Session::getLoginUserID();
        $DB->insert(self::getTable(), [
            'users_id'      => $users_id,
            'action'        => $action,
            'itemtype'      => $itemtype,
            'items_id'      => $items_id,
            'details'       => $details,
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);

        // Mirror into GLPI_LOG_DIR/plugin_auchanassettracker.log (file was empty
        // because only rare exceptions were written there).
        try {
            $who = PluginAuchanassettrackerPluginlog::userRef($users_id);
            $detail = trim($details);
            if (strlen($detail) > 500) {
                $detail = substr($detail, 0, 500) . '…';
            }
            PluginAuchanassettrackerPluginlog::info(sprintf(
                '%s | %s #%d | by %s%s',
                $action,
                $itemtype,
                $items_id,
                $who,
                $detail !== '' ? (' | ' . $detail) : ''
            ));
        } catch (Throwable $e) {
            // Never break business flow because of file logging.
        }
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

    /**
     * Human label for action codes (underscores → spaces).
     */
    public static function formatActionLabel(string $action): string
    {
        $action = trim($action);
        if ($action === '') {
            return '';
        }
        return ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Turn raw key=value / JSON audit payloads into readable text.
     */
    public static function formatDetailsClean(?string $details): string
    {
        $raw = trim((string) $details);
        if ($raw === '') {
            return '';
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            return self::formatDetailsArray($json);
        }

        // key=value tokens (values may be empty).
        if (preg_match_all('/(\w+)=([^\s]*)/u', $raw, $matches, PREG_SET_ORDER)) {
            $parts = [];
            foreach ($matches as $m) {
                $key = (string) ($m[1] ?? '');
                $val = trim((string) ($m[2] ?? ''), " \t\"'");
                $parts[] = self::formatDetailKey($key) . ': ' . self::formatDetailValue($key, $val);
            }
            if ($parts !== []) {
                return implode(' · ', $parts);
            }
        }

        // Bare JSON-looking leftover or plain reason text.
        return $raw;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatDetailsArray(array $data): string
    {
        $parts = [];
        if (isset($data['updates']) && is_array($data['updates'])) {
            $labels = array_map(
                static fn ($f) => self::formatDetailKey((string) $f),
                $data['updates']
            );
            $parts[] = __('Updated fields', 'auchanassettracker') . ': ' . implode(', ', $labels);
            unset($data['updates']);
        }
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $val = implode(', ', array_map('strval', $val));
            }
            $parts[] = self::formatDetailKey((string) $key) . ': '
                . self::formatDetailValue((string) $key, (string) $val);
        }
        return implode(' · ', $parts);
    }

    private static function formatDetailKey(string $key): string
    {
        $map = [
            'serial'     => __('Serial number'),
            'location'   => __('Location'),
            'container'  => __('Container', 'auchanassettracker'),
            'itemtype'   => __('Type'),
            'items_id'   => __('Item ID', 'auchanassettracker'),
            'equipment'  => __('Equipment', 'auchanassettracker'),
            'user'       => __('User'),
            'ticket'     => __('Ticket'),
            'from'       => __('From'),
            'to'         => __('To'),
            'dest'       => __('Destination', 'auchanassettracker'),
            'items'      => __('Items', 'auchanassettracker'),
            'comment'    => __('Comment'),
            'status'     => __('Status'),
            'updates'    => __('Updated fields', 'auchanassettracker'),
            'by_sender'  => __('By sender', 'auchanassettracker'),
            'code'       => __('Code', 'auchanassettracker'),
        ];
        if (isset($map[$key])) {
            return $map[$key];
        }
        return ucwords(str_replace('_', ' ', $key));
    }

    private static function formatDetailValue(string $key, string $value): string
    {
        return strip_tags(self::formatDetailValueHtml($key, $value));
    }

    private static function linkHtml(string $url, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        if ($url === '' || $url === '#') {
            return Html::entities_deep($label);
        }
        return "<a href='" . Html::entities_deep($url) . "'>"
            . Html::entities_deep($label) . '</a>';
    }

    /** @var array<int, string> */
    private static $equipment_link_cache = [];
    /** @var array<int, string> */
    private static $location_link_cache = [];
    /** @var array<int, string> */
    private static $container_link_cache = [];

    private static function equipmentLink(int $eid): string
    {
        if ($eid <= 0) {
            return '';
        }
        if (isset(self::$equipment_link_cache[$eid])) {
            return self::$equipment_link_cache[$eid];
        }
        $eq = new PluginAuchanassettrackerEquipment();
        if (!$eq->getFromDB($eid)) {
            return self::$equipment_link_cache[$eid] = Html::entities_deep('#' . $eid);
        }
        $name = trim((string) ($eq->fields['name'] ?? ''));
        if ($name === '') {
            $name = '#' . $eid;
        }
        $url = PluginAuchanassettrackerEquipment::getFormURLWithID($eid);
        return self::$equipment_link_cache[$eid] = self::linkHtml($url, $name);
    }

    private static function locationLink(int $lid): string
    {
        if ($lid <= 0) {
            return '';
        }
        if (isset(self::$location_link_cache[$lid])) {
            return self::$location_link_cache[$lid];
        }
        $name = trim(strip_tags((string) Dropdown::getDropdownName('glpi_locations', $lid)));
        if ($name === '' || $name === '&nbsp;' || $name === '-') {
            $name = '#' . $lid;
        }
        $url = Location::getFormURLWithID($lid);
        return self::$location_link_cache[$lid] = self::linkHtml($url, $name);
    }

    private static function containerLink(int $cid): string
    {
        if ($cid <= 0) {
            return '';
        }
        if (isset(self::$container_link_cache[$cid])) {
            return self::$container_link_cache[$cid];
        }
        $name = trim(strip_tags((string) Dropdown::getDropdownName(
            PluginAuchanassettrackerContainer::getTable(),
            $cid
        )));
        if ($name === '' || $name === '&nbsp;' || $name === '-') {
            $name = '#' . $cid;
        }
        $url = PluginAuchanassettrackerContainer::getFormURLWithID($cid);
        return self::$container_link_cache[$cid] = self::linkHtml($url, $name);
    }

    private static function userLink(int $uid): string
    {
        if ($uid <= 0) {
            return '—';
        }
        $login = self::userLogin($uid);
        if ($login === '') {
            $login = trim(strip_tags((string) getUserName($uid)));
        }
        if ($login === '') {
            $login = '#' . $uid;
        }
        $url = User::getFormURLWithID($uid);
        return self::linkHtml($url, $login);
    }

    private static function ticketLink(int $tid): string
    {
        if ($tid <= 0) {
            return '—';
        }
        $label = '#' . $tid;
        $url = Ticket::getFormURLWithID($tid);
        return self::linkHtml($url, $label);
    }

    /**
     * Empty / zero FK values should be omitted from details (not shown as 0 / —).
     */
    private static function isEmptyDetailValue(string $key, string $value): bool
    {
        $value = trim($value);
        if ($value === '' || $value === '—' || strtolower($value) === 'null') {
            return true;
        }
        // Hide missing FKs / ids (container=0, location=0, items_id=0, …).
        if ($value === '0'
            && in_array($key, [
                'container', 'location', 'locations_id', 'from', 'to', 'dest',
                'equipment', 'user', 'users_id', 'ticket', 'items_id', 'items',
                'plugin_auchanassettracker_containers_id',
            ], true)
        ) {
            return true;
        }
        return false;
    }

    private static function formatDetailValueHtml(string $key, string $value): string
    {
        $value = trim($value);
        if (self::isEmptyDetailValue($key, $value)) {
            return '';
        }

        if (in_array($key, ['location', 'from', 'to', 'dest', 'locations_id'], true) && ctype_digit($value)) {
            return self::locationLink((int) $value);
        }

        if (in_array($key, ['container', 'plugin_auchanassettracker_containers_id'], true)
            && ctype_digit($value)
        ) {
            return self::containerLink((int) $value);
        }

        if ($key === 'equipment' && ctype_digit($value)) {
            return self::equipmentLink((int) $value);
        }

        if (in_array($key, ['user', 'users_id'], true) && ctype_digit($value)) {
            return self::userLink((int) $value);
        }

        if ($key === 'ticket' && ctype_digit($value)) {
            return self::ticketLink((int) $value);
        }

        if ($key === 'status') {
            return Html::entities_deep(PluginAuchanassettrackerEquipment::getStatusLabel($value));
        }

        if ($key === 'itemtype') {
            return Html::entities_deep(PluginAuchanassettrackerEquipment::getItemtypeLabel($value));
        }

        if ($key === 'items_id' && ctype_digit($value)) {
            // Prefer not showing raw asset ids alone — caller should pair with itemtype.
            return Html::entities_deep('#' . $value);
        }

        if ($key === 'items' && preg_match('/^[\d,]+$/', $value)) {
            $links = [];
            foreach (explode(',', $value) as $raw_id) {
                $eid = (int) trim($raw_id);
                if ($eid > 0) {
                    $links[] = self::equipmentLink($eid);
                }
            }
            return $links !== [] ? implode(', ', $links) : '';
        }

        if ($value === 'by_sender') {
            return Html::entities_deep(__('By sender', 'auchanassettracker'));
        }

        return Html::entities_deep($value);
    }

    /**
     * @param list<string> $fields
     */
    private static function formatUpdatedFieldsPart(array $fields): string
    {
        $labels = [];
        foreach ($fields as $f) {
            $f = trim((string) $f);
            if ($f === '' || ctype_digit($f)) {
                continue;
            }
            // Skip noisy internal ids / raw status codes in the field list.
            if (in_array($f, [
                'status', 'items_id', 'itemtype', 'users_id', 'id', 'date_mod',
                'date_creation', 'entities_id', 'is_recursive', 'is_deleted',
            ], true)) {
                continue;
            }
            $labels[] = self::formatDetailKey($f);
        }
        if ($labels === []) {
            return '';
        }
        return Html::entities_deep(__('Updated fields', 'auchanassettracker') . ': '
            . implode(', ', $labels));
    }

    /**
     * HTML details for Search results (clickable names).
     *
     * @param array<string, mixed> $row
     */
    public static function formatDetailsHtml(string $details, array $row = []): string
    {
        $action = (string) ($row['action'] ?? '');
        $row_itemtype = (string) ($row['itemtype'] ?? '');
        $row_items_id = (int) ($row['items_id'] ?? 0);

        $prefix = '';
        if ($row_items_id > 0) {
            if ($row_itemtype === PluginAuchanassettrackerEquipment::class
                || $row_itemtype === 'PluginAuchanassettrackerEquipment'
                || str_starts_with($action, 'equipment_')
            ) {
                $prefix = self::equipmentLink($row_items_id);
            } elseif ($row_itemtype === PluginAuchanassettrackerContainer::class
                || $row_itemtype === 'PluginAuchanassettrackerContainer'
                || str_starts_with($action, 'container_')
            ) {
                $prefix = self::containerLink($row_items_id);
            }
        }

        $raw = trim($details);
        if ($raw === '') {
            return $prefix;
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            $parts = [];
            if ($prefix !== '') {
                $parts[] = $prefix;
            }

            // Legacy container_update stored a bare list of field names → show as Updated fields.
            $is_list = $json !== [] && array_keys($json) === range(0, count($json) - 1);
            if ($is_list) {
                $upd = self::formatUpdatedFieldsPart(array_map('strval', $json));
                if ($upd !== '') {
                    $parts[] = $upd;
                }
                return implode(' · ', array_filter($parts, static fn ($p) => $p !== ''));
            }

            if (isset($json['updates']) && is_array($json['updates'])) {
                $upd = self::formatUpdatedFieldsPart(array_map('strval', $json['updates']));
                if ($upd !== '') {
                    $parts[] = $upd;
                }
                unset($json['updates']);
            }

            // Equipment / container updates: only the field list (no status / ids / extras).
            if (str_starts_with($action, 'equipment_update')
                || str_starts_with($action, 'container_update')
            ) {
                return implode(' · ', array_filter($parts, static fn ($p) => $p !== ''));
            }

            unset($json['status'], $json['items_id']);
            foreach ($json as $key => $val) {
                $key = (string) $key;
                if (is_array($val)) {
                    $val = implode(', ', array_map('strval', $val));
                }
                $val = (string) $val;
                if (self::isEmptyDetailValue($key, $val)) {
                    continue;
                }
                $rendered = self::formatDetailValueHtml($key, $val);
                if ($rendered === '') {
                    continue;
                }
                $parts[] = Html::entities_deep(self::formatDetailKey($key) . ': ') . $rendered;
            }
            return implode(' · ', array_filter($parts, static fn ($p) => $p !== ''));
        }

        if (preg_match_all('/(\w+)=([^\s]*)/u', $raw, $matches, PREG_SET_ORDER)) {
            $parts = [];
            if ($prefix !== '') {
                $parts[] = $prefix;
            }
            foreach ($matches as $m) {
                $key = (string) ($m[1] ?? '');
                $val = trim((string) ($m[2] ?? ''), " \t\"'");
                if ($key === 'items_id') {
                    continue;
                }
                if ($key === 'status' && str_starts_with($action, 'equipment_update')) {
                    continue;
                }
                if (self::isEmptyDetailValue($key, $val)) {
                    continue;
                }
                $rendered = self::formatDetailValueHtml($key, $val);
                if ($rendered === '') {
                    continue;
                }
                $parts[] = Html::entities_deep(self::formatDetailKey($key) . ': ') . $rendered;
            }
            return implode(' · ', array_filter($parts, static fn ($p) => $p !== ''));
        }

        $plain = Html::entities_deep($raw);
        return $prefix !== '' ? ($prefix . ' · ' . $plain) : $plain;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'action':
                return Html::entities_deep(self::formatActionLabel((string) ($values[$field] ?? '')));

            case 'details':
                return self::formatDetailsHtml((string) ($values[$field] ?? ''), $values);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'action':
                return Html::input($name, [
                    'value' => $values[$field] ?? '',
                    'class' => 'form-control',
                ]);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'datatype'      => 'number',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 2,
            'table'         => self::getTable(),
            'field'         => 'date_creation',
            'name'          => __('Date'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 3,
            'table'         => 'glpi_users',
            'field'         => 'name',
            'name'          => __('User'),
            'datatype'      => 'itemlink',
            'linkfield'     => 'users_id',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 4,
            'table'         => self::getTable(),
            'field'         => 'action',
            'name'          => __('Action'),
            'datatype'      => 'specific',
            'searchtype'    => ['contains', 'equals'],
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'               => 5,
            'table'            => self::getTable(),
            'field'            => 'details',
            'name'             => __('Details'),
            'datatype'         => 'specific',
            'htmltext'         => true,
            'massiveaction'    => false,
            // Needed so details can resolve equipment names from the row.
            'additionalfields' => ['action', 'itemtype', 'items_id'],
        ];

        return $tab;
    }

    public static function ensureDisplayPreferences(): void
    {
        global $DB;

        if (!$DB->tableExists('glpi_displaypreferences')) {
            return;
        }

        $itemtype = self::class;

        // Drop Item type column if an older seed added it (search option id 6).
        try {
            $DB->delete('glpi_displaypreferences', [
                'itemtype' => $itemtype,
                'num'      => 6,
            ]);
        } catch (Throwable $e) {
            // Ignore.
        }

        $existing = 0;
        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => $itemtype,
                'users_id' => 0,
            ],
        ]) as $row) {
            $existing = (int) ($row['cpt'] ?? 0);
        }
        if ($existing > 0) {
            return;
        }

        // Date, User, Action, Details
        $rank = 1;
        foreach ([2, 3, 4, 5] as $num) {
            try {
                $DB->insert('glpi_displaypreferences', [
                    'itemtype' => $itemtype,
                    'num'      => $num,
                    'rank'     => $rank,
                    'users_id' => 0,
                ]);
                $rank++;
            } catch (Throwable $e) {
                // Ignore duplicates.
            }
        }
    }
}
