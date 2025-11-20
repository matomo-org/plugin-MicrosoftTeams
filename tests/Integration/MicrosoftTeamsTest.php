<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams\tests;

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\ScheduledReports\API;
use Piwik\Plugins\ScheduledReports\ScheduledReports;
use Piwik\Plugins\MicrosoftTeams\MicrosoftTeams;
use Piwik\Plugins\MicrosoftTeams\SystemSettings;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group MicrosoftTeams
 * @group MicrosoftTeamsTest
 * @group Plugins
 */
class ScheduledReportsTest extends IntegrationTestCase
{
    /**
     * @var ScheduledReports
     */
    private $reports;
    private $reportIds = array();

    public function setUp(): void
    {
        parent::setUp();

        $this->reports = new ScheduledReports();
        $this->setIdentity('userlogin');

        \Piwik\Plugin\Manager::getInstance()->loadPlugins(array('ScheduledReports', 'MicrosoftTeams'));
        \Piwik\Plugin\Manager::getInstance()->installLoadedPlugins();

        for ($i = 1; $i <= 4; $i++) {
            Fixture::createWebsite('2014-01-01 00:00:00');
        }
    }

    public function testAddReportShouldFailWhenRequiredFieldsNotSet()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_RequiredFieldsNotSet');

        $this->addReport('userlogin', 1);
    }

    public function testAddReportSuccess()
    {
        $this->setRequiredFields();

        $this->addReport($userLogin = 'userlogin', $idSite = 1);
        $this->assertHasReport($userLogin, $idSite);
    }

    public function testAddReportSuccessCsv()
    {
        $this->setRequiredFields();

        $this->addReport($userLogin = 'userlogin', $idSite = 2, 'csv');
        $this->assertHasReport($userLogin, $idSite);
    }

    public function testAddReportSuccessTsv()
    {
        $this->setRequiredFields();

        $this->addReport($userLogin = 'userlogin', $idSite = 3, 'tsv');
        $this->assertHasReport($userLogin, $idSite);
    }

    public function testAddReportFailureWhenInvalidReportType()
    {
        $this->setRequiredFields();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('General_ExceptionInvalidReportRendererFormat');

        $this->addReport($userLogin = 'userlogin', $idSite = 3, 'html');
    }

    /**
     * @dataProvider getAlertData
     */
    public function testGetAlertMessage($alert, $expectedMessage)
    {
        $microsoftTeams = StaticContainer::get(MicrosoftTeams::class);
        Fixture::loadAllTranslations();
        $this->assertSame($expectedMessage, $microsoftTeams->getAlertMessage($alert, 'Unique Visitors', 'Visits Summary'));
    }

    public function getAlertData()
    {
        return [
            [['name' => 'Alert1', 'siteName' => 'test.com', 'metric_condition' => 'less_than', 'metric_matched' => '5', 'value_new' => '1', 'value_old' => '2', 'idsite' => 1], 'Alert1 has been triggered for website <a href=\'http://localhost.matomo.com/tests/PHPUnit/proxy/index.php?idSite=1\'>test.com</a> as the metric Unique Visitors in report Visits Summary is 1 which is less than 5.'],
            [['name' => 'Alert2', 'siteName' => 'test.com', 'metric_condition' => 'greater_than', 'metric_matched' => '8', 'value_new' => '10', 'value_old' => '2', 'idsite' => 1], 'Alert2 has been triggered for website <a href=\'http://localhost.matomo.com/tests/PHPUnit/proxy/index.php?idSite=1\'>test.com</a> as the metric Unique Visitors in report Visits Summary is 10 which is greater than 8.'],
            [['name' => 'Alert3', 'siteName' => 'test.com', 'metric_condition' => 'decrease_more_than', 'metric_matched' => '5', 'value_new' => '8', 'value_old' => '15', 'idsite' => 1], 'Alert3 has been triggered for website <a href=\'http://localhost.matomo.com/tests/PHPUnit/proxy/index.php?idSite=1\'>test.com</a> as the metric Unique Visitors in report Visits Summary decreased more than 5 from 15 to 8.'],
            [['name' => 'Alert4', 'siteName' => 'test.com', 'metric_condition' => 'increase_more_than', 'metric_matched' => '5', 'value_new' => '30', 'value_old' => '20', 'idsite' => 1], 'Alert4 has been triggered for website <a href=\'http://localhost.matomo.com/tests/PHPUnit/proxy/index.php?idSite=1\'>test.com</a> as the metric Unique Visitors in report Visits Summary increased more than 5 from 20 to 30.'],
            [['name' => 'Alert5', 'siteName' => 'test.com', 'metric_condition' => 'percentage_decrease_more_than', 'metric_matched' => '5', 'value_new' => '8', 'value_old' => '15', 'idsite' => 1], 'Alert5 has been triggered for website <a href=\'http://localhost.matomo.com/tests/PHPUnit/proxy/index.php?idSite=1\'>test.com</a> as the metric Unique Visitors in report Visits Summary decreased more than 5% from 15 to 8.'],
            [['name' => 'Alert6', 'siteName' => 'test.com', 'metric_condition' => 'percentage_increase_more_than', 'metric_matched' => '5', 'value_new' => '30', 'value_old' => '20', 'idsite' => 1], 'Alert6 has been triggered for website <a href=\'http://localhost.matomo.com/tests/PHPUnit/proxy/index.php?idSite=1\'>test.com</a> as the metric Unique Visitors in report Visits Summary increased more than 5% from 20 to 30.'],
        ];
    }

    public function testValidateReportParametersRequiredFieldsNotSetShouldThrowOauthException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_RequiredFieldsNotSet');
        $parameters = [ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DISPLAY_FORMAT_GRAPHS_ONLY];
        Piwik::postEvent('ScheduledReports.validateReportParameters', [&$parameters, 'teams']);
    }

    public function testValidateReportParametersShouldThrowMicrosoftTeamsWebhookUrlEmptyException()
    {
        $this->setRequiredFields();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookRequiredErrorMessage');
        $parameters = [ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DISPLAY_FORMAT_GRAPHS_ONLY];
        Piwik::postEvent('ScheduledReports.validateReportParameters', [&$parameters, 'teams']);
    }

    public function testValidateReportParametersShouldThrowMicrosoftTeamsWebhookUrInvalidException()
    {
        $this->setRequiredFields();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookInvalidErrorMessage');
        $parameters = [ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DISPLAY_FORMAT_GRAPHS_ONLY, MicrosoftTeams::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => 'WEBHOOK_URL'];
        Piwik::postEvent('ScheduledReports.validateReportParameters', [&$parameters, 'teams']);
    }

    public function testValidateReportParametersShouldThrowMicrosoftTeamsWebhookUrlEmptyException2()
    {
        $this->setRequiredFields();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MicrosoftTeams_IncomingWebhookRequiredErrorMessage');
        $parameters = [ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DISPLAY_FORMAT_GRAPHS_ONLY, MicrosoftTeams::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => ''];
        Piwik::postEvent('ScheduledReports.validateReportParameters', [&$parameters, 'teams']);
    }

    public function testValidateReportParametersMicrosoftTeamsShouldNotThrowAnyException()
    {
        $this->setRequiredFields();
        $parameters = [ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DISPLAY_FORMAT_GRAPHS_ONLY, MicrosoftTeams::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => 'https://WEBHOK_URL'];
        Piwik::postEvent('ScheduledReports.validateReportParameters', [&$parameters, 'teams']);
        $this->assertNotEmpty($parameters);
    }

    public function testValidateReportParametersMicrosoftTeamsShouldNotThrowAnyException2()
    {
        $this->setRequiredFields();
        $parameters = [ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DISPLAY_FORMAT_GRAPHS_ONLY, MicrosoftTeams::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => 'http://WEBHOK_URL'];
        Piwik::postEvent('ScheduledReports.validateReportParameters', [&$parameters, 'teans']);
        $this->assertNotEmpty($parameters);
    }

    private function assertHasReport($login, $idSite)
    {
        $report = $this->getReport($login, $idSite);

        $this->assertNotEmpty($report, "Report for $login, $idSite should exist but does not");
    }

    private function getReport($login, $idSite)
    {
        $this->setIdentity($login);

        return API::getInstance()->getReports($idSite, 'day', $this->reportIds[$login . '_' . $idSite]);
    }

    private function addReport($login, $idSite, $reportFormat = 'pdf')
    {
        $this->setIdentity($login);

        $reportType   = 'teams';
        $reports      = array();
        $parameters   = array(
            MicrosoftTeams::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => 'https://WEBHOOK_URL',
            ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DEFAULT_DISPLAY_FORMAT,
            ScheduledReports::EVOLUTION_GRAPH_PARAMETER => ScheduledReports::EVOLUTION_GRAPH_PARAMETER_DEFAULT_VALUE,
        );

        $reportId = API::getInstance()->addReport($idSite, 'description', 'day', 3, $reportType, $reportFormat, $reports, $parameters);
        $this->reportIds[$login . '_' . $idSite] = $reportId;
    }

    private function setIdentity($login)
    {
        FakeAccess::$identity  = $login;
        FakeAccess::$superUser = true;
    }

    private function setRequiredFields()
    {
        $settings = StaticContainer::get(SystemSettings::class);
        $settings->clientID->setValue('clientID');
        $settings->clientSecret->setValue('clientSecret');
        $settings->tenantID->setValue('tenantID');
        $settings->teamID->setValue('teamID');
    }

    public function provideContainerConfig()
    {
        return array(
            'Piwik\Access' => new FakeAccess(),
        );
    }
}
