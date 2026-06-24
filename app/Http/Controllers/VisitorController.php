<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitorRequest;
use App\Http\Requests\UpdateVisitorRequest;
use App\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public function index(): View
    {
        $search = request('search');

        $visitors = Visitor::query()
            ->when($search, fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
            )
            ->withCount('visits')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('visitors.index', compact('visitors', 'search'));
    }

    public function create(): View
    {
        return view('visitors.create');
    }

    public function store(StoreVisitorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('visitors', 'public');
        }

        $visitor = Visitor::create($data);

        return redirect()
            ->route('visitors.show', $visitor)
            ->with('success', "Visitor {$visitor->name} registered successfully.");
    }

    public function show(Visitor $visitor): View
    {
        $visits = $visitor->visits()
            ->with('host')
            ->latest('check_in_at')
            ->paginate(10);

        return view('visitors.show', compact('visitor', 'visits'));
    }

    public function edit(Visitor $visitor): View
    {
        return view('visitors.edit', compact('visitor'));
    }

    public function update(UpdateVisitorRequest $request, Visitor $visitor): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            if ($visitor->photo) {
                Storage::disk('public')->delete($visitor->photo);
            }
            $data['photo'] = $request->file('photo')->store('visitors', 'public');
        }

        $visitor->update($data);

        return redirect()
            ->route('visitors.show', $visitor)
            ->with('success', 'Visitor updated successfully.');
    }

    public function destroy(Visitor $visitor): RedirectResponse
    {
        if ($visitor->photo) {
            Storage::disk('public')->delete($visitor->photo);
        }

        $visitor->delete();

        return redirect()
            ->route('visitors.index')
            ->with('success', 'Visitor removed.');
    }
}
