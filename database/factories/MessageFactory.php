<?php

namespace Database\Factories;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => null,
            'contact_id' => Contact::factory(),
            'channel_id' => Channel::factory(),
            'direction' => MessageDirection::Incoming,
            'payload_type' => 'text',
            'provider_message_id' => fake()->uuid(),
            'content' => fake()->sentence(),
            'delivery_status' => null,
            'is_responded' => false,
        ];
    }

    /**
     * Configure the factory to keep its default related records in one team.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Message $message): void {
            if ($message->channel->team_id === $message->contact->team_id) {
                return;
            }

            $message->channel->update(['team_id' => $message->contact->team_id]);
        });
    }

    /**
     * Indicate that the message came from the contact.
     */
    public function incoming(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => MessageDirection::Incoming,
            'delivery_status' => null,
        ]);
    }

    /**
     * Indicate that the message was sent by the Agent.
     */
    public function outgoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => MessageDirection::Outgoing,
            'delivery_status' => MessageDeliveryStatus::Pending,
        ]);
    }

    /**
     * Indicate that an incoming message has already been handled.
     */
    public function responded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_responded' => true,
        ]);
    }

    /**
     * Indicate that an incoming message still needs a response.
     */
    public function unresponded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_responded' => false,
        ]);
    }
}
