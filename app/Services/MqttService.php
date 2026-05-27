<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MqttService
{
    private ?\PhpMqtt\Client\MqttClient $client = null;
    private bool $connected = false;

    private function connect(): void
    {
        if ($this->connected) return;

        try {
            $host = config('mqtt.host', '127.0.0.1');
            $port = (int) config('mqtt.port', 1883);
            $clientId = 'gas-backend-' . uniqid();

            $this->client = new \PhpMqtt\Client\MqttClient($host, $port, $clientId);

            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'))
                ->setKeepAliveInterval(60)
                ->setConnectTimeout(10);

            $this->client->connect($connectionSettings, true);
            $this->connected = true;
        } catch (\Exception $e) {
            Log::error('MQTT connection failed: ' . $e->getMessage());
        }
    }

    public function publishValveCommand(string $mqttTopic, bool $open): void
    {
        $this->connect();

        if (! $this->connected || ! $this->client) {
            Log::warning("MQTT not connected. Cannot send valve command to {$mqttTopic}");
            return;
        }

        try {
            $payload = json_encode(['valve' => $open ? 'open' : 'close', 'ts' => now()->timestamp]);
            $this->client->publish("gas/{$mqttTopic}/command", $payload, 1);
            Log::info("Valve command sent: gas/{$mqttTopic}/command → " . ($open ? 'open' : 'close'));
        } catch (\Exception $e) {
            Log::error('MQTT publish failed: ' . $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        if ($this->connected && $this->client) {
            $this->client->disconnect();
            $this->connected = false;
        }
    }
}
