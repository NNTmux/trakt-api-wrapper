<?php
use NNTmux\Trakt\Request\RequestType;
use NNTmux\Trakt\Request\Users\Settings;
use NNTmux\Trakt\Trakt;

/**
 * Created by PhpStorm.
 * User: bwubs
 * Date: 25/02/15
 * Time: 14:18
 */
class SettingsTest extends PHPUnit\Framework\TestCase
{

    public function testInitiation()
    {
        $settingsRequest = new Settings(get_token());

        $this->assertEquals(RequestType::GET, $settingsRequest->getRequestType());
        $this->assertEquals("users/settings", $settingsRequest->getUrl());
    }
}
