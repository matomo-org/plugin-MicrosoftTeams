<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Option;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugins\ScheduledReports\ScheduledReports;
use Piwik\ReportRenderer;
use Piwik\SettingsPiwik;
use Piwik\UrlHelper;
use Piwik\View;

class MicrosoftTeams extends \Piwik\Plugin
{
    public const MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER = 'msTeamsWebhookUrl';
    public const MS_TEAMS_TYPE = 'teams';
    private static $availableParameters = [
        self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => true,
        ScheduledReports::EVOLUTION_GRAPH_PARAMETER => false,
        ScheduledReports::DISPLAY_FORMAT_PARAMETER => true,
    ];

    private static $managedReportTypes = [
        self::MS_TEAMS_TYPE => 'plugins/MicrosoftTeams/images/teams.png',
    ];

    private static $managedReportFormats = array(
        ReportRenderer::PDF_FORMAT => 'plugins/Morpheus/icons/dist/plugins/pdf.png',
        ReportRenderer::CSV_FORMAT => 'plugins/Morpheus/images/export.png',
        ReportRenderer::TSV_FORMAT => 'plugins/Morpheus/images/export.png',
    );

    public function install()
    {
        (new Configuration())->install();
    }

    public function registerEvents()
    {
        return [
            'ScheduledReports.getReportParameters' => 'getReportParameters',
            'ScheduledReports.validateReportParameters' => 'validateReportParameters',
            'ScheduledReports.getReportMetadata' => 'getReportMetadata',
            'ScheduledReports.getReportTypes' => 'getReportTypes',
            'ScheduledReports.getReportFormats' => 'getReportFormats',
            'ScheduledReports.getRendererInstance' => 'getRendererInstance',
            'ScheduledReports.getReportRecipients' => 'getReportRecipients',
            'ScheduledReports.processReports' => 'processReports',
            'ScheduledReports.allowMultipleReports' => 'allowMultipleReports',
            'ScheduledReports.sendReport' => 'sendReport',
            'Template.reportParametersScheduledReports' => 'templateReportParametersScheduledReports',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'CustomAlerts.validateReportParameters' => 'validateCustomAlertReportParameters',
            'CustomAlerts.sendNewAlerts' => 'sendNewAlerts',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Template.jsGlobalVariables' => 'addJsGlobalVariables',
        ];
    }


    public function requiresInternetConnection()
    {
        return true;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/MicrosoftTeams/javascripts/alertNotification.js";
    }

    public function addJsGlobalVariables(&$out)
    {
        $request = \Piwik\Request::fromRequest();
        $module = $request->getParameter('module', '');
        $action  = $request->getParameter('action', '');
        $shouldShowNotification = false;
        $login = Piwik::getCurrentUserLogin();
        $idSite = $request->getParameter('idSite', '');
        if ($module === 'ScheduledReports' && $action === 'index' && $idSite) {
            $table = Common::prefixTable('report');
            $sql = "SELECT count(type) FROM `$table` WHERE type = ? AND idsite = ? AND deleted = 0 AND login = ?"
                . " AND parameters NOT LIKE ?";
            $bind = [self::MS_TEAMS_TYPE, $idSite, $login, '%powerautomate%'];
            $result = Db::fetchOne($sql, $bind);
            $shouldShowNotification = !empty($result);
        } elseif ($module === 'CustomAlerts' && $action === 'index') {
            $table = Common::prefixTable('alert');
            $sql = "SELECT count(idalert) FROM `$table` WHERE login = ? AND ms_teams_webhook_url NOT LIKE ?"
                . " AND report_mediums LIKE ?";
            $bind = [$login, '%powerautomate%', '%teams%'];
            $result = Db::fetchOne($sql, $bind);
            $shouldShowNotification = !empty($result);
        }

        $out .= 'var msTeamsShouldShowWebhookNotification = ' . json_encode($shouldShowNotification) . ';';
        $out .= 'var msTeamsAlertModule = ' . json_encode($module) . ';';
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'MicrosoftTeams_RequiredFieldsNotSet';
        $translationKeys[] = 'MicrosoftTeams_IncomingWebhookRequiredErrorMessage';
        $translationKeys[] = 'MicrosoftTeams_TeamsWebhookUrl';
        $translationKeys[] = 'MicrosoftTeams_ClientIdTitle';
        $translationKeys[] = 'MicrosoftTeams_ClientIdDescription';
        $translationKeys[] = 'MicrosoftTeams_ClientSecretTitle';
        $translationKeys[] = 'MicrosoftTeams_ClientSecretDescription';
        $translationKeys[] = 'MicrosoftTeams_TenantIdTitle';
        $translationKeys[] = 'MicrosoftTeams_TenantIdDescription';
        $translationKeys[] = 'MicrosoftTeams_TeamsEnterYourWebhookUrlText';
        $translationKeys[] = 'MicrosoftTeams_MicrosoftTeamsWebhookUrlDeprecatedNoticeText';
        $translationKeys[] = 'MicrosoftTeams_MicrosoftTeamsWebhookUrlDeprecatedNoticeTextCustomAlerts';
    }

    /**
     *
     * Adds report parameter for MicrosoftTeams, e.g. teamWebhookURL
     *
     * @param $availableParameters
     * @param $reportType
     * @return void
     */
    public function getReportParameters(&$availableParameters, $reportType)
    {
        if (self::isMSTeamsEvent($reportType)) {
            $availableParameters = self::$availableParameters;
        }
    }

    /**
     *
     * Validates the Schedule Report for MicrosoftTeams reportType
     *
     * @param $parameters
     * @param $reportType
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function validateReportParameters(&$parameters, $reportType)
    {
        if (!self::isMSTeamsEvent($reportType)) {
            return;
        }

        $reportFormat = $parameters[ScheduledReports::DISPLAY_FORMAT_PARAMETER];
        $availableDisplayFormats = array_keys(ScheduledReports::getDisplayFormats());
        if (!in_array($reportFormat, $availableDisplayFormats)) {
            throw new \Exception(
                Piwik::translate(
                    'General_ExceptionInvalidAggregateReportsFormat',
                    array($reportFormat, implode(', ', $availableDisplayFormats))
                )
            );
        }

        // evolutionGraph is an optional parameter
        if (!isset($parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER])) {
            $parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER] = ScheduledReports::EVOLUTION_GRAPH_PARAMETER_DEFAULT_VALUE;
        } else {
            $parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER] = self::valueIsTrue($parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER]);
        }

        $settings = StaticContainer::get(SystemSettings::class);
        if (!$settings->isRequiredFieldsSet()) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_RequiredFieldsNotSet'));
        } elseif (empty($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookRequiredErrorMessage'));
        } elseif (!UrlHelper::isLookLikeUrl($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookInvalidErrorMessage'));
        }

        $this->assertWebhookDestinationAllowed($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER]);

        $parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER] = htmlspecialchars_decode($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER]);
    }

    /**
     *
     * Adds MicrosoftTeams as a reportType in Schedule Reports
     *
     * @param $reportTypes
     * @return void
     */
    public function getReportTypes(&$reportTypes)
    {
        $reportTypes = array_merge($reportTypes, self::$managedReportTypes);
    }

    /**
     *
     * Adds allowed reportTypes for MicrosoftTeams, e.g. PDF, CSV and TSV
     *
     * @param $reportFormats
     * @param $reportType
     * @return void
     */
    public function getReportFormats(&$reportFormats, $reportType)
    {
        if (self::isMSTeamsEvent($reportType)) {
            $reportFormats = array_merge($reportFormats, self::$managedReportFormats);
        }
    }

    /**
     *
     * To allow multiple reports in a single file
     *
     * @param $allowMultipleReports
     * @param $reportType
     * @return void
     */
    public function allowMultipleReports(&$allowMultipleReports, $reportType)
    {
        if (self::isMSTeamsEvent($reportType)) {
            $allowMultipleReports = true;
        }
    }

    /**
     *
     * Get report metadata for MicrosoftTeams scheduled report
     *
     * @param $availableReportMetadata
     * @param $reportType
     * @param $idSite
     * @return void
     */
    public function getReportMetadata(&$availableReportMetadata, $reportType, $idSite)
    {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }

        // Use same metadata as E-mail report from ScheduledReports plugin
        Piwik::postEvent(
            'ScheduledReports.getReportMetadata',
            [&$availableReportMetadata, ScheduledReports::EMAIL_TYPE, $idSite]
        );
    }

    /**
     *
     * Displays the recipients in the list of Schedule Reports
     *
     * @param $recipients
     * @param $reportType
     * @param $report
     * @return void
     */
    public function getReportRecipients(&$recipients, $reportType, $report)
    {
        if (!self::isMSTeamsEvent($reportType) || empty($report['parameters'][self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
            return;
        }

        $recipients = [Piwik::translate('MicrosoftTeams_TeamsChannel')];
    }

    /**
     *
     * Process the Schedule report for reportType MicrosoftTeams
     *
     * @param $processedReports
     * @param $reportType
     * @param $outputType
     * @param $report
     * @return void
     */
    public function processReports(&$processedReports, $reportType, $outputType, $report)
    {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }

        // Use same metadata as E-mail report from ScheduledReports plugin
        Piwik::postEvent(
            'ScheduledReports.processReports',
            [&$processedReports, ScheduledReports::EMAIL_TYPE, $outputType, $report]
        );
    }

    /**
     *
     * Sets the rendered instance based on reportFormat for MicrosoftTeams
     *
     * @param $reportRenderer
     * @param $reportType
     * @param $outputType
     * @param $report
     * @return void
     * @throws \Exception
     */
    public function getRendererInstance(&$reportRenderer, $reportType, $outputType, $report)
    {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }

        $reportFormat = $report['format'];

        $reportRenderer = ReportRenderer::factory($reportFormat);
    }

    /**
     *
     * Add the view template for MicrosoftTeams report parameters
     *
     * @param $out
     * @param $context
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function templateReportParametersScheduledReports(&$out, $context = '')
    {
        if (Piwik::isUserIsAnonymous()) {
            return;
        }

        $view = new View('@MicrosoftTeams/reportParametersScheduledReports');
        $view->reportType = self::MS_TEAMS_TYPE;
        $view->context = $context;

        $settings = StaticContainer::get(SystemSettings::class);
        $view->isRequiredFieldsSet = !empty($settings->isRequiredFieldsSet());
        $view->defaultDisplayFormat = ScheduledReports::DEFAULT_DISPLAY_FORMAT;
        $view->defaultFormat = ReportRenderer::PDF_FORMAT;
        $view->defaultEvolutionGraph = ScheduledReports::EVOLUTION_GRAPH_PARAMETER_DEFAULT_VALUE;
        $out .= $view->render();
    }

    /**
     *
     * Code to send a Schedule Report via MicrosoftTeams
     * @param $reportType
     * @param $report
     * @param $contents
     * @param $filename
     * @param $prettyDate
     * @param $reportSubject
     * @param $reportTitle
     * @param $additionalFiles
     * @param Period|null $period
     * @param $force
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function sendReport(
        $reportType,
        $report,
        $contents,
        $filename,
        $prettyDate,
        $reportSubject,
        $reportTitle,
        $additionalFiles,
        $period,
        $force
    ) {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }
        $logger = StaticContainer::get(LoggerInterface::class);
        // Safeguard against sending the same report twice to the same Teams channel (unless $force is true)
        if (!$force && $this->reportAlreadySent($report, $period)) {
            $logger->warning(
                sprintf('Preventing the same scheduled report from being sent again (report #%s for period "%s")', $report['idreport'], $report['period'])
            );
            return;
        }

        $settings = StaticContainer::get(SystemSettings::class);
        if (!$settings->isRequiredFieldsSet()) {
            $logger->error('Microsoft Teams required fields not set.');
            return;
        }

        $periods = ScheduledReports::getPeriodToFrequency();
        $subject = Piwik::translate('MicrosoftTeams_PleaseFindYourReport', [$periods[$report['period']], $reportSubject]);
        $webhookUrl = $report['parameters'][self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER];
        $requiredFields = $settings->getRequiredFieldsWithValue();
        $scheduleReportMsTeams = new ScheduleReportMicrosoftTeams($subject, $filename, $contents, $webhookUrl, $requiredFields);
        if ($scheduleReportMsTeams->send() && !$force) {
            $this->markReportAsSent($report, $period);
        }
    }

    /**
     *
     * Validation check for CustomAlert report parameters
     *
     * @param $parameters
     * @param $alertMedium
     * @return void
     * @throws \Exception
     */
    public function validateCustomAlertReportParameters($parameters, $alertMedium)
    {
        if ($alertMedium === self::MS_TEAMS_TYPE) {
            if (empty($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
                throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookRequiredErrorMessage'));
            } elseif (!UrlHelper::isLookLikeUrl($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
                throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookInvalidErrorMessage'));
            }

            $this->assertWebhookDestinationAllowed($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER]);

            $parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER] = htmlspecialchars_decode($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER]);
        }
    }

    /**
     * Rejects webhook URLs Matomo must never post to.
     *
     * The authoritative destination check runs when the webhook is called, over the SSRF safe fetch
     * path in {@see MicrosoftTeamsApi::sendMessageToTeamsChannel()}, which resolves the host and pins
     * the validated address. This is the DNS free part of that contract, so that a webhook URL which
     * can never be a Teams channel is reported while the report or alert is being saved.
     *
     * @throws \Exception
     */
    private function assertWebhookDestinationAllowed(string $webhookUrl): void
    {
        $host = $this->canonicaliseHost(parse_url($webhookUrl, PHP_URL_HOST));

        if (
            $host === ''
            || $this->isAddressLiteralHost($host)
            // A Teams webhook host is a plain letter digit hyphen name, so everything else is
            // refused by not matching that, rather than by enumerating the ways a host can be
            // written, e.g. a percent encoded '%6c%6fcalhost' or an underscore.
            || !preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $host)
            || $this->isLocalMatomoHost($host)
        ) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookInvalidErrorMessage'));
        }
    }

    /**
     * Whether the host is an address literal rather than a name, in any spelling the transport reads
     * as one.
     *
     * A Teams webhook is always a named host, so an address literal only points at infrastructure the
     * user should not be able to aim Matomo at, and curl accepts far more spellings of one than
     * filter_var() does: inet_aton() reads one to four decimal, octal or hexadecimal parts, so
     * 2130706433, 0x7f000001, 0177.0.0.1, 127.1, 0x7f.1 and 0x7f.0x0.0x0.0x1 all reach the loopback
     * interface. The name check cannot stand in for this, as '0x7f' and '1' are both valid labels.
     *
     * @param string $host canonicalised host, as returned by {@see canonicaliseHost()}
     */
    private function isAddressLiteralHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        // more parts than inet_aton() reads, but digits and dots alone still cannot name a host
        if (preg_match('/^[0-9.]+$/', $host)) {
            return true;
        }

        $parts = explode('.', $host);

        if (count($parts) > 4) {
            return false;
        }

        foreach ($parts as $part) {
            if (!preg_match('/^(0x[0-9a-f]+|[0-9]+)$/i', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Folds a host to the form the transport will connect to, so that the checks made on it cannot be
     * sidestepped with casing, a trailing dot or an internationalised spelling of the same name.
     */
    private function canonicaliseHost(?string $host): string
    {
        $host = trim((string) $host, '[]');

        if ($host !== '' && preg_match('/[^\x20-\x7e]/', $host) && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        return rtrim(strtolower($host), '.');
    }

    /**
     * @param string $host canonicalised host, as returned by {@see canonicaliseHost()}
     */
    private function isLocalMatomoHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'localhost.localdomain', 'ip6-localhost'], true)) {
            return true;
        }

        $matomoHost = $this->canonicaliseHost(parse_url(SettingsPiwik::getPiwikUrl(), PHP_URL_HOST));

        return $matomoHost !== '' && $matomoHost === $host;
    }

    /**
     *
     * Code to send CustomAlerts via MicrosoftTeams
     *
     * @param $triggeredAlerts
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function sendNewAlerts($triggeredAlerts): void
    {
        if (!empty($triggeredAlerts)) {
            $enrichTriggerAlerts = StaticContainer::get(EnrichTriggeredAlerts::class);
            $triggeredAlerts = $enrichTriggerAlerts->enrichTriggeredAlerts($triggeredAlerts);
            $groupedAlerts = $this->groupAlertsByChannelId($triggeredAlerts);
            foreach ($groupedAlerts as $msTeamsWebhookUrl => $alert) {
                $msTeamsApi = new MicrosoftTeamsApi($msTeamsWebhookUrl);
                if (!$msTeamsApi->sendMessageToTeamsChannel(implode("<br>", $alert['message']))) {
                    $logger = StaticContainer::get(LoggerInterface::class);
                    $logger->info('MicrosoftTeams alert failed for following alerts: ' . implode("\n", $alert['name']));
                }
            }
        }
    }

    /**
     *
     * Group alerts by msTeamsWebhookUrl to reduce number of network calls for multiple alerts
     *
     * @param array $alerts
     * @return array
     */
    private function groupAlertsByChannelId(array $alerts): array
    {
        $groupedAlerts = [];
        foreach ($alerts as $alert) {
            if (!in_array(self::MS_TEAMS_TYPE, $alert['report_mediums']) || empty($alert['ms_teams_webhook_url'])) {
                continue;
            }
            $metric = !empty($alert['reportMetric']) ? $alert['reportMetric'] : $alert['metric'];
            $reportName = !empty($alert['reportName']) ? $alert['reportName'] : $alert['report'];
            $groupedAlerts[$alert['ms_teams_webhook_url']]['message'][] = $this->getAlertMessage($alert, $metric, $reportName);
            $groupedAlerts[$alert['ms_teams_webhook_url']]['name'][] = $alert['name'];
        }

        return $groupedAlerts;
    }


    /**
     *
     * Returns the alert message to send via MicrosoftTeams
     *
     * @param array $alert
     * @param string $metric
     * @param string $reportName
     * @return string
     */
    public function getAlertMessage(array $alert, string $metric, string $reportName): string
    {
        $settingURL = SettingsPiwik::getPiwikUrl();
        if (stripos($settingURL, 'index.php') === false) {
            $settingURL .= 'index.php';
        }
        $settingURL .= '?idSite=' . $alert['idsite'];
        $siteName = htmlspecialchars($alert['siteName'], ENT_QUOTES);
        $siteWithLink = "<a href='$settingURL'>$siteName</a>";
        return Piwik::translate('MicrosoftTeams_MicrosoftTeamsAlertContent', [$alert['name'], $siteWithLink, $metric, $reportName, $this->transformAlertCondition($alert)]);
    }

    /**
     *
     * Transform the alert condition to text
     *
     * @param array $alert
     * @return string
     */
    private function transformAlertCondition(array $alert): string
    {
        switch ($alert['metric_condition']) {
            case 'less_than':
                return Piwik::translate('CustomAlerts_ValueIsLessThan', [$alert['metric_matched'], $alert['value_new']]);
            case 'greater_than':
                return Piwik::translate('CustomAlerts_ValueIsGreaterThan', [$alert['metric_matched'], $alert['value_new']]);
            case 'decrease_more_than':
                return Piwik::translate('CustomAlerts_ValueDecreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
            case 'increase_more_than':
                return Piwik::translate('CustomAlerts_ValueIncreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
            case 'percentage_decrease_more_than':
                return Piwik::translate('CustomAlerts_ValuePercentageDecreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
            case 'percentage_increase_more_than':
                return Piwik::translate('CustomAlerts_ValuePercentageIncreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
        }

        return '';
    }

    private static function isMSTeamsEvent($reportType): bool
    {
        return in_array($reportType, array_keys(self::$managedReportTypes));
    }

    private function reportAlreadySent($report, Period $period)
    {
        $key = ScheduledReports::OPTION_KEY_LAST_SENT_DATERANGE . $report['idreport'];

        $previousDate = Option::get($key);

        return $previousDate === $period->getRangeString();
    }

    private static function valueIsTrue($value)
    {
        return $value == 'true' || $value == 1 || $value == '1' || $value === true;
    }

    private function markReportAsSent($report, Period $period)
    {
        $key = ScheduledReports::OPTION_KEY_LAST_SENT_DATERANGE . $report['idreport'];

        Option::set($key, $period->getRangeString());
    }
}
