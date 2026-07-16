<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams;

use Piwik\Common;
use Piwik\Db;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

class Updates_5_2_0 extends PiwikUpdates
{
    private const SETTING_NAMES = [
        'teamsClientID',
        'teamsClientSecret',
        'teamsClientSecretExpiryDate',
        'teamsTenantID',
        'teamsTeamID',
    ];

    public function doUpdate(Updater $updater)
    {
        $configuration = new Configuration();
        $configuration->install();

        $encryption = new Encryption($configuration);
        $table = Common::prefixTable('plugin_setting');
        $rows = Db::fetchAll(
            'SELECT `setting_name`, `setting_value` FROM ' . $table . ' WHERE `plugin_name` = ? AND `user_login` = ? AND `setting_name` IN (?, ?, ?, ?, ?)',
            array_merge(['MicrosoftTeams', ''], self::SETTING_NAMES)
        );

        foreach ($rows as $row) {
            $value = $row['setting_value'] ?? '';
            $settingName = $row['setting_name'] ?? '';

            if (!is_string($value) || $value === '' || !is_string($settingName) || $settingName === '' || $encryption->isEncrypted($value)) {
                continue;
            }

            Db::query(
                'UPDATE ' . $table . ' SET `setting_value` = ? WHERE `plugin_name` = ? AND `user_login` = ? AND `setting_name` = ? AND `setting_value` = ?',
                [$encryption->encryptString($value), 'MicrosoftTeams', '', $settingName, $value]
            );
        }
    }
}
