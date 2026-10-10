<?php

class PluginAuchanassettrackerTransferitem extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return _n('Transfer item', 'Transfer items', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_transferitems';
    }
}
