$(function () {
    if (!window.piwik.userLogin || document.querySelector('#loginPage')) {
      return; // do nothing if not a dashboard request
    }
    if (window.msTeamsShouldShowWebhookNotification) {
        var UI = require('piwik/UI');
        var notification = new UI.Notification();
        var message = _pk_translate('MicrosoftTeams_MicrosoftTeamsWebhookUrlDeprecatedNoticeText', ['<a href="https://matomo.org/faq/reports/how-to-get-microsoft-teams-webhook-url/" target="_blank" rel="noreferrer noopener">', '</a>'])
        notification.show(message,{
          context: 'warning',
          id: 'msTeamsDeprecatedNotification'
        });
    }
});