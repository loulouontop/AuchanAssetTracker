<?php

/**
 * Physical containers + Contents tabs on native GLPI Location.
 */
class PluginAuchanassettrackerLocationtab extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return __('Auchan Asset Tracker', 'auchanassettracker');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof Location) || (int) $item->getID() <= 0) {
            return '';
        }

        $id = (int) $item->getID();
        $nb_containers = 0;
        $nb_equipment = 0;
        if ($_SESSION['glpishow_count_on_tabs'] ?? true) {
            $nb_containers = countElementsInTable(
                PluginAuchanassettrackerContainer::getTable(),
                ['locations_id' => $id, 'is_deleted' => 0]
            );
            $nb_equipment = countElementsInTable(
                PluginAuchanassettrackerEquipment::getTable(),
                ['locations_id' => $id, 'is_deleted' => 0]
            );
        }

        $tabs = [];
        if (PluginAuchanassettrackerRighthelper::canSeeContainers()) {
            $tabs[1] = self::createTabEntry(
                PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
                $nb_containers,
                $item->getType(),
                PluginAuchanassettrackerContainer::getIcon()
            );
        }
        if (PluginAuchanassettrackerRighthelper::canSeeEquipment()) {
            $tabs[2] = self::createTabEntry(
                __('Contents', 'auchanassettracker'),
                $nb_equipment,
                $item->getType(),
                PluginAuchanassettrackerEquipment::getIcon()
            );
        }

        return $tabs;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof Location)) {
            return false;
        }

        return match ((int) $tabnum) {
            1 => PluginAuchanassettrackerContainer::showForLocation($item),
            2 => PluginAuchanassettrackerEquipment::showForLocation($item),
            default => false,
        };
    }
}
