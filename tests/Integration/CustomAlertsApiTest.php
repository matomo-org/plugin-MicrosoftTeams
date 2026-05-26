<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams\tests;

use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tests\Framework\TestingEnvironmentManipulator;
use Piwik\Plugins\CustomAlerts\API;

/**
 * @group MicrosoftTeams
 * @group CustomAlertsApiTest
 * @group Plugins
 */
class CustomAlertsApiTest extends IntegrationTestCase
{
    /**
     * @var \Piwik\Plugins\CustomAlerts\API
     */
    protected $api;

    protected $idSite;

    public function setUp(): void
    {
        self::$fixture->extraPluginsToLoad = array('CustomAlerts');
        TestingEnvironmentManipulator::$extraPluginsToLoad = self::$fixture->extraPluginsToLoad;

        parent::setUp();

        $pluginManager = \Piwik\Plugin\Manager::getInstance();
        $pluginManager->loadPlugin('CustomAlerts');
        $pluginManager->installLoadedPlugins();
        $pluginManager->activatePlugin('CustomAlerts');
        $this->api = API::getInstance();
        $this->idSite = Fixture::createWebsite('2012-08-09 11:22:33');
    }

    public function testAddAlertShouldThrowExceptionIfSMicrosoftTeamsWebhookUrlEmpty()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookRequiredErrorMessage');
        $this->addAlert('');
    }

    public function testAddAlertShouldThrowExceptionIfInvalidMicrosoftTeamsWebhookUrl()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookInvalidErrorMessage');
        $this->addAlert('webhookURL');
    }

    public function testAddAlertShouldThrowExceptionIfMicrosoftTeamsWebhookUrlUsesIntegerLoopbackAlias()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookInvalidErrorMessage');
        $this->addAlert('http://2130706433:18081/hook');
    }

    public function testAddAlertSuccess()
    {
        $id = $this->addAlert($msTeamsWebhookUrl = 'https://webhook.microsft.com/webhook');
        $this->assertEquals(1, $id);
        $alert = $this->api->getAlert($id);
        $this->assertEquals(['email','teams'], $alert['report_mediums']);
        $this->assertEquals($msTeamsWebhookUrl, $alert['ms_teams_webhook_url']);
    }

    public function testUpdateAlertShouldThrowExceptionIfEmptyMicrosoftTeamsWebhookUrl()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookRequiredErrorMessage');
        $idAlert = $this->addAlert('https://webhook.microsft.com/webhook');
        $this->updateAlert($idAlert);
    }

    public function testUpdateAlertShouldThrowExceptionIfInvalidMicrosoftTeamsWebhookUrl()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookInvalidErrorMessage');
        $idAlert = $this->addAlert('https://webhook.microsft.com/webhook');
        $this->updateAlert($idAlert, 'webhookURL');
    }

    public function testUpdateAlertShouldThrowExceptionIfMicrosoftTeamsWebhookUrlUsesIntegerLoopbackAlias()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookInvalidErrorMessage');
        $idAlert = $this->addAlert('https://webhook.microsft.com/webhook');
        $this->updateAlert($idAlert, 'http://2130706433:18081/hook');
    }

    public function testUpdateAlertSuccess()
    {
        $idAlert = $this->addAlert($msTeamsWebhookUrl = 'https://webhook.microsft.com/webhook');
        $this->updateAlert($idAlert, $msTeamsWebhookUrl . 'new');
        $alert = $this->api->getAlert($idAlert);
        $this->assertEquals(['email','teams'], $alert['report_mediums']);
        $this->assertEquals('https://webhook.microsft.com/webhooknew', $alert['ms_teams_webhook_url']);
    }

    private function addAlert($msTeamsWebhookUrl = '')
    {

        return $this->api->addAlert(
            'Test teams and Email',
            $this->idSite,
            'day',
            1,
            [],
            [],
            'nb_visits',
            'less_than',
            $metricMatched = 5,
            1,
            'MultiSites_getOne',
            'matches_exactly',
            'Piwik',
            ['email', 'teams'],
            '',
            $msTeamsWebhookUrl
        );
    }

    private function updateAlert($idAlert, $msTeamsWebhookUrl = '')
    {
        return $this->api->editAlert(
            $idAlert,
            'Test teams and Email',
            $this->idSite,
            'day',
            1,
            [],
            [],
            'nb_visits',
            'less_than',
            $metricMatched = 5,
            1,
            'MultiSites_getOne',
            'matches_exactly',
            'Piwik',
            ['email', 'teams'],
            '',
            $msTeamsWebhookUrl
        );
    }
}
