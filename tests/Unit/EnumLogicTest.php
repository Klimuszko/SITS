<?php

namespace Tests\Unit;

use App\Enums\SupportScope;
use App\Enums\TicketStatus;
use PHPUnit\Framework\TestCase;

/**
 * Testy czystej logiki domenowej (bez bazy danych).
 * Pełne testy reguł dostępu (policies) – kolejny etap (feature/DB).
 */
class EnumLogicTest extends TestCase
{
    public function test_closed_and_cancelled_tickets_are_terminal(): void
    {
        $this->assertTrue(TicketStatus::Closed->isTerminal());
        $this->assertTrue(TicketStatus::Cancelled->isTerminal());
        $this->assertFalse(TicketStatus::New->isTerminal());
        $this->assertTrue(TicketStatus::New->isOpen());
    }

    public function test_open_cases_excludes_terminal_statuses(): void
    {
        $open = TicketStatus::openCases();

        $this->assertNotContains(TicketStatus::Closed, $open);
        $this->assertNotContains(TicketStatus::Cancelled, $open);
        $this->assertContains(TicketStatus::WaitingUser, $open);
    }

    public function test_support_scope_all_covers_every_area(): void
    {
        $this->assertTrue(SupportScope::All->covers(SupportScope::Tickets));
        $this->assertTrue(SupportScope::All->covers(SupportScope::Assets));
        $this->assertTrue(SupportScope::Tickets->covers(SupportScope::Tickets));
        $this->assertFalse(SupportScope::Tickets->covers(SupportScope::Assets));
    }
}
