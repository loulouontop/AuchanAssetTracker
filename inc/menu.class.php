<?php

class PluginAuchanassettrackerMenu extends CommonGLPI
{
    public static $rightname = 'plugin_auchanassettracker';

    /** Top-level menu sector (not under Assets). */
    public const SECTOR = 'auchanassettracker';

    /** Option keys under the Auchan Asset Tracker menu. */
    public const MENU_EQUIPMENT  = 'aat_equipment';
    public const MENU_CONTAINER  = 'aat_container';
    public const MENU_BULK       = 'aat_bulk';
    public const MENU_ALLOCATION = 'aat_allocation';
    public const MENU_CONFIRM    = 'aat_confirm';
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
        $can_stock = PluginAuchanassettrackerRighthelper::canManageStock();
        $can_alloc = PluginAuchanassettrackerRighthelper::canAllocate();
        $is_admin  = PluginAuchanassettrackerRighthelper::isCentralAdmin();

        $content = [];

        if ($can_stock || $can_alloc || $is_admin) {
            $content[self::MENU_EQUIPMENT] = [
                'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/equipment.php",
                'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
                'links' => [
                    'search' => "$base/front/equipment.php",
                    'add'    => "$base/front/equipment.form.php",
                ],
            ];
            $content[self::MENU_CONTAINER] = [
                'title' => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/container.php",
                'icon'  => PluginAuchanassettrackerContainer::getIcon(),
                'links' => [
                    'search' => "$base/front/container.php",
                    'add'    => "$base/front/container.form.php",
                ],
            ];
        }

        if ($can_stock) {
            $content[self::MENU_BULK] = [
                'title' => PluginAuchanassettrackerBulk::getTypeName(1),
                'page'  => "$base/front/equipment.bulk.php",
                'icon'  => PluginAuchanassettrackerBulk::getIcon(),
            ];
        }

        if ($can_alloc) {
            $content[self::MENU_ALLOCATION] = [
                'title' => __('New allocation', 'auchanassettracker'),
                'page'  => "$base/front/allocation.form.php",
                'icon'  => 'ti ti-user-plus',
            ];
        }

        $content[self::MENU_CONFIRM] = [
            'title' => __('Confirm receipt', 'auchanassettracker'),
            'page'  => "$base/front/confirm.php",
            'icon'  => 'ti ti-check',
        ];

        if ($is_admin) {
            $content[self::MENU_CONFIG] = [
                'title' => __('Configuration', 'auchanassettracker'),
                'page'  => "$base/front/config.form.php",
                'icon'  => 'ti ti-settings',
            ];
        }

        if ($content === []) {
            return false;
        }

        $default = "$base/front/confirm.php";
        if ($can_alloc) {
            $default = "$base/front/allocation.form.php";
        } elseif ($can_stock) {
            $default = "$base/front/equipment.php";
        }

        return [
            'title'   => self::getMenuName(),
            'page'    => $default,
            'icon'    => self::getIcon(),
            'content' => $content,
        ];
    }
}
