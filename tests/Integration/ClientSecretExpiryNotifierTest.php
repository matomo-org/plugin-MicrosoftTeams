<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\MicrosoftTeams\tests;

use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Mail;
use Piwik\Piwik;
use Piwik\Plugins\MicrosoftTeams\ClientSecretExpiryNotifier;
use Piwik\Plugins\MicrosoftTeams\MicrosoftTeams;
use Piwik\Plugins\MicrosoftTeams\MicrosoftTeamsApi;
use Piwik\Plugins\MicrosoftTeams\ScheduleReportMicrosoftTeams;
use Piwik\Plugins\MicrosoftTeams\SystemSettings;
use Piwik\Plugins\MicrosoftTeams\Tasks as MicrosoftTeamsTasks;
use Piwik\Plugins\ScheduledReports\API as ScheduledReportsApi;
use Piwik\Plugins\ScheduledReports\ScheduledReports;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Piwik\Plugins\UsersManager\Model as UsersManagerModel;
use Piwik\Scheduler\Schedule\Daily;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group MicrosoftTeams
 * @group ClientSecretExpiryNotifierTest
 * @group Plugins
 */
class ClientSecretExpiryNotifierTest extends IntegrationTestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private $sentEmails = [];

    /**
     * @var string
     */
    private $suffix;

    public function setUp(): void
    {
        parent::setUp();

        $this->suffix = substr(md5((string)microtime(true) . (string)mt_rand()), 0, 8);
        FakeAccess::$identity = $this->login('root');
        FakeAccess::$superUser = true;
        Config::getInstance()->General['emails_enabled'] = 1;

        \Piwik\Plugin\Manager::getInstance()->loadPlugins(['UsersManager', 'ScheduledReports', 'MicrosoftTeams']);
        \Piwik\Plugin\Manager::getInstance()->installLoadedPlugins();
        Fixture::loadAllTranslations();

        SitesManagerApi::getInstance()->addSite('Test', ['http://example.org']);
        FakeAccess::setIdSitesView([1]);
        FakeAccess::$superUser = true;

        $this->createUsers();
        $this->setRequiredFields();

        Piwik::addAction('Mail.send', function (Mail $mail): void {
            $this->sentEmails[] = [
                'body' => $mail->getBodyText(),
                'html' => $mail->getBodyHtml(),
                'from' => $mail->getFrom(),
                'recipients' => array_keys($mail->getRecipients()),
                'subject' => $mail->getSubject(),
            ];
        });
    }

    public function tearDown(): void
    {
        Date::$now = null;
        ScheduledReportsApi::$cache = [];

        parent::tearDown();
    }

    /**
     * @dataProvider getNoticeDays
     */
    public function testShouldSendEmailsOnlyOnNoticeDays(int $daysUntilExpiry): void
    {
        $this->setExpiryDaysFromNow($daysUntilExpiry);

        $this->notifier()->sendNotificationsIfDue();

        $this->assertCount(2, $this->sentEmails);
    }

    public function getNoticeDays(): array
    {
        return [
            [28],
            [21],
            [14],
            [7],
            [0],
        ];
    }

    /**
     * @dataProvider getNonNoticeDays
     */
    public function testShouldSkipEmailsWhenNotOnNoticeDay(int $daysUntilExpiry): void
    {
        $this->setExpiryDaysFromNow($daysUntilExpiry);

        $this->notifier()->sendNotificationsIfDue();

        $this->assertSame([], $this->sentEmails);
    }

    public function testShouldSkipEmailsWhenExpiryDateIsNotConfigured(): void
    {
        StaticContainer::get(SystemSettings::class)->clientSecretExpiryDate->setValue('');

        $this->notifier()->sendNotificationsIfDue();

        $this->assertSame([], $this->sentEmails);
    }

    public function getNonNoticeDays(): array
    {
        return [
            [31],
            [27],
            [6],
            [-1],
        ];
    }

    public function testShouldSendOneEmailPerUserWithRecipientSpecificBody(): void
    {
        $this->setExpiryDaysFromNow(7);
        $this->addTeamsReport($this->login('owner1'));
        $this->addTeamsReport($this->login('owner1'));
        $this->addTeamsReport($this->login('superuser1'));
        $this->addEmailReport($this->login('owner2'));

        $this->notifier()->sendNotificationsIfDue();

        $emailsByRecipient = $this->indexEmailsByRecipient();
        $this->assertSame([$this->email('owner1'), $this->email('super1'), $this->email('super2')], array_keys($emailsByRecipient));
        $this->assertSame('Final reminder: Microsoft Teams client secret expires soon', $emailsByRecipient[$this->email('super1')]['subject']);
        $this->assertSame('Final reminder: Microsoft Teams integration expires soon', $emailsByRecipient[$this->email('owner1')]['subject']);
        $this->assertStringContainsString('update the client secret before the expiry date', $emailsByRecipient[$this->email('super1')]['body']);
        $this->assertStringContainsString('update the client secret before the expiry date', $emailsByRecipient[$this->email('super2')]['body']);
        $this->assertStringContainsString('Please contact your Matomo superuser', $emailsByRecipient[$this->email('owner1')]['body']);
        $this->assertStringContainsString('2025-01-08', $emailsByRecipient[$this->email('owner1')]['body']);
        $this->assertStringContainsString('<p>', $emailsByRecipient[$this->email('owner1')]['html']);
        $this->assertStringContainsString('<br />', $emailsByRecipient[$this->email('owner1')]['html']);
        $this->assertStringContainsString('Please contact your Matomo superuser', $emailsByRecipient[$this->email('owner1')]['html']);
    }

    public function testTeamsReportSenderShouldNotHaveClientSecretExpiryNoteHook(): void
    {
        $this->assertFalse(method_exists(ScheduleReportMicrosoftTeams::class, 'getTokenExpiryNoteIfNearExpiring'));
    }

    public function testTeamsReportSenderShouldUseUploadFileWithoutAdditionalExpiryNote(): void
    {
        $method = new \ReflectionMethod(MicrosoftTeamsApi::class, 'uploadFile');
        $parameters = $method->getParameters();

        $this->assertSame('additionalNote', $parameters[4]->getName());
        $this->assertTrue($parameters[4]->isDefaultValueAvailable());
        $this->assertSame('', $parameters[4]->getDefaultValue());
    }

    public function testShouldRegisterDailyClientSecretExpiryTask(): void
    {
        $tasks = new MicrosoftTeamsTasks($this->notifier());

        $tasks->schedule();

        $scheduledTasks = $tasks->getScheduledTasks();
        $this->assertCount(1, $scheduledTasks);
        $this->assertSame('sendClientSecretExpiryNotifications', $scheduledTasks[0]->getMethodName());
        $this->assertNull($scheduledTasks[0]->getMethodParameter());
        $this->assertInstanceOf(Daily::class, $scheduledTasks[0]->getScheduledTime());
    }

    public function testScheduledTaskShouldSendDueNotifications(): void
    {
        $this->setExpiryDaysFromNow(28);
        $tasks = new MicrosoftTeamsTasks($this->notifier());

        $tasks->sendClientSecretExpiryNotifications();

        $this->assertCount(2, $this->sentEmails);
    }

    /**
     * @dataProvider getNoticeSubjects
     */
    public function testShouldUseNoticeSpecificSubjects(
        int $daysUntilExpiry,
        string $expectedSuperUserSubject,
        string $expectedOwnerSubject
    ): void {
        $this->setExpiryDaysFromNow($daysUntilExpiry);
        $this->addTeamsReport($this->login('owner1'));

        $this->notifier()->sendNotificationsIfDue();

        $emailsByRecipient = $this->indexEmailsByRecipient();
        $this->assertSame($expectedSuperUserSubject, $emailsByRecipient[$this->email('super1')]['subject']);
        $this->assertSame($expectedOwnerSubject, $emailsByRecipient[$this->email('owner1')]['subject']);
    }

    public function getNoticeSubjects(): array
    {
        return [
            [28, 'Microsoft Teams client secret expires in 4 weeks', 'Microsoft Teams integration expires in 4 weeks'],
            [21, 'Microsoft Teams client secret expires in 3 weeks', 'Reminder: Microsoft Teams integration may require update'],
            [14, 'Upcoming expiry: Microsoft Teams client secret', 'Upcoming expiry: Check Microsoft Teams integration'],
            [7, 'Final reminder: Microsoft Teams client secret expires soon', 'Final reminder: Microsoft Teams integration expires soon'],
            [0, 'Action required: Microsoft Teams client secret has expired', 'Microsoft Teams integration has expired'],
        ];
    }

    private function createUsers(): void
    {
        $api = UsersManagerApi::getInstance();
        $api->addUser($this->login('superuser1'), 'password1', $this->email('super1'), false);
        $api->addUser($this->login('superuser2'), 'password2', $this->email('super2'), false);
        $api->addUser($this->login('owner1'), 'password3', $this->email('owner1'), false);
        $api->addUser($this->login('owner2'), 'password4', $this->email('owner2'), false);

        $userModel = new UsersManagerModel();
        $userModel->setSuperUserAccess($this->login('superuser1'), true);
        $userModel->setSuperUserAccess($this->login('superuser2'), true);
    }

    private function setRequiredFields(): void
    {
        $settings = StaticContainer::get(SystemSettings::class);
        $settings->clientID->setValue('clientID');
        $settings->clientSecret->setValue('clientSecret');
        $settings->tenantID->setValue('tenantID');
        $settings->teamID->setValue('teamID');
    }

    private function setExpiryDaysFromNow(int $daysUntilExpiry): void
    {
        Date::$now = Date::factory('2025-01-01')->getTimestamp();
        StaticContainer::get(SystemSettings::class)
            ->clientSecretExpiryDate
            ->setValue(Date::today()->addDay($daysUntilExpiry)->toString());
    }

    private function addTeamsReport(string $login): void
    {
        $this->addReport($login, MicrosoftTeams::MS_TEAMS_TYPE, [
            MicrosoftTeams::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => 'https://example.org/webhook',
            ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DEFAULT_DISPLAY_FORMAT,
            ScheduledReports::EVOLUTION_GRAPH_PARAMETER => ScheduledReports::EVOLUTION_GRAPH_PARAMETER_DEFAULT_VALUE,
        ]);
    }

    private function addEmailReport(string $login): void
    {
        $this->addReport($login, ScheduledReports::EMAIL_TYPE, [
            ScheduledReports::EMAIL_ME_PARAMETER => true,
            ScheduledReports::ADDITIONAL_EMAILS_PARAMETER => [],
            ScheduledReports::DISPLAY_FORMAT_PARAMETER => ScheduledReports::DEFAULT_DISPLAY_FORMAT,
            ScheduledReports::EVOLUTION_GRAPH_PARAMETER => ScheduledReports::EVOLUTION_GRAPH_PARAMETER_DEFAULT_VALUE,
        ]);
    }

    private function addReport(string $login, string $type, array $parameters): void
    {
        FakeAccess::$identity = $login;
        ScheduledReportsApi::$cache = [];

        ScheduledReportsApi::getInstance()->addReport(1, 'description', 'day', 3, $type, 'pdf', [], $parameters);

        FakeAccess::$identity = $this->login('root');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function indexEmailsByRecipient(): array
    {
        $emails = [];

        foreach ($this->sentEmails as $email) {
            $emails[$email['recipients'][0]] = $email;
        }

        ksort($emails);

        return $emails;
    }

    private function notifier(): ClientSecretExpiryNotifier
    {
        return StaticContainer::get(ClientSecretExpiryNotifier::class);
    }

    private function login(string $login): string
    {
        return $login . '_' . $this->suffix;
    }

    private function email(string $login): string
    {
        return $login . '_' . $this->suffix . '@example.org';
    }

    public function provideContainerConfig(): array
    {
        return [
            'Piwik\Access' => new FakeAccess(),
        ];
    }
}
