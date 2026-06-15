<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\AdministrativeWorkLog;
use App\Models\Asset;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pulpit')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();

        return match (true) {
            $user->isAdminLevel() => view('livewire.dashboards.admin', $this->adminData()),
            $user->isSupport() => view('livewire.dashboards.support', $this->supportData($user)),
            $user->role === Role::Manager => view('livewire.dashboards.manager', $this->managerData($user)),
            default => view('livewire.dashboards.user', $this->userData($user)),
        };
    }

    /** @return array<string,mixed> */
    protected function adminData(): array
    {
        return [
            'organizationsCount' => Organization::count(),
            'usersCount' => User::count(),
            'openTicketsCount' => Ticket::open()->count(),
            'recentTickets' => Ticket::with(['organization', 'requester'])->latest()->limit(8)->get(),
            'recentWorkLogs' => AdministrativeWorkLog::with(['organization', 'performer'])->latest('performed_at')->limit(5)->get(),
        ];
    }

    /** @return array<string,mixed> */
    protected function supportData(User $user): array
    {
        $orgIds = $user->accessibleOrganizationIds()->all();

        return [
            'organizations' => $user->supportedOrganizations()->wherePivot('is_active', true)->get(),
            'newTickets' => Ticket::with(['organization', 'requester'])
                ->forOrganizations($orgIds)
                ->where('status', TicketStatus::New->value)
                ->latest()->limit(10)->get(),
            'myTickets' => Ticket::with('organization')
                ->where('assigned_support_id', $user->id)
                ->open()->latest('last_reply_at')->limit(10)->get(),
            'waitingUser' => Ticket::with('organization')
                ->forOrganizations($orgIds)
                ->where('status', TicketStatus::WaitingUser->value)
                ->latest('last_reply_at')->limit(10)->get(),
        ];
    }

    /** @return array<string,mixed> */
    protected function managerData(User $user): array
    {
        $orgIds = $user->accessibleOrganizationIds()->all();

        return [
            'openTickets' => Ticket::forOrganizations($orgIds)->open()->count(),
            'recentTickets' => Ticket::with(['organization', 'requester'])
                ->forOrganizations($orgIds)->latest()->limit(8)->get(),
            'assetsCount' => Asset::whereIn('organization_id', $orgIds)->active()->count(),
            'recentWorkLogs' => AdministrativeWorkLog::with(['organization', 'asset'])
                ->whereIn('organization_id', $orgIds)
                ->visibleToManager()->published()
                ->latest('performed_at')->limit(8)->get(),
        ];
    }

    /** @return array<string,mixed> */
    protected function userData(User $user): array
    {
        $orgIds = $user->accessibleOrganizationIds()->all();

        return [
            'myOpenTickets' => Ticket::where('requester_id', $user->id)->open()->latest()->get(),
            'myRecentTickets' => Ticket::where('requester_id', $user->id)->latest()->limit(6)->get(),
            'orgAssetsCount' => Asset::whereIn('organization_id', $orgIds)
                ->where('is_private', false)->active()->count(),
            'privateAssets' => $user->privateAssets()->with('category')->get(),
        ];
    }
}
