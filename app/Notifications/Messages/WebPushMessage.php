<?php

namespace App\Notifications\Messages;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Traits\Conditionable;
use NotificationChannels\WebPush\WebPushMessage as BaseWebPushMessage;

/**
 * A web push message whose buttons the service worker (sw.ts) can act on:
 * each button either opens a page in the app, or posts to a signed link,
 * so it works in the background without the user's Sanctum token.
 */
class WebPushMessage extends BaseWebPushMessage
{
    use Conditionable;

    /**
     * How long a button's signed link works after the push is sent.
     */
    public const SIGNED_ACTION_LIFETIME_DAYS = 7;

    /**
     * What each button does, keyed by its action name.
     *
     * @var array<string, array{url?: string, post?: string}>
     */
    protected array $links = [];

    /**
     * A button that opens $url in the app.
     */
    public function openAction(string $action, string $title, string $url): static
    {
        $this->links[$action] = ['url' => $url];

        return $this->action($title, $action);
    }

    /**
     * A button that posts to the signed $route in the background. The
     * signature stands in for the user's token, and only allows this one
     * action, until it expires.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function signedAction(string $action, string $title, string $route, array $parameters): static
    {
        $this->links[$action] = [
            'post' => URL::temporarySignedRoute(
                $route,
                now()->addDays(self::SIGNED_ACTION_LIFETIME_DAYS),
                $parameters,
                absolute: false
            ),
        ];

        return $this->action($title, $action);
    }

    /**
     * The links go in the notification's data, next to its url, where the
     * service worker reads them when a button is clicked.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return collect(parent::toArray())
            ->except('links')
            ->when(
                $this->links,
                fn ($message) => $message->put('data', [...(array) $this->data, 'actions' => $this->links])
            )
            ->all();
    }
}
