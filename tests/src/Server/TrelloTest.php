<?php namespace Stevenmaguire\OAuth1\Client\Test\Server;

use Stevenmaguire\OAuth1\Client\Server\Trello;
use League\OAuth1\Client\Credentials\ClientCredentials;
use Mockery as m;
use PHPUnit_Framework_TestCase;

class TrelloTest extends PHPUnit_Framework_TestCase
{
    /**
     * Close mockery.
     *
     * @return void
     */
    public function tearDown()
    {
        m::close();
    }

    public function testCreatingWithArray()
    {
        $server = new Trello($this->getMockClientCredentials());

        $credentials = $server->getClientCredentials();
        $this->assertInstanceOf('League\OAuth1\Client\Credentials\ClientCredentialsInterface', $credentials);
        $this->assertEquals('myidentifier', $credentials->getIdentifier());
        $this->assertEquals('mysecret', $credentials->getSecret());
        $this->assertEquals('http://app.dev/', $credentials->getCallbackUri());
    }

    public function testCreatingWithObject()
    {
        $credentials = new ClientCredentials;
        $credentials->setIdentifier('myidentifier');
        $credentials->setSecret('mysecret');
        $credentials->setCallbackUri('http://app.dev/');

        $server = new Trello($credentials);

        $this->assertEquals($credentials, $server->getClientCredentials());
    }

    public function testGettingTemporaryCredentials()
    {
        $server = m::mock('Stevenmaguire\OAuth1\Client\Server\Trello[createHttpClient]');
        $server->__construct($this->getMockClientCredentials());

        $server->shouldReceive('createHttpClient')->andReturn($client = m::mock('stdClass'));

        $me = $this;
        $client->shouldReceive('post')->with('https://trello.com/1/OAuthGetRequestToken', m::on(function($headers) use ($me) {
            $me->assertTrue(isset($headers['Authorization']));

            // OAuth protocol specifies a strict number of
            // headers should be sent, in the correct order.
            // We'll validate that here.
            $pattern = '/OAuth oauth_consumer_key=".*?", oauth_nonce="[a-zA-Z0-9]+", oauth_signature_method="HMAC-SHA1", oauth_timestamp="\d{10}", oauth_version="1.0", oauth_callback="'.preg_quote('http%3A%2F%2Fapp.dev%2F', '/').'", oauth_signature=".*?"/';

            $matches = preg_match($pattern, $headers['Authorization']);
            $me->assertEquals(1, $matches, 'Asserting that the authorization header contains the correct expression.');

            return true;
        }))->once()->andReturn($request = m::mock('stdClass'));

        $request->shouldReceive('send')->once()->andReturn($response = m::mock('stdClass'));
        $response->shouldReceive('getBody')->andReturn('oauth_token=temporarycredentialsidentifier&oauth_token_secret=temporarycredentialssecret&oauth_callback_confirmed=true');

        $credentials = $server->getTemporaryCredentials();
        $this->assertInstanceOf('League\OAuth1\Client\Credentials\TemporaryCredentials', $credentials);
        $this->assertEquals('temporarycredentialsidentifier', $credentials->getIdentifier());
        $this->assertEquals('temporarycredentialssecret', $credentials->getSecret());
    }

    public function testGettingAuthorizationUrl()
    {
        $server = new Trello($this->getMockClientCredentials());

        $expected = 'https://trello.com/1/OAuthAuthorizeToken?oauth_token=foo';

        $this->assertEquals($expected, $server->getAuthorizationUrl('foo'));

        $credentials = m::mock('League\OAuth1\Client\Credentials\TemporaryCredentials');
        $credentials->shouldReceive('getIdentifier')->andReturn('foo');
        $this->assertEquals($expected, $server->getAuthorizationUrl($credentials));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGettingTokenCredentialsFailsWithManInTheMiddle()
    {
        $server = new Trello($this->getMockClientCredentials());

        $credentials = m::mock('League\OAuth1\Client\Credentials\TemporaryCredentials');
        $credentials->shouldReceive('getIdentifier')->andReturn('foo');

        $server->getTokenCredentials($credentials, 'bar', 'verifier');
    }

    public function testGettingTokenCredentials()
    {
        $server = m::mock('Stevenmaguire\OAuth1\Client\Server\Trello[createHttpClient]');
        $server->__construct($this->getMockClientCredentials());

        $temporaryCredentials = m::mock('League\OAuth1\Client\Credentials\TemporaryCredentials');
        $temporaryCredentials->shouldReceive('getIdentifier')->andReturn('temporarycredentialsidentifier');
        $temporaryCredentials->shouldReceive('getSecret')->andReturn('temporarycredentialssecret');

        $server->shouldReceive('createHttpClient')->andReturn($client = m::mock('stdClass'));

        $me = $this;
        $client->shouldReceive('post')->with('https://trello.com/1/OAuthGetAccessToken', m::on(function($headers) use ($me) {
            $me->assertTrue(isset($headers['Authorization']));

            // OAuth protocol specifies a strict number of
            // headers should be sent, in the correct order.
            // We'll validate that here.
            $pattern = '/OAuth oauth_consumer_key=".*?", oauth_nonce="[a-zA-Z0-9]+", oauth_signature_method="HMAC-SHA1", oauth_timestamp="\d{10}", oauth_version="1.0", oauth_token="temporarycredentialsidentifier", oauth_signature=".*?"/';

            $matches = preg_match($pattern, $headers['Authorization']);
            $me->assertEquals(1, $matches, 'Asserting that the authorization header contains the correct expression.');

            return true;
        }), array('oauth_verifier' => 'myverifiercode'))->once()->andReturn($request = m::mock('stdClass'));

        $request->shouldReceive('send')->once()->andReturn($response = m::mock('stdClass'));
        $response->shouldReceive('getBody')->andReturn('oauth_token=tokencredentialsidentifier&oauth_token_secret=tokencredentialssecret');

        $credentials = $server->getTokenCredentials($temporaryCredentials, 'temporarycredentialsidentifier', 'myverifiercode');
        $this->assertInstanceOf('League\OAuth1\Client\Credentials\TokenCredentials', $credentials);
        $this->assertEquals('tokencredentialsidentifier', $credentials->getIdentifier());
        $this->assertEquals('tokencredentialssecret', $credentials->getSecret());
    }

    public function testGettingUserDetails()
    {
        $server = m::mock('Stevenmaguire\OAuth1\Client\Server\Trello[createHttpClient,protocolHeader]');
        $server->__construct($this->getMockClientCredentials());

        $temporaryCredentials = m::mock('League\OAuth1\Client\Credentials\TokenCredentials');
        $temporaryCredentials->shouldReceive('getIdentifier')->andReturn('tokencredentialsidentifier');
        $temporaryCredentials->shouldReceive('getSecret')->andReturn('tokencredentialssecret');

        $server->shouldReceive('createHttpClient')->andReturn($client = m::mock('stdClass'));

        $me = $this;
        $client->shouldReceive('get')->with('https://trello.com/1/members/me?key='.$this->getApplicationKey().'&token='.$this->getAccessToken(), m::on(function($headers) use ($me) {
            $me->assertTrue(isset($headers['Authorization']));

            // OAuth protocol specifies a strict number of
            // headers should be sent, in the correct order.
            // We'll validate that here.
            $pattern = '/OAuth oauth_consumer_key=".*?", oauth_nonce="[a-zA-Z0-9]+", oauth_signature_method="HMAC-SHA1", oauth_timestamp="\d{10}", oauth_version="1.0", oauth_token="tokencredentialsidentifier", oauth_signature=".*?"/';

            $matches = preg_match($pattern, $headers['Authorization']);
            $me->assertEquals(1, $matches, 'Asserting that the authorization header contains the correct expression.');

            return true;
        }))->once()->andReturn($request = m::mock('stdClass'));

        $request->shouldReceive('send')->once()->andReturn($response = m::mock('stdClass'));
        $response->shouldReceive('json')->once()->andReturn($this->getUserPayload());

        $user = $server
            ->setApplicationKey($this->getApplicationKey())
            ->setAccessToken($this->getAccessToken())
            ->getUserDetails($temporaryCredentials);
        $this->assertInstanceOf('League\OAuth1\Client\Server\User', $user);
        $this->assertEquals('Matilda Wormwood', $user->name);
        $this->assertEquals('545df696e29c0dddaed31967', $server->getUserUid($temporaryCredentials));
        $this->assertEquals(null, $server->getUserEmail($temporaryCredentials));
        $this->assertEquals('matildawormwood12', $server->getUserScreenName($temporaryCredentials));
    }

    protected function getMockClientCredentials()
    {
        return array(
            'identifier' => 'myidentifier',
            'secret' => 'mysecret',
            'callback_uri' => 'http://app.dev/',
        );
    }

    protected function getApplicationKey()
    {
        return 'abcdefghijk';
    }

    protected function getAccessToken()
    {
        return 'lmnopqrstuvwxyz';
    }

    private function getUserPayload()
    {
        $user = '{
            "id": "545df696e29c0dddaed31967",
            "avatarHash": null,
            "bio": "I have magical powers",
            "bioData": null,
            "confirmed": true,
            "fullName": "Matilda Wormwood",
            "idPremOrgsAdmin": [],
            "initials": "MW",
            "memberType": "normal",
            "products": [],
            "status": "idle",
            "url": "https://trello.com/matildawormwood12",
            "username": "matildawormwood12",
            "avatarSource": "none",
            "email": null,
            "gravatarHash": "39aaaada0224f26f0bb8f1965326dcb7",
            "idBoards": [
                "545df696e29c0dddaed31968",
                "545e01d6c7b2dd962b5b46cb"
            ],
            "idOrganizations": [
                "54adfd79f9aea14f84009a85",
                "54adfde13b0e706947bc4789"
            ],
            "loginTypes": null,
            "oneTimeMessagesDismissed": [],
            "prefs": {
                "sendSummaries": true,
                "minutesBetweenSummaries": 1,
                "minutesBeforeDeadlineToNotify": 1440,
                "colorBlind": false,
                "timezoneInfo": {
                    "timezoneNext": "CDT",
                    "dateNext": "2015-03-08T08:00:00.000Z",
                    "offsetNext": 300,
                    "timezoneCurrent": "CST",
                    "offsetCurrent": 360
                }
            },
            "trophies": [],
            "uploadedAvatarHash": null,
            "premiumFeatures": [],
            "idBoardsPinned": null
        }';
        return json_decode($user, true);
    }
}
