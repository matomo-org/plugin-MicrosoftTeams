<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\MicrosoftTeams;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Piwik;

class ScheduleReportMicrosoftTeams
{
    /**
     * @var string
     */
    private $subject;
    /**
     * @var string
     */
    private $fileName;

    /**
     * @var string
     */
    private $fileContents;

    /**
     * @var string
     */
    private $webhookUrl;

    /**
     * @var array
     */
    private $requiredFields;

    public function __construct(
        string $subject,
        string $fileName,
        string $fileContents,
        #[\SensitiveParameter]
        string $webhookUrl,
        #[\SensitiveParameter]
        array $requiredFields
    ) {
        $this->subject = $subject;
        $this->fileName = $fileName;
        $this->fileContents = $fileContents;
        $this->webhookUrl = $webhookUrl;
        $this->requiredFields = $requiredFields;
    }

    public function send(): bool
    {
        $microsoftTeamsApi = new MicrosoftTeamsApi($this->webhookUrl);
        return $microsoftTeamsApi->uploadFile($this->subject, $this->fileName, $this->fileContents, $this->requiredFields, $this->getTokenExpiryNoteIfNearExpiring());
    }

    private function getTokenExpiryNoteIfNearExpiring(): string
    {
        $note = '';
        $systemSettings = StaticContainer::get(SystemSettings::class);
        $expiryDate = $systemSettings->clientSecretExpiryDate->getValue();
        if (!$expiryDate) {
            return $note;
        }
        $today = new \DateTime();
        $expiry = new \DateTime($expiryDate);
        $interval = $today->diff($expiry);

        if ($expiry < $today) {
            $logger = StaticContainer::get(LoggerInterface::class);
            $logger->error('Client Secret Expired.');

            return $note;
        }

        if ($interval->days <= 15) {
            $note = Piwik::translate('MicrosoftTeams_ClientSecretExpiryNote', ['<strong>', '</strong>', $expiryDate]);
        }

        return $note;
    }
}
