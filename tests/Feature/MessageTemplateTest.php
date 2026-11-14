<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessageTemplateTest extends TestCase
{
    public function test_preview_never_sends_and_missing_configuration_blocks_send(): void
    {
        Http::fake();
        $this->artisan('messages:test-templates')->assertSuccessful();
        config(['services.messaging.test_recipient' => null]);
        $this->artisan('messages:test-templates --send --run=pilot')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_sends_six_samples_only_to_configured_number_with_stable_keys(): void
    {
        config([
            'services.messaging.test_recipient' => '71996651964',
            'services.messaging.baileys.url' => 'http://bridge',
            'services.messaging.baileys.token' => 'test',
        ]);
        Http::fake([
            'bridge/health' => Http::response(['connected' => true]),
            'bridge/welcome' => Http::response(['id' => 'test-id']),
        ]);
        $this->artisan('messages:test-templates --send --run=pilot')->assertSuccessful();
        Http::assertSentCount(7);
        $keys = [];
        foreach (Http::recorded() as [$request]) {
            if ($request->method() === 'POST') {
                $this->assertSame('+5571996651964', $request['to']);
                $this->assertStringStartsWith('welcome-test:0:pilot_', $request['key']);
                $this->assertStringStartsWith('[TESTE WNFit', $request['body']);
                $keys[] = $request['key'];
            }
        }
        $this->assertCount(6, array_unique($keys));
    }

    public function test_uncertain_result_interrupts_batch_without_retry(): void
    {
        config([
            'services.messaging.test_recipient' => '71996651964',
            'services.messaging.baileys.url' => 'http://bridge',
            'services.messaging.baileys.token' => 'test',
        ]);
        Http::fake([
            'bridge/health' => Http::response(['connected' => true]),
            'bridge/welcome' => Http::response(['error' => 'uncertain'], 409),
        ]);
        $this->artisan('messages:test-templates --send --run=pilot')->assertFailed();
        Http::assertSentCount(2);
    }
}
