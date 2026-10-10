<?php

/**
 * Dedicated log file: GLPI_LOG_DIR/plugin_auchanassettracker.log
 */
class PluginAuchanassettrackerPluginlog
{
    public const LOG_FILE = 'plugin_auchanassettracker';

    /**
     * Prefer GLPI login name over bare numeric id in log lines.
     */
    public static function userRef(int $users_id): string
    {
        $login = PluginAuchanassettrackerAuditlog::userLogin($users_id);
        if ($login !== '') {
            return $login;
        }
        return $users_id > 0 ? ('user#' . $users_id) : 'unknown';
    }

    public static function log(string $message, string $level = 'INFO'): void
    {
        $actor_id = (int) Session::getLoginUserID();
        $actor = $actor_id > 0 ? self::userRef($actor_id) : '-';
        $line = sprintf(
            "[%s] [%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $actor,
            $message
        );
        Toolbox::logInFile(self::LOG_FILE, $line);
    }

    public static function info(string $message): void
    {
        self::log($message, 'INFO');
    }

    public static function warning(string $message): void
    {
        self::log($message, 'WARNING');
    }

    public static function error(string $message): void
    {
        self::log($message, 'ERROR');
    }

    public static function exception(Throwable $e, string $context = ''): void
    {
        $prefix = $context !== '' ? $context . ': ' : '';
        self::error($prefix . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
