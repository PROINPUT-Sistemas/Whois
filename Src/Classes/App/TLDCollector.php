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

declare(strict_types=1);

namespace Pentagonal\WhoIs\App;

use Pentagonal\WhoIs\Util\DataParser;
use Pentagonal\WhoIs\Util\Puny;

/**
 * Class TLDCollector
 * @package Pentagonal\WhoIs\App
 */
class TLDCollector
{
    /**
     * @var array
     */
    protected $ccTLDList = [
        "ac",
        "ad",
        "ae",
        "af",
        "ag",
        "ai",
        "al",
        "am",
        "an",
        "ao",
        "aq",
        "ar",
        "as",
        "at",
        "au",
        "aw",
        "ax",
        "az",
        "ba",
        "bb",
        "bd",
        "be",
        "bf",
        "bg",
        "bh",
        "bi",
        "bj",
        "bl",
        "bm",
        "bn",
        "bo",
        "bq",
        "br",
        "bs",
        "bt",
        "bv",
        "bw",
        "by",
        "bz",
        "ca",
        "cc",
        "cd",
        "cf",
        "cg",
        "ch",
        "ci",
        "ck",
        "cl",
        "cm",
        "cn",
        "co",
        "cr",
        "cu",
        "cv",
        "cw",
        "cx",
        "cy",
        "cz",
        "de",
        "dj",
        "dk",
        "dm",
        "do",
        "dz",
        "ec",
        "ee",
        "eg",
        "eh",
        "er",
        "es",
        "et",
        "eu",
        "fi",
        "fj",
        "fk",
        "fm",
        "fo",
        "fr",
        "ga",
        "gb",
        "gd",
        "ge",
        "gf",
        "gg",
        "gh",
        "gi",
        "gl",
        "gm",
        "gn",
        "gp",
        "gq",
        "gr",
        "gs",
        "gt",
        "gu",
        "gw",
        "gy",
        "hk",
        "hm",
        "hn",
        "hr",
        "ht",
        "hu",
        "id",
        "ie",
        "il",
        "im",
        "in",
        "io",
        "iq",
        "ir",
        "is",
        "it",
        "je",
        "jm",
        "jo",
        "jp",
        "ke",
        "kg",
        "kh",
        "ki",
        "km",
        "kn",
        "kp",
        "kr",
        "kw",
        "ky",
        "kz",
        "la",
        "lb",
        "lc",
        "li",
        "lk",
        "lr",
        "ls",
        "lt",
        "lu",
        "lv",
        "ly",
        "ma",
        "mc",
        "md",
        "me",
        "mf",
        "mg",
        "mh",
        "mk",
        "ml",
        "mm",
        "mn",
        "mo",
        "mp",
        "mq",
        "mr",
        "ms",
        "mt",
        "mu",
        "mv",
        "mw",
        "mx",
        "my",
        "mz",
        "na",
        "nc",
        "ne",
        "nf",
        "ng",
        "ni",
        "nl",
        "no",
        "np",
        "nr",
        "nu",
        "nz",
        "om",
        "pa",
        "pe",
        "pf",
        "pg",
        "ph",
        "pk",
        "pl",
        "pm",
        "pn",
        "pr",
        "ps",
        "pt",
        "pw",
        "py",
        "qa",
        "re",
        "ro",
        "rs",
        "ru",
        "rw",
        "sa",
        "sb",
        "sc",
        "sd",
        "se",
        "sg",
        "sh",
        "si",
        "sj",
        "sk",
        "sl",
        "sm",
        "sn",
        "so",
        "sr",
        "ss",
        "st",
        "su",
        "sv",
        "sx",
        "sy",
        "sz",
        "tc",
        "td",
        "tf",
        "tg",
        "th",
        "tj",
        "tk",
        "tl",
        "tm",
        "tn",
        "to",
        "tp",
        "tr",
        "tt",
        "tv",
        "tw",
        "tz",
        "ua",
        "ug",
        "uk",
        "um",
        "us",
        "uy",
        "uz",
        "va",
        "vc",
        "ve",
        "vg",
        "vi",
        "vn",
        "vu",
        "wf",
        "ws",
        "ye",
        "yt",
        "za",
        "zm",
        "zw"
    ];

    /**
     * @var array[]|string[][]
     */
    protected $availableServers = [];

    /**
     * @var array[]|string[][]
     */
    protected $availableExtensions = [];

    /**
     * @var Puny
     */
    protected $punyCodeInstance;

    /**
     * @var string
     */
    protected $availableServersFile;

    /**
     * @var string
     */
    protected $availableExtensionsFile;

    /**
     * TLDCollector constructor.
     *
     * @param array|null $serverList
     * @final
     */
    final public function __construct(array $serverList = null)
    {
        $this->punyCodeInstance        = new Puny();
        $this->availableServersFile    = DataParser::PATH_WHOIS_SERVERS;
        $this->availableExtensionsFile = DataParser::PATH_EXTENSIONS_AVAILABLE;

        /** @noinspection PhpIncludeInspection */
        $this->availableServers = $this->checkTLDList($serverList ?: require $this->availableServersFile);
        /** @noinspection PhpIncludeInspection */
        $this->availableExtensions = $this->checkTLDList($serverList ?: require $this->availableExtensionsFile);
    }

    /**
     * Get Server List File
     *
     * @return string
     */
    public function getAvailableServersFile() : string
    {
        return $this->availableServersFile;
    }

    /**
     * @return string
     */
    public function getAvailableExtensionsFile() : string
    {
        return $this->availableExtensionsFile;
    }

    /**
     * @param array $list
     *
     * @return array
     */
    protected function checkTLDList(array $list) : array
    {
        $data = [];
        foreach ($list as $key => $value) {
            if (!is_string($key) || ! is_array($value)) {
                throw new \RuntimeException(
                    'Invalid whois server list declared. This contains invalid whois server or extension.',
                    E_WARNING
                );
            }

            $key = $this->encode($key);
            $data[$key] = [];
            foreach ($value as $server) {
                if (!is_string($server)) {
                    throw new \RuntimeException(
                        'Invalid whois server list declared. This contains invalid whois server definition.',
                        E_WARNING
                    );
                }
                $data[$key][] = $this->encode($server);
            }
        }

        return $data;
    }

    /**
     * @return Puny
     */
    public function getPunyCode() : Puny
    {
        return clone $this->punyCodeInstance;
    }

    /**
     * Encode Puny code
     *
     * @param string $string
     *
     * @return string
     */
    public function encode(string $string) : string
    {
        return $this->punyCodeInstance->encode($string);
    }

    /**
     * Decode puny code
     *
     * @param string $string
     *
     * @return string
     */
    public function decode(string $string) : string
    {
        return $this->punyCodeInstance->decode($string);
    }

    /**
     * Check if extension exists
     *
     * @param string $extension
     *
     * @return bool
     */
    public function isExtensionExists(string $extension) : bool
    {
        $extension = $this->encode(trim($extension));
        return isset($this->availableServers[$extension]);
    }

    /**
     * @return array[]|\string[][]
     */
    public function getAvailableServers() : array
    {
        return $this->availableServers;
    }

    /**
     * Get List Of Empty Server
     *
     * @return array
     */
    public function getEmptyServersExtensions() : array
    {
        return array_keys(
            array_filter(
                $this->getAvailableServers(),
                function ($data) {
                    return empty($data);
                }
            )
        );
    }

    /**
     * @return array
     */
    public function getAvailableExtensions() : array
    {
        return $this->availableExtensions;
    }

    /**
     * Get servers list from extensions, when it was empty return null
     *
     * @param string $extension
     *
     * @return null|ArrayCollector
     */
    public function getServersFromExtension(string $extension)
    {
        if (trim($extension) == '') {
            throw new \InvalidArgumentException(
                'Extension could not be empty or white space only',
                E_NOTICE
            );
        }

        $extension = $this->encode(trim($extension));
        return isset($this->availableServers[$extension])
            ? new ArrayCollector($this->availableServers[$extension])
            : null;
    }

    /**
     * Get Server From Servers
     *
     * @param string $extension
     * @param int $position
     *
     * @return string|null
     */
    public function getServerFromExtension(string $extension, int $position = 0)
    {
        $servers = $this->getServersFromExtension($extension);
        if ($servers === null) {
            return null;
        }

        // check if possible
        $isEnd = $position >= count($servers);
        $position = isset($servers[$position])
            ? $position
            : ($isEnd ? count($servers)-1 : 0);

        return $servers[$position];
    }

    /**
     * Get Sub domain from extension
     *
     * @param string $extension
     *
     * @return null|ArrayCollector
     */
    public function getSubDomainFromExtension(string $extension)
    {
        if (trim($extension) == '') {
            throw new \InvalidArgumentException(
                'Extension could not be empty or white space only',
                E_NOTICE
            );
        }

        $extension = $this->encode(trim($extension));
        return isset($this->availableExtensions[$extension])
            ? new ArrayCollector($this->availableExtensions[$extension])
            : null;
    }

    /**
     * Get CCTLD List
     *
     * @return array
     */
    public function getCountryExtensionList(): array
    {
        return $this->ccTLDList;
    }
}