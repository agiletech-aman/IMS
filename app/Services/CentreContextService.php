<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CentreContextService
{
    public function __construct(private readonly Request $request) {}

    public function selected(): ?string
    {
        $user = $this->request->hasSession()
            ? $this->request->session()->get('static_auth_user', [])
            : [];

        if (($user['role'] ?? null) !== 'Administrator' && filled($user['centre'] ?? null)) {
            return $user['centre'];
        }

        $centre = $this->request->hasSession() ? $this->request->session()->get('selected_centre') : null;

        return in_array($centre, ['noida', 'lucknow'], true) ? $centre : null;
    }

    public function isAll(): bool
    {
        return $this->request->hasSession() && $this->request->session()->get('selected_centre') === 'all';
    }

    public function requireSelected(): string
    {
        $centre = $this->selected();
        if ($centre === null || $this->isAll()) {
            throw new HttpException(422, 'Please select a specific Centre before performing this action.');
        }

        return $centre;
    }

    public function set(string $centre): void
    {
        $this->request->session()->put('selected_centre', $centre);
    }

    public function apply(Builder $query): Builder
    {
        if ($centre = $this->selected()) {
            $query->where($query->getModel()->qualifyColumn('centre'), $centre);
        }

        return $query;
    }
}