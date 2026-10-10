<?php

/**
 * Role helpers: central_admin | location_manager | support_tech | user
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
        if (Session::haveRight('config', UPDATE) && !self::hasPluginProfileRow()) {
            // Super-admins without explicit mapping act as central admin.
            return self::ROLE_CENTRAL_ADMIN;
        }

        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            return (string) ($mapping['role'] ?? self::ROLE_USER);
        }

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

    public static function canTransfer(): bool
    {
        return self::isSupportTech();
    }

    public static function canWriteOff(): bool
    {
        return self::isLocationManager() || self::isCentralAdmin();
    }

    public static function canChangeFinalStatus(): bool
    {
        return self::isCentralAdmin();
    }

    /**
     * Location scope for current user. null = all locations (central admin).
     */
    public static function getScopedLocationId(): ?int
    {
        if (self::isCentralAdmin()) {
            return null;
        }

        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            $loc = (int) ($mapping['locations_id'] ?? 0);
            return $loc > 0 ? $loc : 0;
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
