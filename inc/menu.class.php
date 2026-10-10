<?php

class PluginAuchanassettrackerMenu extends CommonGLPI
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getIcon(): string
    {
        return 'ti ti-packages';
    }

    public static function getMenuName(): string
    {
        return __('Auchan Asset Tracker', 'auchanassettracker');
    }

    public static function getMenuContent(): array|false
    {
        if (!Session::getLoginUserID()) {
            return false;
        }

        $base = Plugin::getWebDir(plugin_auchanassettracker_dir());

        $default = $base . '/front/confirm.php';
        if (PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin()
            || PluginAuchanassettrackerRighthelper::canAllocate()) {
            $default = $base . '/front/equipment.php';
        }

        $menu = [
            'title' => self::getMenuName(),
            'page'  => $default,
            'icon'  => self::getIcon(),
        ];

        if (PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin()
            || PluginAuchanassettrackerRighthelper::canAllocate()) {
            $menu['options']['equipment'] = [
                'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
                'page'  => $base . '/front/equipment.php',
                'links' => [
                    'search' => $base . '/front/equipment.php',
                    'add'    => $base . '/front/equipment.form.php',
                ],
                'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
            ];
            $menu['options']['container'] = [
                'title' => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
                'page'  => $base . '/front/container.php',
                'links' => [
                    'search' => $base . '/front/container.php',
                    'add'    => $base . '/front/container.form.php',
                ],
                'icon'  => PluginAuchanassettrackerContainer::getIcon(),
            ];
        }

        if (PluginAuchanassettrackerRighthelper::canAllocate()) {
            $menu['options']['allocation'] = [
                'title' => __('New allocation', 'auchanassettracker'),
                'page'  => $base . '/front/allocation.form.php',
                'icon'  => 'ti ti-user-plus',
            ];
        }

        if (PluginAuchanassettrackerRighthelper::canTransfer()) {
            $menu['options']['transfer'] = [
                'title' => __('Transfers', 'auchanassettracker'),
                'page'  => $base . '/front/transfer.php',
                'icon'  => PluginAuchanassettrackerTransfer::getIcon(),
            ];
        }

        $menu['options']['confirm'] = [
            'title' => __('Confirm receipt', 'auchanassettracker'),
            'page'  => $base . '/front/confirm.php',
            'icon'  => 'ti ti-check',
        ];

        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            $menu['options']['config'] = [
                'title' => __('Configuration'),
                'page'  => $base . '/front/config.form.php',
                'icon'  => 'ti ti-settings',
            ];
            $menu['options']['equipmenttype'] = [
                'title' => PluginAuchanassettrackerEquipmenttype::getTypeName(Session::getPluralNumber()),
                'page'  => $base . '/front/equipmenttype.php',
                'icon'  => 'ti ti-list',
            ];
            $menu['options']['manufacturer'] = [
                'title' => PluginAuchanassettrackerManufacturer::getTypeName(Session::getPluralNumber()),
                'page'  => $base . '/front/manufacturer.php',
                'icon'  => 'ti ti-building-factory',
            ];
        }

        return $menu;
    }
}
