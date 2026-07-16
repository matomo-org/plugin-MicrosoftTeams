<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams\tests;

use Piwik\Plugins\MicrosoftTeams\Configuration;
use Piwik\Plugins\MicrosoftTeams\Encryption;
use Piwik\Plugins\MicrosoftTeams\Exceptions\SecretConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * @group MicrosoftTeams
 * @group Plugins
 */
class EncryptionTest extends TestCase
{
    private function makeConfiguration()
    {
        return new class () extends Configuration {
            private $key = '';

            public function getEncryptionKey(): string
            {
                return $this->key;
            }

            public function getOrCreateEncryptionKey(): string
            {
                if ($this->key === '') {
                    $this->key = base64_encode(random_bytes(32));
                }

                return $this->key;
            }

            public function setEncryptionKey(
                #[\SensitiveParameter]
                string $key
            ): void {
                $this->key = $key;
            }
        };
    }

    public function test_shouldEncryptAndDecryptRoundTrip()
    {
        $encryption = new Encryption($this->makeConfiguration());
        $encrypted = $encryption->encryptString('very-secret-value');

        $this->assertTrue($encryption->isEncrypted($encrypted));
        $this->assertNotSame('very-secret-value', $encrypted);
        $this->assertSame('very-secret-value', $encryption->decryptString($encrypted));
    }

    public function test_shouldFailToDecryptTamperedPayload()
    {
        $this->expectException(SecretConfigurationException::class);

        $encryption = new Encryption($this->makeConfiguration());
        $encrypted = $encryption->encryptString('very-secret-value');
        $encryption->decryptString(substr($encrypted, 0, -2) . 'ab');
    }
}
