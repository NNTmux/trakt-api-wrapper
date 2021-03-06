<?php
use GuzzleHttp\Client;
use NNTmux\Trakt\Auth\Auth;
use NNTmux\Trakt\Auth\TraktProvider;
use NNTmux\Trakt\Trakt;

/**
 * Created by PhpStorm.
 * User: bwubs
 * Date: 22/02/15
 * Time: 13:44
 */
class TraktTest extends PHPUnit\Framework\TestCase
{
    public function testCanInitiateTrakt()
    {
        $provider = new TraktProvider(getenv("TRAKT_CLIENT_ID"), getenv("TRAKT_CLIENT_SECRET"), getenv("TRAKT_REDIRECT_URI"));
        $auth = new Auth($provider);

        $trakt = new Trakt($auth, new Client());

        $this->assertInstanceOf("NNTmux\\Trakt\\Trakt", $trakt);
    }

    public function testInvalidAuthorization()
    {
        $provider = new TraktProvider(getenv("TRAKT_CLIENT_ID"), getenv("TRAKT_CLIENT_SECRET"), getenv("TRAKT_REDIRECT_URI"));
        $auth = new Auth($provider);

        $trakt = new Trakt($auth, new Client());
        $_SESSION['trakt_oauth_state'] = "ADifferentState";
        $_GET['state'] = 'NotTheStateItShouldBe';

        $test = $trakt->auth->isValid();

        $this->assertFalse($test);
    }

    public function testValidAuthorization()
    {
        $provider = new TraktProvider(getenv("TRAKT_CLIENT_ID"), getenv("TRAKT_CLIENT_SECRET"), getenv("TRAKT_REDIRECT_URI"));
        $auth = new Auth($provider);

        $trakt = new Trakt($auth, new Client());

        $_SESSION['trakt_oauth_state'] = "AState";
        $_GET['state'] = 'AState';

        $test = $trakt->auth->isValid();

        $this->assertTrue($test);

    }
}
