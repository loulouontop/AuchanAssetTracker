<?php

/**
 * Plugin configuration (alert thresholds: allocation / transfer / service).
 */
class PluginAuchanassettrackerConfig extends CommonDBTM
{
    public static $rightname = 'config';

    public const KEY_ALLOC_DAYS    = 'allocation_confirm_days';
    public const KEY_TRANSFER_DAYS = 'transfer_validate_days';
    public const KEY_SERVICE_DAYS  = 'service_max_days';

    public const DEFAULT_ALLOC_DAYS    = 5;
    public const DEFAULT_TRANSFER_DAYS = 7;
    public const DEFAULT_SERVICE_DAYS  = 30;

    public static function getTypeName($nb = 0): string
    {
        return __('Auchan Asset Tracker - configuration', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_configs';
    }

    public static function getIcon(): string
    {
        return 'ti ti-settings';
    }

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_CONFIG];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/config.form.php';
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    public static function canView(): bool
    {
        return PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    public static function canCreate(): bool
    {
        return self::canView();
    }

    public static function canUpdate(): bool
    {
        return self::canView();
    }

    public function canCreateItem(): bool
    {
        return self::canCreate();
    }

    public function canUpdateItem(): bool
    {
        return self::canUpdate();
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm(-1, $options);
        echo '<div class="aat-config-page">';
        echo '<div class="aat-config-card">';
        PluginAuchanassettrackerMenu::beginNativeFormCard(
            __('Alert thresholds', 'auchanassettracker'),
            self::getIcon()
        );
        echo "<form method='post' action='" . Html::entities_deep(self::getFormURL()) . "'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo "<div class='mb-3'><label class='form-label'>"
            . __('Allocation confirmation (calendar days)', 'auchanassettracker') . "</label>";
        echo Html::input('allocation_confirm_days', [
            'type'  => 'number',
            'min'   => 1,
            'class' => 'form-control',
            'value' => self::getAllocationConfirmDays(),
        ]);
        echo "<div class='form-text'>"
            . __('Default 5 calendar days. Late pending allocations appear under Active alerts on New allocation.', 'auchanassettracker')
            . "</div></div>";

        echo "<div class='mb-3'><label class='form-label'>"
            . __('Transfer validation (days)', 'auchanassettracker') . "</label>";
        echo Html::input('transfer_validate_days', [
            'type'  => 'number',
            'min'   => 1,
            'class' => 'form-control',
            'value' => self::getTransferValidateDays(),
        ]);
        echo "<div class='form-text'>"
            . __('Default 7 days before an in-transit transfer is considered overdue.', 'auchanassettracker')
            . "</div></div>";

        echo "<div class='mb-3'><label class='form-label'>"
            . __('Service max period (days)', 'auchanassettracker') . "</label>";
        echo Html::input('service_max_days', [
            'type'  => 'number',
            'min'   => 1,
            'class' => 'form-control',
            'value' => self::getServiceMaxDays(),
        ]);
        echo "<div class='form-text'>"
            . __('Default 30 days before equipment in service is considered overdue.', 'auchanassettracker')
            . "</div></div>";

        echo "<div class='text-center'>";
        echo Html::submit(_sx('button', 'Save'), ['name' => 'save_thresholds', 'class' => 'btn btn-primary']);
        echo "</div>";
        Html::closeForm();
        PluginAuchanassettrackerMenu::endNativeFormCard();
        echo '</div></div>';
        return true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return $default;
        }

        foreach ($DB->request([
            'SELECT' => ['value'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['name' => $key],
            'LIMIT'  => 1,
        ]) as $row) {
            return (string) ($row['value'] ?? $default);
        }

        return $default;
    }

    public static function set(string $key, string $value): bool
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return false;
        }

        $existing = 0;
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['name' => $key],
            'LIMIT'  => 1,
        ]) as $row) {
            $existing = (int) ($row['id'] ?? 0);
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        if ($existing > 0) {
            return (bool) $DB->update(
                self::getTable(),
                ['value' => $value, 'date_mod' => $now],
                ['id' => $existing]
            );
        }

        return (bool) $DB->insert(self::getTable(), [
            'name'          => $key,
            'value'         => $value,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);
    }

    public static function getInt(string $key, int $default): int
    {
        $raw = self::get($key, (string) $default);
        $v = (int) $raw;
        return $v > 0 ? $v : $default;
    }

    public static function getAllocationConfirmDays(): int
    {
        return self::getInt(self::KEY_ALLOC_DAYS, self::DEFAULT_ALLOC_DAYS);
    }

    public static function getTransferValidateDays(): int
    {
        return self::getInt(self::KEY_TRANSFER_DAYS, self::DEFAULT_TRANSFER_DAYS);
    }

    public static function getServiceMaxDays(): int
    {
        return self::getInt(self::KEY_SERVICE_DAYS, self::DEFAULT_SERVICE_DAYS);
    }

    public static function saveThresholds(int $alloc, int $transfer = 7, int $service = 30): void
    {
        self::set(self::KEY_ALLOC_DAYS, (string) max(1, $alloc));
        self::set(self::KEY_TRANSFER_DAYS, (string) max(1, $transfer));
        self::set(self::KEY_SERVICE_DAYS, (string) max(1, $service));
    }

    public static function seedDefaults(): void
    {
        if (self::get(self::KEY_ALLOC_DAYS) === null) {
            self::set(self::KEY_ALLOC_DAYS, (string) self::DEFAULT_ALLOC_DAYS);
        }
        if (self::get(self::KEY_TRANSFER_DAYS) === null) {
            self::set(self::KEY_TRANSFER_DAYS, (string) self::DEFAULT_TRANSFER_DAYS);
        }
        if (self::get(self::KEY_SERVICE_DAYS) === null) {
            self::set(self::KEY_SERVICE_DAYS, (string) self::DEFAULT_SERVICE_DAYS);
        }
    }
}
