<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Pgp;

use SensitiveParameter;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Pgp\Exception\BadPassphraseException;
use Symfony\Component\Pgp\Exception\FileException;
use Symfony\Component\Pgp\Exception\GeneralException;
use Symfony\Component\Pgp\Exception\KeyNotFoundException;
use Symfony\Component\Pgp\Mime\Part\Multipart\PgpEncryptedPart;
use Symfony\Component\Pgp\Mime\Part\Multipart\PgpSignedPart;
use Symfony\Component\Pgp\Mime\Part\PgpEncryptedInitializationPart;
use Symfony\Component\Pgp\Mime\Part\PgpEncryptedMessagePart;
use Symfony\Component\Pgp\Mime\Part\PgpKeyPart;
use Symfony\Component\Pgp\Mime\Part\PgpSignaturePart;
use Symfony\Component\Pgp\Mime\Traits\PgpSigningTrait;

/*
 * @author PuLLi <the@pulli.dev>
 */
final class PgpEncrypter
{
    use PgpSigningTrait;

    private \Crypt_GPG $gpg;

    private Headers $headers;

    private ?string $signingKey = null;

    private ?string $signed = null;

    private ?string $signature = null;

    /**
     * @throws FileException
     * @throws GeneralException
     */
    public function __construct(array $options = [])
    {
        try {
            $this->gpg = new \Crypt_GPG(
                array_merge(
                    $options,
                    [
                        'cipher-algo' => 'AES256',
                        'digest-algo' => 'SHA512',
                    ],
                )
            );
        } catch (\Crypt_GPG_FileException $e) {
            throw new FileException($e->getMessage(), $e->getCode(), $e);
        } catch (\PEAR_Exception $e) {
            throw new GeneralException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function signingKey(string $keyIdentifier): void
    {
        $this->signingKey = $keyIdentifier;
    }

    /**
     * @throws BadPassphraseException
     * @throws KeyNotFoundException
     * @throws GeneralException
     */
    public function encrypt(Message $message, bool $attachKey = false): Message
    {
        try {
            return $this->encryptWithOrWithoutSigning($message, false, null, $attachKey);
        } catch (\Crypt_GPG_BadPassphraseException $e) {
            throw new BadPassphraseException($e->getMessage(), $e->getCode(), $e);
        } catch (\Crypt_GPG_KeyNotFoundException $e) {
            throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
        } catch (\Crypt_GPG_Exception $e) {
            throw new GeneralException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws BadPassphraseException
     * @throws KeyNotFoundException
     * @throws GeneralException
     */
    public function encryptAndSign(Message $message, #[SensitiveParameter] ?string $passphrase = null, bool $attachKey = false): Message
    {
        try {
            return $this->encryptWithOrWithoutSigning($message, true, $passphrase, $attachKey);
        } catch (\Crypt_GPG_BadPassphraseException $e) {
            throw new BadPassphraseException($e->getMessage(), $e->getCode(), $e);
        } catch (\Crypt_GPG_KeyNotFoundException $e) {
            throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
        } catch (\Crypt_GPG_Exception $e) {
            throw new GeneralException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws BadPassphraseException
     * @throws KeyNotFoundException
     * @throws GeneralException
     */
    private function encryptWithOrWithoutSigning(Message $message, bool $sign = false, #[SensitiveParameter] ?string $passphrase = null, bool $attachKey = false): Message
    {
        $this->headers = $message->getHeaders();
        $body = $message->getBody();

        foreach ($this->getRecipients() as $recipient) {
            $this->gpg->addEncryptKey($recipient);
        }

        if ($attachKey) {
            try {
                $body = $this->attachPublicKey($message);
            } catch (\Crypt_GPG_KeyNotFoundException $e) {
                throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
            } catch (\Crypt_GPG_Exception $e) {
                throw new GeneralException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if ($sign) {
            $this->gpg->addSignKey($this->determineSigningKey(), $passphrase);

            try {
                $body = $this->gpg->encryptAndSign($body->toString());
            } catch (\Crypt_GPG_BadPassphraseException $e) {
                throw new BadPassphraseException($e->getMessage(), $e->getCode(), $e);
            } catch (\Crypt_GPG_KeyNotFoundException $e) {
                throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
            } catch (\Crypt_GPG_Exception $e) {
                throw new GeneralException($e->getMessage(), $e->getCode(), $e);
            }
        } else {
            try {
                $body = $this->gpg->encrypt($body->toString());
            } catch (\Crypt_GPG_KeyNotFoundException $e) {
                throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
            } catch (\Crypt_GPG_Exception $e) {
                throw new GeneralException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $part = new PgpEncryptedPart(
            new PgpEncryptedInitializationPart(),
            new PgpEncryptedMessagePart($body)
        );

        return new Message($this->headers, $part);
    }

    /**
     * @throws BadPassphraseException
     * @throws KeyNotFoundException
     * @throws GeneralException
     */
    public function sign(Message $message, #[SensitiveParameter] ?string $passphrase = null, bool $attachKey = false): Message
    {
        $this->headers = $message->getHeaders();
        $body = $message->getBody()->toString();
        $messagePart = $message->getBody();

        if ($attachKey) {
            try {
                $mixed = $this->attachPublicKey($message);
            } catch (\Crypt_GPG_KeyNotFoundException $e) {
                throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
            } catch (\Crypt_GPG_Exception $e) {
                throw new GeneralException($e->getMessage(), $e->getCode(), $e);
            }

            $body = $mixed->toString();
            $messagePart = $mixed;
        }

        // TODO: find a way to normalize Message body and pass it along to PGPSignedPart
        $body = $this->prepareMessageForSigning($messagePart, $body);
        $this->signed = $body;

        $this->gpg->addSignKey($this->determineSigningKey(), $passphrase);

        try {
            $signature = $this->gpg->sign($body, \Crypt_GPG::SIGN_MODE_DETACHED);
        } catch (\Crypt_GPG_BadPassphraseException $e) {
            throw new BadPassphraseException($e->getMessage(), $e->getCode(), $e);
        } catch (\Crypt_GPG_KeyNotFoundException $e) {
            throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
        } catch (\Crypt_GPG_Exception $e) {
            throw new GeneralException($e->getMessage(), $e->getCode(), $e);
        }

        $this->signature = $signature;
        $part = new PgpSignedPart(
            $messagePart,
            new PgpSignaturePart($signature)
        );

        return new Message($this->headers, $part);
    }

    public function getSignedPart(): ?string
    {
        return $this->signed;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    /**
     * @throws KeyNotFoundException
     * @throws GeneralException
     */
    private function attachPublicKey(Message $message): MixedPart
    {
        try {
            $publicKey = $this->gpg->exportPublicKey($this->determineSigningKey());
        } catch (\Crypt_GPG_KeyNotFoundException $e) {
            throw new KeyNotFoundException($e->getMessage(), $e->getCode(), $e);
        } catch (\Crypt_GPG_Exception $e) {
            throw new GeneralException($e->getMessage(), $e->getCode(), $e);
        }
        $key = new PgpKeyPart($publicKey);

        // TODO: find more elegant way than to create another MixedPart, if Message is already a MixedPart
        return new MixedPart($message->getBody(), $key);
    }

    private function getRecipients(): array
    {
        $recipients = [
            $this->getAddresses('to'),
            $this->getAddresses('cc'),
            $this->getAddresses('bcc'),
        ];

        return array_merge(...$recipients);
    }

    private function getFrom(): string
    {
        return $this->getAddresses('from')[0];
    }

    private function getAddresses(string $type): array
    {
        $addresses = [];
        $addressType = $this->headers->get($type);
        if ($addressType instanceof MailboxListHeader) {
            foreach ($addressType->getAddresses() as $address) {
                $addresses[] = $address->getAddress();
            }
        }

        return $addresses;
    }

    private function determineSigningKey(): string
    {
        return $this->signingKey ?? $this->getFrom();
    }
}
