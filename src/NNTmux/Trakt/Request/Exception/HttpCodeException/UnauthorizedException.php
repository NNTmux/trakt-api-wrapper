<?php
namespace NNTmux\Trakt\Request\Exception\HttpCodeException;


class UnauthorizedException extends \Exception
{

    protected $message = 'Unauthorized - OAuth must be provided';

}
