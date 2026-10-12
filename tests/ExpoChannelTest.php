<?php

namespace NotificationChannels\Expo\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NotificationChannels\Expo\ExpoChannel;

class ExpoChannelTest extends TestCase
{
    /** @test */
    public function can_send_notification_to_notifible()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"data":{"status":"ok","id":"0b1c6dbf-77c9-4b9a-b037-33cc8b297c9a"}}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new Client(['handler' => $handlerStack]);

        $channel = new ExpoChannel($guzzle);

        $response = $channel->send(new Notifiable(), new TestNotification());
        $json = json_decode($response->getBody());
        $this->assertTrue($json->data->status === 'ok');
    }

    /** @test */
    public function sends_one_request_per_recipient()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"data":{"id":"e74c5f71-c9f0-4dcb-95b9-3dd6bc8d4b0f","status":"ok"}}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data":{"status":"ok","id":"d46deba0-1b77-482e-bb29-af2331b7b8d8"}}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new Client(['handler' => $handlerStack]);

        $channel = new ExpoChannel($guzzle);

        $response = $channel->send(new NotifiableArray(), new TestNotification());
        $json = json_decode($response->getBody());
        $this->assertTrue($json->data->status === 'ok');
        $this->assertSame(0, $mock->count());
        $this->assertSame('ExponentPushToken[yyyyyyyyyyyyyyyyyyyyyy]', json_decode($mock->getLastRequest()->getBody())[0]->to);
    }

    /** @test */
    public function a_rejected_recipient_does_not_fail_the_others()
    {
        $mock = new MockHandler([
            new Response(400, ['Content-Type' => 'application/json'], '{"errors":[{"code":"PUSH_TOO_MANY_EXPERIENCE_IDS","type":"USER"}]}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data":{"status":"ok","id":"d46deba0-1b77-482e-bb29-af2331b7b8d8"}}'),
        ]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);

        $response = (new ExpoChannel($guzzle))->send(new NotifiableArray(), new TestNotification());

        $this->assertSame('ok', json_decode($response->getBody())->data->status);
    }

    /** @test */
    public function throws_when_every_recipient_is_rejected()
    {
        $mock = new MockHandler([
            new Response(400, [], '{"errors":[{"code":"PUSH_TOO_MANY_EXPERIENCE_IDS"}]}'),
            new Response(400, [], '{"errors":[{"code":"PUSH_TOO_MANY_EXPERIENCE_IDS"}]}'),
        ]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);

        $this->expectException(\NotificationChannels\Expo\Exceptions\CouldNotSendNotification::class);

        (new ExpoChannel($guzzle))->send(new NotifiableArray(), new TestNotification());
    }
}
