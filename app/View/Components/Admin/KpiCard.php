<?php

namespace App\View\Components\Admin;

use Illuminate\View\Component;

class KpiCard extends Component
{
    public string $title;
    public string|int $value;
    public ?string $icon;
    public ?string $color;

    public function __construct(
        string $title,
        string|int $value,
        ?string $icon = null,
        ?string $color = 'blue'
    ) {
        $this->title = $title;
        $this->value = $value;
        $this->icon  = $icon;
        $this->color = $color;
    }

    public function render()
    {
        return view('components.admin.kpi-card');
    }
}