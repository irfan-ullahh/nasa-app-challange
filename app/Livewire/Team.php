<?php

namespace App\Livewire;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Team Members — ')]
class Team extends Component
{
    public function render(): View
    {
        $members = [
            [
                'name' => 'Irfan Ullah Amin',
                'role' => 'Team Lead & Full Stack Developer',
                'badge' => 'Team Lead',
                'initials' => 'images/irfan.jpg',
                'bio' => 'Architected the Elite Vision SAR intelligence platform, NASA POWER real-time telemetry ingestion pipeline, interactive Mapbox globe visualizations, and physics-informed analytics engine.',
                'stack' => ['Laravel', 'Livewire', 'Tailwind CSS', 'Mapbox GL', 'Chart.js', 'NASA POWER API'],
                'github' => 'https://github.com/irfan-ullahh',
                'linkedin' => 'https://www.linkedin.com/in/irfan-ullahh/',
            ],

            // Add your remaining team members below using the same structure:
            // [
            //     'name' => 'Member Name',
            //     'role' => 'Frontend & UI Engineer',
            //     'badge' => 'Core Member',
            //     'initials' => 'MN',
            //     'bio' => 'Designed responsive dashboard interfaces, dark-mode telemetry views, and interactive data layer visualizations.',
            //     'stack' => ['Tailwind CSS', 'Alpine.js', 'Blade'],
            //     'github' => 'https://github.com/',
            //     'linkedin' => 'https://www.linkedin.com/',
            // ],
        ];

        return view('livewire.team', [
            'members' => $members,
            'monitoredRegions' => SatelliteScan::count(),
            'telemetryRecords' => TelemetryLog::count(),
        ]);
    }
}
