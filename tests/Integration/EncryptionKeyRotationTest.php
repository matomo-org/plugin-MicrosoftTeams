<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams\tests;

use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\CoreAdminHome\EncryptionKeyRotator;
use Piwik\Plugins\CoreAdminHome\tests\Framework\Mock\FileBackedConfig;
use Piwik\Plugins\MicrosoftTeams\Configuration;
use Piwik\Plugins\MicrosoftTeams\Encryption;
use Piwik\Plugins\MicrosoftTeams\Exceptions\SecretConfigurationException;
use Piwik\Plugins\MicrosoftTeams\SystemSettings;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group MicrosoftTeams
 * @group EncryptionKeyRotationTest
 * @group Plugins
 */
class EncryptionKeyRotationTest extends IntegrationTestCase
{
    private $backupConfig = [];

    /**
     * @var FileBackedConfig|null
     */
    private $config;

    public function setUp(): void
    {
        parent::setUp();

        $this->backupConfig = Config::getInstance()->MicrosoftTeams ?: [];

        // the rotation command is only available from the Matomo release that introduced it
        if (!class_exists(EncryptionKeyRotator::class)) {
            $this->markTestSkipped('Encryption key rotation is not available in this Matomo version.');
        }

        Config::getInstance()->MicrosoftTeams = [Configuration::KEY_ENCRYPTION_KEY => 'old-key'];
        $this->config = FileBackedConfig::replaceTestConfig();

        Fixture::loadAllTranslations();
        Fixture::createSuperUser();
    }

    public function tearDown(): void
    {
        if ($this->config !== null) {
            $this->config->deleteFile();
        }

        Config::getInstance()->MicrosoftTeams = $this->backupConfig;

        parent::tearDown();
    }

    public function testRotationReEncryptsEveryTeamsSettingWithTheNewKey()
    {
        $expected = [
            'teamsClientID' => 'clientID',
            'teamsClientSecret' => 'clientSecret',
            'teamsClientSecretExpiryDate' => '2025-11-21',
            'teamsTenantID' => 'tenantID',
            'teamsTeamID' => 'teamID',
        ];

        $settings = new SystemSettings();
        $settings->clientID->setValue($expected['teamsClientID']);
        $settings->clientSecret->setValue($expected['teamsClientSecret']);
        $settings->clientSecretExpiryDate->setValue($expected['teamsClientSecretExpiryDate']);
        $settings->tenantID->setValue($expected['teamsTenantID']);
        $settings->teamID->setValue($expected['teamsTeamID']);
        $settings->save();
        $oldClientSecret = $this->getStoredValue('teamsClientSecret');

        $targets = [];
        Piwik::postEvent('CoreAdminHome.getEncryptionKeyRotationTargets', [&$targets]);
        $count = (new EncryptionKeyRotator())->rotate('MicrosoftTeams', $targets['MicrosoftTeams']);

        $this->assertSame(5, $count);

        $newKey = Config::getInstance()->MicrosoftTeams[Configuration::KEY_ENCRYPTION_KEY];
        $this->assertSame($newKey, $this->config->readFile()['MicrosoftTeams'][Configuration::KEY_ENCRYPTION_KEY]);
        $this->assertNotSame('old-key', $newKey);

        $newEncryption = Encryption::withKey($newKey);
        foreach ($expected as $settingName => $value) {
            $this->assertSame($value, $newEncryption->decryptString($this->getStoredValue($settingName)));
        }
        $this->assertNotSame($oldClientSecret, $this->getStoredValue('teamsClientSecret'));
        $this->assertSame('clientSecret', (new SystemSettings())->clientSecret->getValue());

        $this->expectException(SecretConfigurationException::class);
        Encryption::withKey('old-key')->decryptString($this->getStoredValue('teamsClientSecret'));
    }

    private function getStoredValue(string $settingName): string
    {
        return Db::fetchOne(
            'SELECT `setting_value` FROM `' . Common::prefixTable('plugin_setting') . '` WHERE `plugin_name` = ? AND `user_login` = ? AND `setting_name` = ?',
            ['MicrosoftTeams', '', $settingName]
        );
    }
}
