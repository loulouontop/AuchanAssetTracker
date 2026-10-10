<?php

/**
 * Confirm receipt page (native GLPI form chrome).
 */
class PluginAuchanassettrackerConfirm extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return __('Confirm receipt', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_allocations';
    }

    public static function getIcon(): string
    {
        return 'ti ti-check';
    }

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_CONFIRM];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/confirm.php';
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    public static function canCreate(): bool
    {
        return self::canView();
    }

    public function canCreateItem(): bool
    {
        return self::canCreate();
    }

    public function canViewItem(): bool
    {
        return self::canView();
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm(-1, $options);
        PluginAuchanassettrackerMenu::beginNativeFormCard(self::getTypeName(1), self::getIcon());
        self::renderConfirmWorkspace();
        PluginAuchanassettrackerMenu::endNativeFormCard();
        return true;
    }

    public static function renderConfirmWorkspace(): void
    {
        $uid = (int) Session::getLoginUserID();
        $base = plugin_auchanassettracker_web_dir();
        $focus_id = (int) ($_GET['allocation_id'] ?? 0);

        $pending = PluginAuchanassettrackerAllocation::getPendingForUser($uid);

        if ($focus_id > 0) {
            $focus = new PluginAuchanassettrackerAllocation();
            if ($focus->getFromDB($focus_id)
                && ($focus->fields['allocation_status'] ?? '') === PluginAuchanassettrackerAllocation::STATUS_PENDING
            ) {
                $is_recipient = (int) ($focus->fields['users_id_recipient'] ?? 0) === $uid;
                $can_see = $is_recipient
                    || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                    || PluginAuchanassettrackerRighthelper::canAllocate();
                if ($can_see) {
                    $already = false;
                    foreach ($pending as $p) {
                        if ((int) ($p['id'] ?? 0) === $focus_id) {
                            $already = true;
                            break;
                        }
                    }
                    if (!$already) {
                        array_unshift($pending, $focus->fields);
                    }
                }
            }
        }

        $mine = PluginAuchanassettrackerAllocation::getCurrentGearForUser($uid);

        echo "<div class='aat-workspace'>";
        echo "<h3 class='fs-5'>" . __('Equipment awaiting your confirmation', 'auchanassettracker') . "</h3>";
        if ($pending === []) {
            echo "<p class='text-muted'>" . __('Nothing to confirm.', 'auchanassettracker') . "</p>";
        } else {
            echo "<div class='table-responsive mb-4'><table class='table table-sm table-hover'>";
            echo "<thead><tr><th>" . __('Equipment') . "</th><th>" . __('Type') . "</th><th>"
                . __('Serial number') . "</th><th>" . __('Allocated on', 'auchanassettracker')
                . "</th><th>" . __('Actions') . "</th></tr></thead><tbody>";
            foreach ($pending as $a) {
                $aid = (int) ($a['id'] ?? 0);
                $eq = new PluginAuchanassettrackerEquipment();
                $eq->getFromDB((int) $a['plugin_auchanassettracker_equipments_id']);
                $type = (string) ($eq->fields['itemtype'] ?? '');
                $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
                $eq_row = array_merge($eq->fields, ['source' => 'plugin']);
                echo "<tr><td>" . PluginAuchanassettrackerAllocation::equipmentNameLink($eq_row)
                    . "</td><td>" . Html::entities_deep($type_label)
                    . "</td><td>" . Html::entities_deep((string) ($eq->fields['serial'] ?? ''))
                    . "</td><td>" . Html::entities_deep((string) ($a['allocation_date'] ?? ''))
                    . "</td><td><form method='post' action='' class='d-inline'>";
                echo Html::hidden('allocation_id', ['value' => $aid]);
                echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
                $can_act = ((int) ($a['users_id_recipient'] ?? 0) === $uid)
                    || PluginAuchanassettrackerRighthelper::isCentralAdmin();
                if ($can_act) {
                    echo Html::submit(__('Confirm receipt', 'auchanassettracker'), [
                        'name'  => 'confirm',
                        'class' => 'btn btn-success btn-sm',
                    ]);
                    echo " ";
                    echo Html::submit(__('Did not receive', 'auchanassettracker'), [
                        'name'  => 'reject',
                        'class' => 'btn btn-outline-danger btn-sm',
                    ]);
                } else {
                    echo "<span class='text-muted'>"
                        . __('Waiting for the recipient to confirm.', 'auchanassettracker')
                        . "</span>";
                }
                Html::closeForm();
                echo "</td></tr>";
            }
            echo "</tbody></table></div>";
        }

        echo "<h3 class='fs-5'>" . __('My equipment', 'auchanassettracker') . "</h3>";
        if ($mine === []) {
            echo "<p class='text-muted mb-0'>" . __('None.', 'auchanassettracker') . "</p>";
        } else {
            $parts = [];
            foreach ($mine as $e) {
                $parts[] = PluginAuchanassettrackerAllocation::equipmentNameLink($e);
            }
            echo "<p class='mb-0'>" . implode('<span class="text-muted"> · </span>', $parts) . "</p>";
        }
        echo "</div>";
    }
}
