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

use Pentagonal\WhoIs\Exceptions\EmptyDomainException;
use Pentagonal\WhoIs\Exceptions\HttpException;
use Pentagonal\WhoIs\Exceptions\InvalidDomainException;
use Pentagonal\WhoIs\Exceptions\RequestLimitException;
use Pentagonal\WhoIs\Exceptions\ResultException;
use Pentagonal\WhoIs\Exceptions\TimeOutException;
use Pentagonal\WhoIs\Exceptions\WhoIsServerNotFoundException;
use Pentagonal\WhoIs\Interfaces\CacheInterface;
use Pentagonal\WhoIs\Util\DataParser;
use Pentagonal\WhoIs\Util\Sanitizer;

/**
 * Class Checker
 * @package Pentagonal\WhoIs\App
 */
class Checker
{
    /**
     * Version
     */
    const VERSION = '2.0.0';

    /**
     * Fake Comment that Domain is Registered from
     */
    const COMMENT_FAKE_RECORD = <<<FAKE
% NOTE: The registry of domain does not provide or publish ownership information
%       of domain for public usage. This data is represent for public domain data,
%       data provide from Domain Name Server (DNS) existences.

FAKE;

    /**
     * cache Prefix
     */
    const CACHE_PREFIX = 'pentagonal_wc_';

    /**
     * whois result class to use a result
     */
    const WHOIS_RESULT_CLASS = WhoIsResult::class;

    /**
     * max retry if timeout
     */
    const MAX_RETRY = 5;

    /**
     * @var int
     */
    protected $cacheExpired = 3600;

    /**
     * Instance of validator
     *
     * @var Validator
     */
    protected $validatorInstance;

    /**
     * Cache instance object
     *
     * @var CacheInterface
     */
    protected $cacheInstance;

    /**
     * @var int minimum length when domain name always registered,
     *          maximum set is on between 0 to 3 to use it
     *          and set to non numeric , null or les than 0 or more than
     *          3 (4 or more) to disable it
     */
    protected $minLength = 2;

    /**
     * Declare base on domain name
     * that must be always registered
     * maybe this is branded domain that use trademark use on TLD
     * Domain. or has 1 stld
     * This is fast check for domain availability checker
     * That means no check from whois server
     * but on less than 3 characters it means domain always registered!
     *
     * @var array
     */
    protected $reservedNameTLDDomain = [
        // brand domain that easy to understand and always registered
        'google', 'youtube', 'blogspot', 'android', 'blogger',
        'example', 'icann', 'iana', 'pir', 'ripe', 'arin',
        'amazon', 'ebay', 'adobe', 'hp',
        'fb', 'facebook', 'twitter', 'instagram', 'path',
        'wikipedia', 'wordpress', 'wp', 'time', 'php',
        'office', 'microsoft', 'windows', 'apple', 'mac',
        'disney', 'marvel', 'dc',

        // common registered domain for network
        'dot', 'nic', 'whois', 'web', 'dig', 'blog', 'ping',
        'ip', 'linux', 'unix', 'com', 'net', 'co',
    ];

    /**
     * @var array
     */
    protected $disAllowMainDomainExtension = [
        'kr'
    ];

    /**
     * Checker constructor.
     *
     * @param Validator      $validator Validator instance
     * @param CacheInterface $cache     Cache object
     */
    public function __construct(Validator $validator, CacheInterface $cache = null)
    {
        $this->validatorInstance = $validator;
        $this->cacheInstance = $cache?: new ArrayCacheCollector();
    }

    /**
     * Get instance of validator
     *
     * @return Validator
     */
    public function getValidator() : Validator
    {
        return $this->validatorInstance;
    }

    /**
     * Normalize cache key to put on Cache storage
     *
     * @param string $key
     *
     * @return string
     */
    protected function normalizeCacheKey($key)
    {
        if (!is_string($key) && !is_numeric($key) && ! is_bool($key) && ! is_null($key)) {
            $key = Sanitizer::maybeSerialize($key);
        }

        return static::CACHE_PREFIX . sha1($key);
    }

    /**
     * Get cache
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    protected function putCache(string $key, $value)
    {
        return $this->cacheInstance->put(
            $this->normalizeCacheKey($key),
            Sanitizer::maybeSerialize($value),
            $this->cacheExpired
        );
    }

    /**
     * Get cache data
     *
     * @param string $cache
     *
     * @return mixed|null
     */
    protected function getCache(string $cache)
    {
        $cacheData = $this->cacheInstance->get(
            $this->normalizeCacheKey($cache),
            null
        );

        if ($cacheData !== null && is_string($cacheData)) {
            $cacheData = Sanitizer::maybeUnSerialize($cacheData);
        }

        return $cacheData;
    }

    /**
     * Get From Server
     *
     * @param string $domain
     * @param string $server
     * @param array $options
     *
     * @return WhoIsRequest
     */
    public function getRequest(string $domain, string $server, array $options = []) : WhoIsRequest
    {
        $request = new WhoIsRequest($domain, $server, $options);
        return $this->sanitizeAfterRequest($request->send());
    }

    /**
     * Sanitize for Request this for child class that maybe
     *
     * @param WhoIsRequest $request
     *
     * @return WhoIsRequest
     */
    protected function sanitizeAfterRequest(WhoIsRequest $request) : WhoIsRequest
    {
        $domain = $request->getTargetName();
        if ($domain && $this->getValidator()->isValidDomain($domain)) {
            $extension = $this->getValidator()->splitDomainName($domain)->getBaseExtension();
        }

        if (empty($extension)) {
            return $request;
        }

        // with extensions logic
        switch ($extension) {
            case 'ph':
                if (stripos($request->getServer(), 'https://whois.dot.ph/?') !== 0) {
                    return $request;
                }
                $body = $request->getBodyString();
                if (trim($body) === '') {
                    return $request;
                }
                preg_match(
                    '~
                      <div\b[^>]*?id\=(\")about\-whois(?:\\1)[^>]*+>\s*
                          <div\b[^>]*+>
                            ((?:(?!<\/main>)[\s\S])*)
                    ~ix',
                    $body,
                    $match
                );
                if (!empty($match[2])) {
                    $match = trim(preg_replace('~<br[^>]*>~i', "\n", $match[2]));
                    $match = preg_replace('~^[ ]+~m', '', strip_tags($match));
                    $request->setBodyString(trim($match));
                }
                break;
        }
        return $request;
    }

    /**
     * @param string $domainName     the domain Name
     * @param bool $requestFromIAna  try to get whois server from IANA
     *
     * @return array
     * @throws \Throwable
     */
    public function getWhoIsServerFor(string $domainName, bool $requestFromIAna = false) : array
    {
        $domainName = trim($domainName);
        if ($domainName === '') {
            throw new EmptyDomainException(
                'Domain name could not be empty or white space only'
            );
        }

        $validator  = $this->getValidator();
        // if invalid thrown error
        $domainRecord = $validator->splitDomainName($domainName);
        $extension  = $domainRecord->getBaseExtension();
        $servers = $validator->getTldCollector()->getServersFromExtension($extension);
        if (empty($servers)) {
            $domainKey = strtolower($domainName);
            $keyWhoIs = "{$domainKey}_whs";
            $whoIsServer = $this->getCache($keyWhoIs);
            if ($requestFromIAna && (empty($whoIsServer) || !is_array($whoIsServer))) {
                $iAnaRequest = $this->getRequest($domainName, DataParser::URI_IANA_WHOIS);
                if ($iAnaRequest->isTimeOut()) {
                    $iAnaRequest = $this->getRequest($domainName, DataParser::URI_IANA_WHOIS);
                }
                if ($iAnaRequest->isError()) {
                    throw $iAnaRequest->getResponse();
                }

                $whoIsServer = DataParser::getWhoIsServerFromResultData($iAnaRequest->getBodyString());
                if ($whoIsServer) {
                    $servers = [$whoIsServer];
                    $this->putCache($keyWhoIs, $servers);
                } else {
                    throw new WhoIsServerNotFoundException(
                        sprintf(
                            'Could not get whois server for: %s',
                            $domainName
                        ),
                        E_NOTICE
                    );
                }
            }
        }

        return (array) $servers;
    }

    /**
     * Get From Domain With Server
     *
     * @param string $domainName
     * @param string $server
     * @param int $retry
     *
     * @return WhoIsResult
     * @throws \Throwable
     */
    public function getFromDomainWithServer(
        string $domainName,
        string $server,
        int $retry = 2
    ) : WhoIsResult {
        if ($retry > static::MAX_RETRY) {
            $retry = static::MAX_RETRY;
        }

        $domainName = trim($domainName);
        if ($domainName === '') {
            throw new EmptyDomainException(
                'Domain name could not be empty or white space only'
            );
        }

        $keyCache = "{$domainName}_{$server}";
        $result = $this->getCache($keyCache);
        if ($result instanceof WhoIsResult) {
            return $result;
        }

        $record = $this->getValidator()->splitDomainName($domainName);
        if (! $record->isTopLevelDomain()) {
            throw new InvalidDomainException(
                sprintf(
                    'Domain name %s is not a valid top domain',
                    $record->getFullDomainName()
                ),
                E_NOTICE
            );
        }

        if ($record->isGTLD()
            && in_array($record->getBaseExtension(), $this->disAllowMainDomainExtension)
        ) {
            throw new InvalidDomainException(
                sprintf(
                    'Domain name Extension: (.%s) GTLD is not for public registration',
                    $record->getBaseExtension()
                ),
                E_NOTICE
            );
        }

        if ($record->isSTLD() && $record->isMaybeDisAllowToRegistered()) {
            throw new InvalidDomainException(
                sprintf(
                    'Domain name : %s has invalid as top level domain as STLD',
                    $domainName
                ),
                E_NOTICE
            );
        }

        $request = $this->getRequest(
            $record->getDomainName(),
            $server
        );

        while ($request->isTimeOut() && $retry > 0) {
            $retry -= 1;
            $request = $this->getRequest($domainName, $server);
        }

        if ($request->isError()) {
            throw $request->getResponse();
        }

        $whoIsResultClass = static::WHOIS_RESULT_CLASS;
        $result = new $whoIsResultClass($record, $request->getBodyString(), $request->getServer());
        $this->putCache($keyCache, $result);
        return $result;
    }

    /**
     * @param string $domainName
     *
     * @return string
     * @throws \Throwable
     */
    protected function createFakeResultFromEmptyServerDomain(string $domainName) : string
    {
        $record = trim(static::COMMENT_FAKE_RECORD);
        $record .= "\n\nDomain Name: {$domainName}\n";
        try {
            $err = null;
            // handle error
            set_error_handler(function ($code, $message, $file, $line) use (&$err) {
                $err = new HttpException(
                    $message,
                    $code
                );

                $err->setFile($file);
                $err->setLine($line);
            });
            // get dns
            $dns = (array)dns_get_record($domainName, DNS_NS);

            restore_error_handler();
            if ($err instanceof HttpException) {
                throw $err;
            }
        } catch (\Throwable $e) {
            throw $e;
        }

        if (!empty($dns)) {
            $record .= "Domain Status: Taken\n\n";
            foreach ($dns as $array) {
                if (!empty($array['target'])) {
                    $record .= "Name Server: {$array['target']}\n";
                }
            }
        } else {
            $record .= "Domain Status: Available\n";
        }
        try {
            $string = $this
                ->getFromDomainWithServer($domainName, DataParser::URI_IANA_WHOIS)
                ->getOriginalResultString();
            preg_match(
                '~
                    Registra(?:tion|ant|ar)s?\s+information(?:[^\:]+)?\:(?:\s*remarks:[\s]*)?
                    ((?>https?\:\/\/)[^\n\r]+)
                ~xi',
                $string,
                $match
            );
            if (!empty($match[1])) {
                $match[1] = trim($match[1]);
                $record .= "\n% For more information please visit : {$match[1]}";
            }
        } catch (\Throwable $e) {
            //
        }

        return $record;
    }

    /**
     * Get From Domain
     *
     * @param string $domainName
     * @param bool $followServer follow server if whois server exists
     * @param bool $allowEmptyServer use *Name Server (DNS)* if Server has empty default is true
     * @param bool $requestFromIAna  try to get whois server from IANA
     *
     * @return ArrayCollector|WhoIsResult[]
     * @throws \Throwable
     */
    public function getFromDomain(
        string $domainName,
        bool $followServer = false,
        bool $allowEmptyServer = true,
        bool $requestFromIAna = false
    ) : ArrayCollector {

        $record = $this->getValidator()->splitDomainName($domainName);
        if (! $record->isTopLevelDomain()) {
            throw new InvalidDomainException(
                sprintf(
                    'Domain name %s is not a valid top domain',
                    $record->getFullDomainName()
                ),
                E_NOTICE
            );
        }

        $domainName = strtolower($record->getDomainName());
        if ($allowEmptyServer && !$record->getWhoIsServers()) {
            $keyCache = "{$domainName}_fake_record";
            if (($result = $this->getCache($keyCache)) instanceof WhoIsResult) {
                $result->useCacheNameServer = true;
                return new ArrayCollector([$domainName => $result]);
            }
            try {
                $result = new WhoIsResult(
                    $record,
                    $this->createFakeResultFromEmptyServerDomain($domainName)
                );
            } catch (HttpException $e) {
                throw new ResultException(
                    sprintf(
                        'Could not get data for : %1$s, with error: %2$s',
                        $domainName,
                        $e->getMessage()
                    )
                );
            }

            $this->putCache($keyCache, $result);
            /** @noinspection PhpUndefinedFieldInspection */
            $result->useNameServerDNS = true;
            return new ArrayCollector([$domainName => $result]);
        }

        $servers = $this->getWhoIsServerFor($record->getDomainName(), $requestFromIAna);
        $isLimit = false;
        $usedServer = null;
        foreach ($servers as $server) {
            try {
                $usedServer = $server;
                $whoIsResult = $this->getFromDomainWithServer($domainName, $server);
                break;
            } catch (TimeOutException $e) {
                continue;
            } catch (RequestLimitException $e) {
                $isLimit = $e;
            } catch (\Throwable $e) {
                throw $e;
            }
            unset($whoIsResult);
        }

        if (!isset($whoIsResult)) {
            if ($isLimit) {
                throw $isLimit;
            }

            throw new ResultException(
                sprintf(
                    'Could not get data for : %s',
                    $domainName
                )
            );
        }

        // result
        $result = new ArrayCollector([$usedServer => $whoIsResult]);
        if ($followServer) {
            $alternatedServer = $whoIsResult->getWhoIsServerFromResult();
            if ($alternatedServer
                && ! in_array(
                    strtolower($alternatedServer),
                    // whois.iana.org is not allowed here
                    [strtolower($usedServer), DataParser::URI_IANA_WHOIS]
                )
            ) {
                try {
                    $whoIsResult = $this->getFromDomainWithServer($domainName, $alternatedServer);
                    $result[$alternatedServer] = $whoIsResult;
                } catch (\Throwable $e) {
                    // pass
                }
            }
        }

        return $result;
    }

    /**
     * @param string $domainName the domain name to check
     *
     * @return bool|null  boolean true if registered otherwise false,
     *                    or null if can not check domain
     */
    public function isDomainRegistered(string $domainName)
    {
        $record = $this->getValidator()->splitDomainName($domainName);
        if (! $record->isTopLevelDomain()) {
            throw new InvalidDomainException(
                sprintf(
                    'Domain name %s is not a valid top domain',
                    $record->getFullDomainName()
                ),
                E_NOTICE
            );
        }

        if (in_array($record->getMainDomainName(), $this->reservedNameTLDDomain)) {
            return true;
        }

        // check length
        if (is_numeric($this->minLength)
            && $this->minLength > 0
            && $this->minLength < 4
            && strlen($record->getMainDomainName()) <= $this->minLength
        ) {
            return true;
        }

        if (!$record->getWhoIsServers()) {
            $keyCache = $record->getDomainName() .'_availability';
            if (($result = $this->getCache($keyCache)) instanceof \stdClass
                && !empty($result->registered)
                && is_array($result->registered)
            ) {
                return !empty($result->registered);
            }

            $dns = dns_get_record($record->getDomainName(), DNS_NS);
            $result = new \stdClass();
            $result->registered = $dns;
            $this->putCache($keyCache, $result);
            return ! empty($result->registered);
        }

        /**
         * @var WhoIsResult $result
         */
        $result = $this->getFromDomain($record->getMainDomainName());
        $result = $result->last();
        $parser = $result->getDataParser();
        $status = $parser->getRegisteredDomainStatus($result->getOriginalResultString());
        return $status === $parser::REGISTERED
            || $status === $parser::RESERVED
            ? true
            : (
                $status === $parser::UNREGISTERED
                    ? false
                    : null
            );
    }
}