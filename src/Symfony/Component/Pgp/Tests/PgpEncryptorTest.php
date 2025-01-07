<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Pgp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Pgp\PgpEncrypter;

class PgpEncryptorTest extends TestCase
{
    private \Crypt_GPG $gpg;

    private Email $email;

    private PgpEncrypter $encrypter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gpg = new \Crypt_GPG();
        $this->gpg->importKeyFile(__DIR__.'/_data/pgp_test_key.asc');
        $this->email = (new Email())
            ->from(new Address('pgp@pulli.dev', 'PuLLi'))
            ->to(new Address('pgp@pulli.dev', 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');
        $this->encrypter = new PgpEncrypter();
    }

    protected function tearDown(): void
    {
        $this->removeKey('pgp@pulli.dev');
        parent::tearDown();
    }

    public function testEncrypting()
    {
        $encrypted = $this->encrypter->encrypt($this->email)->toString();
        $this->assertStringContainsString('-----BEGIN PGP MESSAGE-----', $encrypted);
        $this->assertStringContainsString('-----END PGP MESSAGE-----', $encrypted);
    }

    public function testEncryptingAndSigning()
    {
        $encrypted = $this->encrypter->encryptAndSign($this->email, 'test1234')->toString();
        $this->assertStringContainsString('-----BEGIN PGP MESSAGE-----', $encrypted);
        $this->assertStringContainsString('-----END PGP MESSAGE-----', $encrypted);
        $this->assertStringNotContainsString('-----BEGIN PGP SIGNATURE-----', $encrypted);
        $this->assertStringNotContainsString('-----END PGP SIGNATURE-----', $encrypted);
    }

    public function testSigning()
    {
        $this->encrypter->sign($this->email, 'test1234');

        $signature = $this->encrypter->getSignature();

        $this->assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $signature);
        $this->assertStringContainsString('-----END PGP SIGNATURE-----', $signature);

        $key = $this->gpg->verify($this->encrypter->getSignedPart(), $signature);

        $this->assertCount(1, $key);
        $this->assertSame('pgp@pulli.dev', $key[0]->getUserId()->getEmail());
    }

    private function removeKey(string $name)
    {
        $this->gpg->deletePrivateKey($name);
        $this->gpg->deletePublicKey($name);
    }
}
