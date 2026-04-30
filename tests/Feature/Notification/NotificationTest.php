<?php

namespace Tests\Feature\Notification;

use App\Helpers\ApiWhatsappHelper;
use App\Models\Charge;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_stub_returns_false_when_api_url_not_configured(): void
    {
        $helper = new ApiWhatsappHelper;
        $result = $helper->send('11999999999', 'Test message');

        $this->assertFalse($result);
    }

    public function test_notification_sends_whatsapp_when_configured(): void
    {
        $whatsappMock = $this->createMock(ApiWhatsappHelper::class);
        $whatsappMock->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $service = new NotificationService($whatsappMock);

        $charge = Charge::factory()->create();
        $charge->expenseParticipant->update(['phone' => '11999999999']);

        $service->notifyChargeRecipient($charge->fresh(['expenseParticipant']));
    }

    public function test_notification_logs_when_phone_missing(): void
    {
        $whatsappMock = $this->createMock(ApiWhatsappHelper::class);
        $whatsappMock->expects($this->never())
            ->method('send');

        $service = new NotificationService($whatsappMock);

        $charge = Charge::factory()->create();
        $charge->expenseParticipant->update(['phone' => '']);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'missing phone'));

        $service->notifyChargeRecipient($charge->fresh(['expenseParticipant']));
    }

    public function test_notification_logs_when_whatsapp_fails(): void
    {
        $whatsappMock = $this->createMock(ApiWhatsappHelper::class);
        $whatsappMock->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $service = new NotificationService($whatsappMock);

        $charge = Charge::factory()->create();
        $charge->expenseParticipant->update(['phone' => '11999999999']);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'not delivered'));

        $service->notifyChargeRecipient($charge->fresh(['expenseParticipant']));
    }

    public function test_whatsapp_helper_sends_real_http_post(): void
    {
        config()->set('services.whatsapp.api_url', 'https://api.whatsapp.test/send');
        config()->set('services.whatsapp.api_token', 'test-token-123');

        Http::fake([
            'api.whatsapp.test/*' => Http::response(['success' => true], 200),
        ]);

        $helper = new ApiWhatsappHelper;
        $result = $helper->send('11999999999', 'Hello test');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.whatsapp.test/send'
                && $request['phone'] === '11999999999'
                && $request['message'] === 'Hello test'
                && $request->hasHeader('Authorization', 'Bearer test-token-123');
        });
    }

    public function test_whatsapp_helper_returns_false_on_server_error(): void
    {
        config()->set('services.whatsapp.api_url', 'https://api.whatsapp.test/send');

        Http::fake([
            'api.whatsapp.test/*' => Http::response(['error' => 'Internal'], 500),
        ]);

        $helper = new ApiWhatsappHelper;
        $result = $helper->send('11999999999', 'Hello');

        $this->assertFalse($result);
    }

    public function test_whatsapp_helper_returns_false_on_client_error(): void
    {
        config()->set('services.whatsapp.api_url', 'https://api.whatsapp.test/send');

        Http::fake([
            'api.whatsapp.test/*' => Http::response(['error' => 'Bad request'], 422),
        ]);

        $helper = new ApiWhatsappHelper;
        $result = $helper->send('11999999999', 'Hello');

        $this->assertFalse($result);
    }
}
