<?php

use App\Enums\NotificationEvent;
use App\Livewire\Intern\Settings\Notifications;
use App\Models\PushSubscription;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Staff\TicketNotification;
use App\Services\WebPushService;
use App\Support\AppSettings;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;

beforeEach(function () {
    $this->user = User::factory()->staff()->create();
    $this->actingAs($this->user);
});

function browserSubscription(string $endpoint = 'https://push.example.com/abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyOfTheBrowser', 'auth' => 'AuthSecret']];
}

/**
 * WebPushService with a fake client that answers with the given status per endpoint.
 *
 * @param  array<string, int>  $statuses
 */
function fakePush(array $statuses, ?array &$sent = null): void
{
    $sent = [];
    app()->instance(WebPushService::class, new WebPushService(app(AppSettings::class), function () use ($statuses, &$sent) {
        $client = Mockery::mock(WebPush::class);
        $queued = [];
        $client->shouldReceive('queueNotification')->andReturnUsing(function ($subscription, $payload) use (&$queued, &$sent) {
            $queued[] = $subscription->getEndpoint();
            $sent[] = json_decode($payload, true);
        });
        $client->shouldReceive('flush')->andReturnUsing(function () use (&$queued, $statuses) {
            foreach ($queued as $endpoint) {
                $status = $statuses[$endpoint] ?? 201;
                yield new MessageSentReport(new Request('POST', $endpoint), new Response($status), $status < 300, $status < 300 ? 'OK' : 'Gone');
            }
        });

        return $client;
    }));
}

it('creates the VAPID key pair once and keeps the private key encrypted', function () {
    $key = app(WebPushService::class)->publicKey();

    expect(app(WebPushService::class)->publicKey())->toBe($key);

    $stored = app(AppSettings::class)->get('push_vapid');
    expect($stored['public'])->toBe($key)
        ->and(Crypt::decryptString($stored['private']))->not->toBeEmpty();
});

it('registers, lists and removes devices', function () {
    $component = Livewire::test(Notifications::class)
        ->assertSee('Auf diesem Gerät aktivieren')
        ->call('subscribe', browserSubscription(), 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile Safari/537.36')
        ->assertSee('Chrome auf Android');

    // The same browser again updates the device instead of adding one.
    $component->call('subscribe', browserSubscription(), 'Mozilla/5.0 (Linux; Android 14) Chrome/131.0');
    expect($this->user->pushSubscriptions()->count())->toBe(1);

    $component->call('unsubscribe', 'https://push.example.com/abc');
    expect($this->user->pushSubscriptions()->count())->toBe(0);
});

it('rejects invalid subscriptions and devices of other users', function () {
    Livewire::test(Notifications::class)
        ->call('subscribe', browserSubscription('http://unsicher.example.com/x'), 'Firefox')
        ->assertHasErrors('endpoint');

    $other = User::factory()->staff()->create();
    $device = app(WebPushService::class)->subscribe($other, browserSubscription());

    Livewire::test(Notifications::class)->call('removeDevice', $device->id);
    expect($device->fresh())->not->toBeNull();
});

it('sends to all devices and forgets expired ones', function () {
    $service = app(WebPushService::class);
    $service->subscribe($this->user, browserSubscription('https://push.example.com/phone'));
    $service->subscribe($this->user, browserSubscription('https://push.example.com/old'));
    fakePush(['https://push.example.com/old' => 410], $sent);

    $delivered = app(WebPushService::class)->send($this->user, ['title' => 'Hallo', 'url' => '/intern']);

    expect($delivered)->toBe(1)
        ->and($sent[0])->toMatchArray(['title' => 'Hallo', 'url' => '/intern'])
        ->and(PushSubscription::pluck('endpoint')->all())->toBe(['https://push.example.com/phone']);
});

it('sends a test notification from the settings', function () {
    app(WebPushService::class)->subscribe($this->user, browserSubscription());
    fakePush([], $sent);

    Livewire::test(Notifications::class)->call('sendTest')->assertDispatched('toast', message: 'Test an 1 Gerät gesendet.');
    expect($sent[0]['title'])->toStartWith('Test von');
});

it('adds push to ticket notifications of users with a device', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Drucker defekt']);
    $notification = new TicketNotification(NotificationEvent::CustomerReply, $ticket, 'Kunde hat geantwortet', 'Geht wieder nicht');

    expect($notification->via($this->user))->toBe(['database', 'mail']);

    app(WebPushService::class)->subscribe($this->user, browserSubscription());
    expect($notification->via($this->user))->toBe(['database', 'mail', 'push'])
        ->and($notification->toWebPush($this->user))->toMatchArray([
            'title' => 'Kunde hat geantwortet · '.$ticket->reference(),
            'url' => route('intern.tickets.show', $ticket),
            'tag' => 'ticket-'.$ticket->id,
        ]);

    // Switched off in the settings.
    $this->user->update(['notification_settings' => [NotificationEvent::CustomerReply->value => ['database' => true, 'mail' => true, 'push' => false]]]);
    expect($notification->via($this->user->fresh()))->toBe(['database', 'mail']);
});

it('serves the web app manifest and the service worker', function () {
    $this->get('/manifest.json')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('start_url', '/intern')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('icons.0.purpose', 'any')
        ->assertJsonPath('icons.2.purpose', 'maskable');
    $this->get(route('manifest', ['start' => 'portal']))->assertJsonPath('start_url', '/portal')->assertJsonPath('id', '/portal');

    foreach (['icons/icon-any-192.png', 'icons/icon-any-512.png', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/apple-touch-icon.png', 'apple-touch-icon.png'] as $file) {
        expect(public_path($file))->toBeFile();
    }
    expect(file_get_contents(public_path('sw.js')))->toContain("addEventListener('push'");
});

it('marks every page as installable for iPhone and Android', function () {
    $this->get('/intern')
        ->assertSee('<link rel="manifest" href="'.route('manifest').'">', false)
        ->assertSee('<link rel="apple-touch-icon" sizes="180x180"', false)
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="'.config('app.name').'">', false);

    $this->get('/portal/login')->assertSee(route('manifest', ['start' => 'portal']), false);
});
