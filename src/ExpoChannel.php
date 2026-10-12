<?php

namespace NotificationChannels\Expo;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\Exceptions\CouldNotSendNotification;

class ExpoChannel
{
    /**
     * The Http Client.
     *
     * @var Client
     */
    protected $client;

    /**
     * Initialise a new Expo Push Channel instance.
     *
     * @param  Client  $client
     * @return void
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     *
     * @throws CouldNotSendNotification|GuzzleException
     */
    public function send($notifiable, Notification $notification)
    {
        if (! ($to = $notifiable->routeNotificationFor('expo'))) {
            throw CouldNotSendNotification::noValidDestination($notifiable);
        }

        if (! method_exists($notification, 'toExpo')) {
            throw CouldNotSendNotification::undefinedMethod($notification);
        }

        /** @var ExpoMessage $message */
        if (! ($message = $notification->toExpo($notification)) instanceof ExpoMessage) {
            throw CouldNotSendNotification::couldNotCreateMessage($notification);
        }

        if (! is_array($to)) {
            $to = [$to];
        }

        // One request per recipient: Expo rejects a whole batch with
        // PUSH_TOO_MANY_EXPERIENCE_IDS when the tokens belong to different
        // projects (e.g. a stale token from a previous app build next to the
        // current one), which used to drop the push for every device.
        $response = null;
        $firstClientError = null;

        foreach ($to as $recipient) {
            try {
                $response = $this->client->request(
                    'post',
                    'https://exp.host/--/api/v2/push/send',
                    ['json' => [array_merge(['to' => $recipient], $message->toArray())]]
                );

                if ($response->getStatusCode() !== 200) {
                    throw CouldNotSendNotification::serviceRespondedWithAnError($response);
                }
            } catch (ClientException $exception) {
                $firstClientError = $firstClientError ?? CouldNotSendNotification::clientError($exception);
                $response = null;
            } catch (Exception $exception) {
                throw CouldNotSendNotification::unexpectedException($exception);
            }
        }

        if ($response === null && $firstClientError !== null) {
            throw $firstClientError;
        }

        return $response;
    }
}
