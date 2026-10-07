<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Alerts;

use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Alert::class)]
final class AlertTest extends TestCase
{
    public function testItCreatesAnUnreadAlert(): void
    {
        $createdAt = new \DateTimeImmutable('2026-09-16 08:00:00');

        $alert = Alert::create(
            id: 11,
            householdId: 'house-1',
            memberId: 7,
            title: 'Water plants',
            body: 'Remember to water the balcony plants.',
            icon: 'droplets',
            urgent: false,
            createdAt: $createdAt
        );

        $this->assertSame(11, $alert->id());
        $this->assertSame('house-1', $alert->householdId());
        $this->assertSame(7, $alert->memberId());
        $this->assertSame('Water plants', $alert->title());
        $this->assertSame('Remember to water the balcony plants.', $alert->body());
        $this->assertSame('droplets', $alert->icon());
        $this->assertFalse($alert->urgent());
        $this->assertSame(AlertStatus::UNREAD, $alert->status());
        $this->assertSame($createdAt, $alert->createdAt());
    }

    public function testItCanBeDismissed(): void
    {
        $alert = Alert::create(
            id: 12,
            householdId: 'house-2',
            memberId: null,
            title: 'Milk is almost expired',
            body: 'Use the milk in the fridge before tonight.',
            icon: 'milk',
            urgent: true,
            createdAt: new \DateTimeImmutable('2026-09-17 09:30:00')
        );

        $alert->dismiss();

        $this->assertSame(AlertStatus::DISMISSED, $alert->status());
    }
}
