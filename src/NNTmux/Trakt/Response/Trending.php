<?php
/**
 * Created by PhpStorm.
 * User: bwubs
 * Date: 18/03/15
 * Time: 14:39
 */

namespace NNTmux\Trakt\Response;


use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use NNTmux\Trakt\Media\Movie;

class Trending
{
    public $watchers;

    /**
     * @var \NNTmux\Trakt\Media\Movie
     */
    public $movie;

    /**
     * @param $json
     * @param $id
     * @param AccessToken $token
     * @param ClientInterface $client
     */
    public function __construct($json, $id, AccessToken $token, ClientInterface $client)
    {
        $this->watchers = $json->watchers;
        $this->movie = new Movie($json->movie, $id, $token, $client);
    }
}