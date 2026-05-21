<?php

use OpenEMR\Core\AbstractModuleActionListener;

/**
 * Laminas Module Manager listener for paho_openemr_translations.
 *
 * Walking-skeleton release (Slice 01): all action handlers are no-op
 * pass-throughs that return $currentActionStatus. The state-table DDL in
 * sql/install.sql is applied by OpenEMR's module manager automatically when
 * the admin clicks Install (filesystem convention); this listener exists as
 * the contract surface that future slices will use for post-install
 * verification and upgrade migrations.
 *
 * Per OpenEMR Laminas convention, this file declares no namespace; the
 * module's namespace is reported via getModuleNamespace().
 *
 * @package OpenEMR Modules
 * @license GNU General Public License 3
 */

class ModuleManagerListener extends AbstractModuleActionListener
{
    /**
     * @param  string $methodName
     * @param  mixed  $modId
     * @param  string $currentActionStatus
     * @return string Status string on success, or error description.
     */
    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {
        if (method_exists(self::class, $methodName)) {
            return self::$methodName($modId, $currentActionStatus);
        }

        return "Module action method $methodName does not exist.";
    }

    public static function getModuleNamespace(): string
    {
        return 'OpenEMR\\Modules\\PahoOpenemrTranslations\\';
    }

    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }

    private function install($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function enable($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function disable($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function unregister($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function install_sql($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function upgrade_sql($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }
}
