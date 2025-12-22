<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams\tests;

use Piwik\Plugins\MicrosoftTeams\MicrosoftTeamsApi;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group MicrosoftTeams
 * @group MicrosoftTeamsApiTest
 * @group Plugins
 */

class MicrosoftTeamsApiTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testSendShouldReturnFalseWhenNoAccessTokenIsReturned()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'getAccessToken',
                'uploadFileToDriveAndGetLink',
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('token'))
            ->getMock();
        $helperMock->expects($this->once())->method('getAccessToken')->willReturn('');
        $helperMock->expects($this->never())->method('uploadFileToDriveAndGetLink');
        $helperMock->expects($this->never())->method('sendMessageToTeamsChannel');

        $this->assertFalse($helperMock->uploadFile('test file', 'test.csv', "a,b,c", []));
    }

    public function testSendShouldReturnFalseWhenGetTeamsSiteIDFails()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'getAccessToken',
                'getTeamsSiteId',
                'getTeamsDriveId',
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('token'))
            ->getMock();
        $helperMock->expects($this->once())->method('getAccessToken')->willReturn('fake_token');
        $helperMock->expects($this->once())->method('getTeamsSiteId')->willReturn('');
        $helperMock->expects($this->never())->method('getTeamsDriveId');
        $helperMock->expects($this->never())->method('sendMessageToTeamsChannel');

        $this->assertFalse($helperMock->uploadFile('test file', 'test.csv', "a,b,c", []));
    }

    public function testSendShouldReturnFalseWhenGetTeamsDriveIDFails()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'getAccessToken',
                'getTeamsSiteId',
                'getTeamsDriveId',
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('token'))
            ->getMock();
        $helperMock->expects($this->once())->method('getAccessToken')->willReturn('fake_token');
        $helperMock->expects($this->once())->method('getTeamsSiteId')->willReturn('site_id');
        $helperMock->expects($this->once())->method('getTeamsDriveId')->willReturn('');
        $helperMock->expects($this->never())->method('sendMessageToTeamsChannel');

        $this->assertFalse($helperMock->uploadFile('test file', 'test.csv', "a,b,c", []));
    }

    public function testSendShouldReturnFalseWhenUploadFileToDriveAndGetLinkFails()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'getAccessToken',
                'uploadFileToDriveAndGetLink',
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('token'))
            ->getMock();
        $helperMock->expects($this->once())->method('getAccessToken')->willReturn('fake_token');
        $helperMock->expects($this->once())->method('uploadFileToDriveAndGetLink')->willReturn('');
        $helperMock->expects($this->never())->method('sendMessageToTeamsChannel');

        $this->assertFalse($helperMock->uploadFile('test file', 'test.csv', "a,b,c", []));
    }

    public function testSendShouldReturnFalseWhenSendMessageToTeamsChannelFails()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'getAccessToken',
                'uploadFileToDriveAndGetLink',
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('token'))
            ->getMock();
        $helperMock->expects($this->once())->method('getAccessToken')->willReturn('fake_token');
        $helperMock->expects($this->once())->method('uploadFileToDriveAndGetLink')->willReturn('https://DRIVE_LINK');
        $helperMock->expects($this->once())->method('sendMessageToTeamsChannel')->willReturn(false);

        $this->assertFalse($helperMock->uploadFile('test file', 'test.csv', "a,b,c", []));
    }

    public function testSendSuccess()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'getAccessToken',
                'uploadFileToDriveAndGetLink',
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('token'))
            ->getMock();
        $helperMock->expects($this->once())->method('getAccessToken')->willReturn('fake_token');
        $helperMock->expects($this->once())->method('uploadFileToDriveAndGetLink')->willReturn('https://DRIVE_LINK');
        $helperMock->expects($this->once())->method('sendMessageToTeamsChannel')->willReturn(true);

        $this->assertTrue($helperMock->uploadFile('test file', 'test.csv', "a,b,c", []));
    }

    public function testSendMessageToTeamsChannelShouldReturnFalse()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('https://WEBHOOK_URL'))
            ->getMock();
        $helperMock->expects($this->once())->method('sendMessageToTeamsChannel')->willReturn(false);

        $this->assertFalse($helperMock->sendMessageToTeamsChannel('test message'));
    }

    public function testSendMessageToTeamsChannelShouldReturnTrue()
    {
        $helperMock = $this->getMockBuilder(MicrosoftTeamsApi::class)
            ->setMethods([
                'sendMessageToTeamsChannel',
            ])
            ->setConstructorArgs(array('https://WEBHOOK_URL'))
            ->getMock();
        $helperMock->expects($this->once())->method('sendMessageToTeamsChannel')->willReturn(true);

        $this->assertTrue($helperMock->sendMessageToTeamsChannel('test message'));
    }
}
