<?php namespace NNTmux\Trakt\Request\Users;

use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;
use League\OAuth2\Client\Token\AccessToken;
use NNTmux\Trakt\Request\AbstractRequest;
use NNTmux\Trakt\Request\RequestType;

/**
 * Created by PhpStorm.
 * User: bwubs
 * Date: 25/02/15
 * Time: 13:42
 */
class Settings extends AbstractRequest
{

    public function __construct(AccessToken $token)
    {
        parent::__construct();
        $this->setToken($token);
    }

    public function getRequestType()
    {
        return RequestType::GET;
    }

    public function getUri()
    {
        return "users/settings";
    }
}