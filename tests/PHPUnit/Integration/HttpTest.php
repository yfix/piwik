<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Integration;

use Piwik\Http;
use Piwik\Tests\Framework\Fixture;

/**
 * @group Core
 * @group HttpTest
 */
class HttpTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Dataprovider for testFetchRemoteFile
     */
    public function getMethodsToTest()
    {
        return array(
            'curl' => array('curl'),
            'fopen' => array('fopen'),
            'socket' => array('socket'),
        );
    }

    /**
     * @dataProvider getMethodsToTest
     */
    public function testFetchRemoteFile($method)
    {
        $this->assertNotNull(Http::getTransportMethod());
        $result = Http::sendHttpRequestBy($method, Fixture::getRootUrl() . 'piwik.js', 30);
        $this->assertTrue(strpos($result, 'Piwik') !== false);
    }

    public function testFetchApiLatestVersion()
    {
        $destinationPath = PIWIK_USER_PATH . '/tmp/latest/LATEST';
        Http::fetchRemoteFile(Fixture::getRootUrl(), $destinationPath, 3);
        $this->assertFileExists($destinationPath);
        $this->assertGreaterThan(0, filesize($destinationPath));
    }

    public function testFetchLatestZip()
    {
        $destinationPath = PIWIK_USER_PATH . '/tmp/latest/latest.zip';
        Http::fetchRemoteFile(Fixture::getRootUrl() . 'tests/PHPUnit/Integration/Http/fixture.zip', $destinationPath, 3, 30);
        $this->assertFileExists($destinationPath);
        $this->assertGreaterThan(0, filesize($destinationPath));
    }

    /**
     * @dataProvider getMethodsToTest
     */
    public function testCustomByteRange($method)
    {
        $result = Http::sendHttpRequestBy(
            $method,
            Fixture::getRootUrl() . '/piwik.js',
            30,
            $userAgent = null,
            $destinationPath = null,
            $file = null,
            $followDepth = 0,
            $acceptLanguage = false,
            $acceptInvalidSslCertificate = false,
            $byteRange = array(10, 20),
            $getExtendedInfo = true
        );

        if ($method != 'fopen') {
            $this->assertEquals(206, $result['status']);
            $this->assertTrue(isset($result['headers']['Content-Range']));
            $this->assertEquals('bytes 10-20/', substr($result['headers']['Content-Range'], 0, 12));
            $this->assertTrue( in_array($result['headers']['Content-Type'], array('application/x-javascript', 'application/javascript')));
        }
    }

    /**
     * @dataProvider getMethodsToTest
     */
    public function testHEADOperation($method)
    {
        if ($method == 'fopen') {
            return; // not supported w/ this method
        }

        $result = Http::sendHttpRequestBy(
            $method,
            Fixture::getRootUrl() . 'tests/PHPUnit/Integration/Http/fixture.zip',
            30,
            $userAgent = null,
            $destinationPath = null,
            $file = null,
            $followDepth = 0,
            $acceptLanguage = false,
            $acceptInvalidSslCertificate = false,
            $byteRange = false,
            $getExtendedInfo = true,
            $httpMethod = 'HEAD'
        );

        $this->assertEquals('', $result['data']);
        $this->assertEquals(200, $result['status']);

        $this->assertTrue(isset($result['headers']['Content-Length']), "Content-Length header not set!");
        $this->assertTrue(is_numeric($result['headers']['Content-Length']), "Content-Length header not numeric!");
        $this->assertEquals('application/zip', $result['headers']['Content-Type']);
    }
}
