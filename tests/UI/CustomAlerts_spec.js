/*!
 * Matomo - free/libre analytics platform
 *
 * Screenshot integration tests.
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

 describe("CustomAlerts", function () {
   this.fixture = "Piwik\\Tests\\Fixtures\\EmptySite";

   // required to ensure no provider is set initially
   this.optionsOverride = {
     'persist-fixture-data': false,
   };

  async function captureScreen(screenshotName, theTest, selector) {
    if (!selector) {
      selector = reportSelector;
    }
    await theTest();
    await page.waitForNetworkIdle();
    await page.waitForSelector(selector);
    expect(await page.screenshotSelector(selector)).to.matchImage(screenshotName);
  }

  before(function () {
    testEnvironment.pluginsToLoad = ['CustomAlerts', 'MicrosoftTeams'];
    testEnvironment.save();
  });

  it('should load the custom alerts as empty', async function () {
    const selector = '.page';
    await captureScreen('empty_report', async () => {
      await page.goto('?module=CustomAlerts&action=index&idSite=1&period=day&date=yesterday');
    }, selector);
  });

  it('should show load a new alert add screen', async function () {
    const selector = '.page';
    await captureScreen('new_custom_alert', async () => {
      await page.evaluate(() => $('.icon-add').click());
    }, selector);
  });

  it('should show send alert via MicrosoftTeams as an option enabled', async function () {
    const selector = '.page';
    await captureScreen('send_via_teams_new', async () => {
      await page.click('.report-mediums .select-dropdown');
      await page.waitForNetworkIdle();
      await page.waitForTimeout(350); // wait for animation
    }, selector);
    await page.click('.report-mediums .select-dropdown');
  });

  it('should show MicrosoftTeams webhookURL input as enabled by default as it requires webhook URL only', async function () {
    const selector = '.page';
    await captureScreen('teams_report_enabled_default', async () => {
      await page.evaluate(() => $('.report-mediums .select-wrapper ul li:contains("Teams")').click());
      // the click leaves the pointer over the select, and the restyled control paints a
      // :hover border; move it away so the capture is not taken mid-hover
      await page.mouse.move(0, 0);
    }, selector);
  });

  it('should show show error if webhookURL not set', async function () {
    const selector = '.page';
    await captureScreen('teams_report_error', async () => {
      await page.type('#alertName', 'Test teams Alert');
      await page.evaluate(() => $('.conditionAndValue .select-wrapper ul li:last').click());
      await page.type('#metricValue', '2');
      await page.click('.matomo-save-button input.btn');
      await page.waitForNetworkIdle();
    }, selector);
  });

  it('should save a report successfully', async function () {
    const selector = '.page';
    testEnvironment.configOverride.MicrosoftTeams = {teamsClientID: 'clientID', teamsClientSecret: 'clientSecret', teamsTenantID: 'tenantID', teamsTeamID: 'teamID'};
    testEnvironment.save();
    await captureScreen('teams_alert_report_save_success', async () => {
      await page.type('input#webhookURL', 'https://webhook-url.example.com');
      await page.click('.matomo-save-button input.btn');
      await page.waitForNetworkIdle();
    }, selector);
  });

  });