<?php

/**
 * Role helpers: central_admin | location_manager | support_tech | user
 *
 * When a GLPI profile is mapped in the plugin tab, that role wins —
 * including for Super-Admin mapped as End user.
 */
class PluginAuchanassettrackerRighthelper
{
    public const ROLE_CENTRAL_ADMIN     = 'central_admin';
    public const ROLE_LOCATION_MANAGER  = 'location_manager';
    public const ROLE_SUPPORT_TECH      = 'support_tech';
    public const ROLE_USER              = 'user';

    public static function getRoles(): array
    {
        return [
            self::ROLE_CENTRAL_ADMIN    => __('Central admin', 'auchanassettracker'),
            self::ROLE_LOCATION_MANAGER => __('Location manager', 'auchanassettracker'),
            self::ROLE_SUPPORT_TECH     => __('Support technician', 'auchanassettracker'),
            self::ROLE_USER             => __('End user', 'auchanassettracker'),
        ];
    }

    public static function getCurrentRole(): string
    {
        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            $role = (string) ($mapping['role'] ?? self::ROLE_USER);
            if (isset(self::getRoles()[$role])) {
                return $role;
            }
            return self::ROLE_USER;
        }

        // No mapping: Super-Admin / config editors act as central admin.
        if (Session::haveRight('config', UPDATE)) {
            return self::ROLE_CENTRAL_ADMIN;
        }

        return self::ROLE_USER;
    }

    public static function hasPluginProfileRow(): bool
    {
        return PluginAuchanassettrackerProfile::getForCurrentProfile() !== null;
    }

    public static function isCentralAdmin(): bool
    {
        return self::getCurrentRole() === self::ROLE_CENTRAL_ADMIN;
    }

    public static function isLocationManager(): bool
    {
        $role = self::getCurrentRole();
        return in_array($role, [self::ROLE_LOCATION_MANAGER, self::ROLE_CENTRAL_ADMIN], true);
    }

    public static function isSupportTech(): bool
    {
        $role = self::getCurrentRole();
        return in_array($role, [
            self::ROLE_SUPPORT_TECH,
            self::ROLE_LOCATION_MANAGER,
            self::ROLE_CENTRAL_ADMIN,
        ], true);
    }

    public static function canManageStock(): bool
    {
        return self::isLocationManager() || self::isCentralAdmin();
    }

    public static function canAllocate(): bool
    {
        return self::isSupportTech();
    }

    /**
     * Location scope for current user.
     * null = all locations (central admin with no location mapped).
     * int  = only that location — including central admin when a location is set.
     */
    public static function getScopedLocationId(): ?int
    {
        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            $loc = (int) ($mapping['locations_id'] ?? 0);
            if ($loc > 0) {
                return $loc;
            }
            // Mapped role with empty location: central admin sees all; others see nothing.
            if (self::isCentralAdmin()) {
                return null;
            }
            return 0;
        }

        // No mapping: Super-Admin / config editors act as central admin (all locations).
        if (self::isCentralAdmin()) {
            return null;
        }

        return 0;
    }

    public static function canAccessLocation(int $locations_id): bool
    {
        $scope = self::getScopedLocationId();
        if ($scope === null) {
            return true;
        }
        return $scope === $locations_id;
    }

    /**
     * Whether the current role may pick this GLPI user as allocation recipient.
     * Scoped roles: only users whose default location matches the profile location.
     */
    public static function canAccessRecipientUser(int $users_id): bool
    {
        if ($users_id <= 0) {
            return false;
        }
        $scope = self::getScopedLocationId();
        if ($scope === null) {
            return true;
        }
        if ($scope <= 0) {
            return false;
        }
        $user = new User();
        if (!$user->getFromDB($users_id)) {
            return false;
        }
        return (int) ($user->fields['locations_id'] ?? 0) === $scope;
    }

    /**
     * User::dropdown condition for the allocation recipient picker.
     *
     * @return array<string, mixed>|null null = no extra filter (all locations)
     */
    public static function getRecipientUserDropdownCondition(): ?array
    {
        $scope = self::getScopedLocationId();
        if ($scope === null) {
            return null;
        }
        if ($scope <= 0) {
            // Mapped role with no location: empty list.
            return ['id' => -1];
        }
        return ['locations_id' => $scope];
    }

    public static function requireCanAccessLocation(int $locations_id): void
    {
        if (!self::canAccessLocation($locations_id)) {
            Session::addMessageAfterRedirect(
                __('You cannot access data from another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            Html::displayRightError();
            exit;
        }
    }
}
