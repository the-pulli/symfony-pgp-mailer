<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\MimePgp\Mime\Part;

use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\MimePgp\Mime\Traits\PgpSigningTrait;

/*
 * @author PuLLi <the@pulli.dev>
 *
 * @internal
 */
final class PgpEncryptedMessagePart extends AbstractPart
{
    use PgpSigningTrait;

    private string $body;

    public function __construct(string $body)
    {
        parent::__construct();

        $this->body = $this->normalizeLineEnding($body);
        $this->getHeaders()->addParameterizedHeader('Content-Disposition', 'inline', [
            'filename' => 'msg.asc',
        ]);
    }

    public function bodyToString(): string
    {
        return $this->body;
    }

    public function bodyToIterable(): iterable
    {
        yield $this->body;
    }

    public function getMediaType(): string
    {
        return 'application';
    }

    public function getMediaSubtype(): string
    {
        return 'octet-stream';
    }
}
