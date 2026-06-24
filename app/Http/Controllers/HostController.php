<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHostRequest;
use App\Http\Requests\UpdateHostRequest;
use App\Models\Host;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HostController extends Controller
{
    public function index(): View
    {
        $search     = request('search');
        $department = request('department');

        $hosts = Host::query()
            ->when($search, fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")
            )
            ->when($department, fn ($q) => $q->where('department', $department))
            ->withCount('visits')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $departments = Host::distinct()->orderBy('department')->pluck('department');

        return view('hosts.index', compact('hosts', 'search', 'department', 'departments'));
    }

    public function create(): View
    {
        return view('hosts.create');
    }

    public function store(StoreHostRequest $request): RedirectResponse
    {
        $data              = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $host = Host::create($data);

        return redirect()
            ->route('hosts.index')
            ->with('success', "Host {$host->name} added successfully.");
    }

    public function edit(Host $host): View
    {
        return view('hosts.edit', compact('host'));
    }

    public function update(UpdateHostRequest $request, Host $host): RedirectResponse
    {
        $data              = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $host->update($data);

        return redirect()
            ->route('hosts.index')
            ->with('success', 'Host updated successfully.');
    }

    public function destroy(Host $host): RedirectResponse
    {
        $host->delete();

        return redirect()
            ->route('hosts.index')
            ->with('success', 'Host removed.');
    }
}
