<?php

class PluginAuchanassettrackerMenu extends CommonGLPI
{
    /** Lowest common right so End users (confirm-only) still get the sector. */
    public static $rightname = 'plugin_auchanassettracker_confirm';

    /** Top-level menu sector (not under Assets). */
    public const SECTOR = 'auchanassettracker';

    /** Option keys under the Auchan Asset Tracker menu. */
    public const MENU_DASHBOARD  = 'aat_dashboard';
    public const MENU_EQUIPMENT  = 'aat_equipment';
    public const MENU_CONTAINER  = 'aat_container';
    public const MENU_BULK       = 'aat_bulk';
    public const MENU_ALLOCATION = 'aat_allocation';
    public const MENU_TRANSFER   = 'aat_transfer';
    public const MENU_CONFIRM    = 'aat_confirm';
    public const MENU_REPORT     = 'aat_report';
    public const MENU_CONFIG     = 'aat_config';

    public static function getIcon(): string
    {
        return 'ti ti-packages';
    }

    public static function getMenuName(): string
    {
        return __('Auchan Asset Tracker', 'auchanassettracker');
    }

    /**
     * GLPI-native form chrome: blue bookmark ribbon + title + entity badge (like Location).
     *
     * Does not open a <form>, so custom workspace forms below stay valid.
     */
    public static function beginNativeFormCard(string $title, string $icon): void
    {
        $entity_id = (int) ($_SESSION['glpiactive_entity'] ?? 0);
        $entity = '';
        if ($entity_id > 0) {
            $entity = trim(strip_tags((string) Dropdown::getDropdownName('glpi_entities', $entity_id)));
        }

        // Same markup as native GLPI item forms (e.g. New item - Equipment).
        echo '<div class="asset aat-form-page">';
        echo '<div class="card">';
        echo '<div class="card-header main-header d-flex flex-wrap flex-md-nowrap me-2 mt-n2 align-items-stretch  flex-grow-1" style="min-width: 100px;">';
        echo '<h3 class="card-title d-flex align-items-center ps-0 ps-sm-4">';
        echo '<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1">';
        echo '<i class="' . Html::entities_deep($icon) . ' fa-2x"></i>';
        echo '</div>';
        echo '<span>' . Html::entities_deep($title) . '</span>';
        echo '</h3>';

        if ($entity !== '' && $entity !== '&nbsp;' && $entity !== '-') {
            echo '<div class="badge entity-name mx-1 px-2 ms-auto align-items-center col" title="'
                . Html::entities_deep($entity) . '" style="min-width: 100px; max-width: fit-content;">';
            echo '<i class="ti ti-stack me-2"></i>';
            echo '<div class="overflow-hidden text-truncate text-nowrap">';
            echo '<span class="float-end ps-1">' . Html::entities_deep($entity) . '</span>';
            echo '</div></div>';
        }

        echo '</div>'; // card-header
        echo '<div class="card-body">';
    }

    public static function endNativeFormCard(): void
    {
        echo '</div></div></div>'; // card-body, card, asset
    }

    /**
     * @return array{title: string, page: string, icon: string, content: array<string, array<string, mixed>>}|false
     */
    public static function getMenuContent(): array|false
    {
        if (!Session::getLoginUserID()) {
            return false;
        }

        $base = plugin_auchanassettracker_web_dir(true);
        $content = [];

        // Dashboard is the role-aware home for every logged-in plugin user.
        $content[self::MENU_DASHBOARD] = [
            'title' => __('Dashboard', 'auchanassettracker'),
            'page'  => "$base/front/dashboard.php",
            'icon'  => 'ti ti-dashboard',
        ];

        // Hide tabs entirely when the profile has no related right (no "click then denied").
        if (PluginAuchanassettrackerRighthelper::canSeeEquipment()) {
            $eq = [
                'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/equipment.php",
                'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
                'links' => [
                    'search' => "$base/front/equipment.php",
                ],
            ];
            if (PluginAuchanassettrackerRighthelper::canManageStock()) {
                $eq['links']['add'] = "$base/front/equipment.form.php";
            }
            $content[self::MENU_EQUIPMENT] = $eq;
        }

        if (PluginAuchanassettrackerRighthelper::canSeeContainers()) {
            $ct = [
                'title' => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/container.php",
                'icon'  => PluginAuchanassettrackerContainer::getIcon(),
                'links' => [
                    'search' => "$base/front/container.php",
                ],
            ];
            if (PluginAuchanassettrackerRighthelper::canManageStock()) {
                $ct['links']['add'] = "$base/front/container.form.php";
            }
            $content[self::MENU_CONTAINER] = $ct;
        }

        if (PluginAuchanassettrackerRighthelper::canBulk()) {
            $content[self::MENU_BULK] = [
                'title' => PluginAuchanassettrackerBulk::getTypeName(1),
                'page'  => "$base/front/equipment.bulk.php",
                'icon'  => PluginAuchanassettrackerBulk::getIcon(),
            ];
        }

        if (PluginAuchanassettrackerRighthelper::canAllocate()) {
            $content[self::MENU_ALLOCATION] = [
                'title' => __('New allocation', 'auchanassettracker'),
                'page'  => "$base/front/allocation.form.php",
                'icon'  => 'ti ti-user-plus',
            ];
        }

        if (PluginAuchanassettrackerRighthelper::canTransfer()) {
            $tr = [
                'title' => __('Transfers', 'auchanassettracker'),
                'page'  => "$base/front/transfer.php",
                'icon'  => PluginAuchanassettrackerTransfer::getIcon(),
                'links' => [
                    'search' => "$base/front/transfer.php",
                ],
            ];
            if (PluginAuchanassettrackerRighthelper::canTransfer()) {
                $tr['links']['add'] = "$base/front/transfer.form.php";
            }
            $content[self::MENU_TRANSFER] = $tr;
        }

        if (PluginAuchanassettrackerRighthelper::canConfirm()) {
            $content[self::MENU_CONFIRM] = [
                'title' => __('Confirm receipt', 'auchanassettracker'),
                'page'  => "$base/front/confirm.php",
                'icon'  => 'ti ti-check',
            ];
        }

        if (PluginAuchanassettrackerRighthelper::canViewReports()) {
            $content[self::MENU_REPORT] = [
                'title' => __('Reports', 'auchanassettracker'),
                'page'  => "$base/front/report.php",
                'icon'  => 'ti ti-report',
            ];
        }

        if (PluginAuchanassettrackerRighthelper::canConfigure()) {
            $content[self::MENU_CONFIG] = [
                'title' => __('Configuration', 'auchanassettracker'),
                'page'  => "$base/front/config.form.php",
                'icon'  => 'ti ti-settings',
            ];
        }

        if ($content === []) {
            return false;
        }

        return [
            'title'   => self::getMenuName(),
            'page'    => $content[self::MENU_DASHBOARD]['page'],
            'icon'    => self::getIcon(),
            'content' => $content,
        ];
    }
}
