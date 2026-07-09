<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams\tests;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\MicrosoftTeams\Configuration;
use Piwik\Plugins\MicrosoftTeams\Encryption;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\MicrosoftTeams\SystemSettings;
use Piwik\Tests\Framework\Fixture;

/**
 * @group MicrosoftTeams
 * @group SystemSettingsTest
 * @group Plugins
 */

class SystemSettingsTest extends IntegrationTestCase
{
    private $settings;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::loadAllTranslations();

        Fixture::createSuperUser();
        Fixture::createWebsite('2014-01-01 00:01:02');

        $this->settings = new SystemSettings();
    }

    public function testMicrosoftTeamsRequiredFieldsDefaultValue()
    {
        $this->assertEmpty($this->settings->clientID->getValue());
        $this->assertEmpty($this->settings->clientSecret->getValue());
        $this->assertEmpty($this->settings->clientSecretExpiryDate->getValue());
        $this->assertEmpty($this->settings->teamID->getValue());
        $this->assertEmpty($this->settings->tenantID->getValue());
    }

    public function testMicrosoftTeamsRequiredFieldsChangeSuccess()
    {
        $this->settings->clientID->setValue('clientID');
        $this->settings->clientSecret->setValue('clientSecret');
        $this->settings->tenantID->setValue('tenantID');
        $this->settings->teamID->setValue('teamID');

        $this->assertEquals('clientID', $this->settings->clientID->getValue());
        $this->assertEquals('clientSecret', $this->settings->clientSecret->getValue());
        $this->assertEquals('tenantID', $this->settings->tenantID->getValue());
        $this->assertEquals('teamID', $this->settings->teamID->getValue());
        $this->assertEmpty($this->settings->clientSecretExpiryDate->getValue());
        $this->assertSettingsAreEncryptedInStorage([
            'teamsClientID' => 'clientID',
            'teamsClientSecret' => 'clientSecret',
            'teamsTenantID' => 'tenantID',
            'teamsTeamID' => 'teamID',
        ]);
    }

    public function testMicrosoftTeamsClientSecretExpiryDateException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid date format, allowed date format is YYYY-MM-DD');
        $this->settings->clientSecretExpiryDate->setValue('date ');
    }

    public function testMicrosoftTeamsClientSecretExpiryDateException2()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid date format, allowed date format is YYYY-MM-DD');
        $this->settings->clientSecretExpiryDate->setValue('2202-13-40 ');
    }

    public function testMicrosoftTeamsClientSecretExpiryDateSuccess()
    {
        $this->settings->clientSecretExpiryDate->setValue('2025-11-20 ');
        $this->assertEquals('2025-11-20', $this->settings->clientSecretExpiryDate->getValue());
        $this->assertSettingsAreEncryptedInStorage([
            'teamsClientSecretExpiryDate' => '2025-11-20',
        ]);
    }

    public function testMicrosoftTeamsClientSecretExpiryDateSuccess2()
    {
        $this->settings->clientSecretExpiryDate->setValue('2025-11-21');
        $this->assertEquals('2025-11-21', $this->settings->clientSecretExpiryDate->getValue());
        $this->assertSettingsAreEncryptedInStorage([
            'teamsClientSecretExpiryDate' => '2025-11-21',
        ]);
    }

    private function assertSettingsAreEncryptedInStorage(array $expectedSettings): void
    {
        $table = Common::prefixTable('plugin_setting');
        $rows = Db::fetchAll(
            'SELECT `setting_name`, `setting_value` FROM ' . $table . ' WHERE `plugin_name` = ? AND `user_login` = ?',
            ['MicrosoftTeams', '']
        );

        $storedValues = [];
        foreach ($rows as $row) {
            $storedValues[$row['setting_name']] = $row['setting_value'];
        }

        $configuration = new Configuration();
        $configuration->install();
        $encryption = new Encryption($configuration);

        foreach ($expectedSettings as $name => $plaintextValue) {
            $this->assertArrayHasKey($name, $storedValues);
            $this->assertNotSame($plaintextValue, $storedValues[$name]);
            $this->assertTrue($encryption->isEncrypted($storedValues[$name]));
            $this->assertSame($plaintextValue, $encryption->decryptString($storedValues[$name]));
        }
    }
}
