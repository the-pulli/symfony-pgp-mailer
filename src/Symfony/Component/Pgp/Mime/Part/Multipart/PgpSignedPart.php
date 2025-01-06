<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Pgp\Mime\Part\Multipart;

use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Pgp\Mime\Traits\PgpSigningTrait;

/*
 * @author PuLLi <the@pulli.dev>
 */
class PgpSignedPart extends AbstractMultipartPart
{
    use PgpSigningTrait;

    public function __construct(AbstractPart ...$parts)
    {
        parent::__construct(...$parts);
        $this->getHeaders()->addParameterizedHeader('Content-Type', 'multipart/signed', [
            'micalg' => 'pgp-sha512',
            'protocol' => 'application/pgp-signature',
        ]);
    }

    public function getMediaSubtype(): string
    {
        return 'signed';
    }

    public function toString(): string
    {
        // We only have a text/multipart and the signature
        $parts = $this->getParts();

        return $this->prepareMessageForSigning($parts[0], parent::toString());
    }

    public function toIterable(): iterable
    {
        yield $this->toString();
    }

    public function bodyToString(): string
    {
        return "This is an OpenPGP/MIME signed message (RFC 3156 and 4880).\r\n\r\n".parent::bodyToString();
    }

    public function bodyToIterable(): iterable
    {
        yield $this->bodyToString();
    }
}
