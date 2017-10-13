<?php
/**
 * This package contains some code that reused by other repository(es) for private uses.
 * But on some certain conditions, it will also allowed to used as commercials project.
 * Some code & coding standard also used from other repositories as inspiration ideas.
 * And also uses 3rd-Party as to be used as result value without their permission but permit to be used.
 *
 * @license GPL-3.0  {@link https://www.gnu.org/licenses/gpl-3.0.en.html}
 * @copyright (c) 2017. Pentagonal Development
 * @author pentagonal <org@pentagonal.org>
 */

namespace Pentagonal\Tests\PhpUnit\WhoIs;

use Pentagonal\WhoIs\Util\DataGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Class DataServerExtensionsTest
 * @package Pentagonal\Tests\PhpUnit\WhoIs
 */
class DataServerExtensionsTest extends TestCase
{
    /**
     * Server List
     */
    public function testServerList()
    {
        $this->assertNotEmpty(
            require DataGenerator::PATH_EXTENSIONS_AVAILABLE
        );

        $this->assertNotEmpty(
            require DataGenerator::PATH_WHOIS_SERVERS
        );
    }
}