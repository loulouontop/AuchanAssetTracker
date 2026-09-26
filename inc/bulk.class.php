<?php

/**
 * Bulk accessories receipt form (native GLPI display chrome).
 *
 * Reuses the equipment table schema for entity fields / form header badge.
 */
class PluginAuchanassettrackerBulk extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return __('Bulk add accessories', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_equipments';
    }

    public static function getIcon(): string
    {
        return 'ti ti-stack-2';
    }

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_BULK];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.bulk.php';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.php';
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm(-1, $options);

        $options['formtitle'] = self::getTypeName(1);
        $options['target']    = self::getFormURL();
        $options['candel']    = false;
        $options['canedit']   = true;

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

        $type_choices = [];
        foreach (PluginAuchanassettrackerEquipment::getAllowedAssetTypes() as $class) {
            $type_choices[$class] = $class::getTypeName(1);
        }
        $default_type = isset($type_choices['Peripheral'])
            ? 'Peripheral'
            : (string) array_key_first($type_choices);

        $loc = $scope ?? 0;
        $cond = ['is_deleted' => 0];
        if ($loc > 0) {
            $cond['locations_id'] = $loc;
        } else {
            $cond['locations_id'] = -1;
        }

        ob_start();
        echo "<span class='aat-container-field' data-aat-width='220px'>";
        PluginAuchanassettrackerContainer::dropdownWithActions([
            'name'          => 'plugin_auchanassettracker_containers_id',
            'condition'     => $cond,
            'width'         => '220px',
            'sync_location' => ($scope === null),
        ]);
        echo "</span>";
        $container_dropdown = ob_get_clean();

        ob_start();
        echo "<span class='aat-model-field'>";
        PluginAuchanassettrackerEquipment::dropdownModel([
            'itemtype' => $default_type,
            'value'    => 0,
            'width'    => '220px',
        ]);
        echo "</span>";
        PluginAuchanassettrackerEquipment::scriptSyncItemtypeModel();
        $model_dropdown = ob_get_clean();

        $location_label = '';
        if ($scope !== null) {
            $location_label = Dropdown::getDropdownName('glpi_locations', $scope);
        }

        \Glpi\Application\View\TemplateRenderer::getInstance()->display(
            '@' . plugin_auchanassettracker_dir() . '/bulk.html.twig',
            [
                'item'               => $this,
                'params'             => $options,
                'field_options'      => [],
                'scope'              => $scope,
                'scope_help'         => __('Fixed from your profile location.', 'auchanassettracker'),
                'location_label'     => $location_label,
                'type_choices'       => $type_choices,
                'default_type'       => $default_type,
                'container_dropdown' => $container_dropdown,
                'model_dropdown'     => $model_dropdown,
            ]
        );

        return true;
    }

    public function canCreateItem(): bool
    {
        return PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    public static function canCreate(): bool
    {
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    public static function canUpdate(): bool
    {
        return self::canCreate();
    }

    public function canUpdateItem(): bool
    {
        return $this->canCreateItem();
    }

    public function canViewItem(): bool
    {
        return true;
    }
}
