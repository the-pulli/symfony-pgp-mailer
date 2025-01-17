<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\PGPEncryptedMessagePart;

class PGPEncryptedMessagePartTest extends TestCase
{
    public function testPGPEncryptedMessagePart()
    {
        $part = (new PGPEncryptedMessagePart(''))->toString();
        $this->assertStringContainsString('Content-Type: application/octet-stream', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: inline', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=msg.asc', $part, 'filename not found');
    }
}
