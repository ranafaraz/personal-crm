<?php

namespace App\Http\Controllers;

use App\Models\EmailSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmailSignatureController extends Controller
{
    public function index(): View
    {
        $signatures = $this->tenantQuery(EmailSignature::class)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(25);

        return view('email-signatures.index', compact('signatures'));
    }

    public function create(): View
    {
        return view('email-signatures.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['image_path'] = $this->storeImage($request);
        $data['is_default'] = $request->boolean('is_default')
            || ! $this->tenantQuery(EmailSignature::class)->exists();

        $signature = EmailSignature::create($this->tenantData($data));

        if ($signature->is_default) {
            $this->makeDefault($signature);
        }

        return redirect()->route('email-signatures.index')->with('success', 'Signature saved.');
    }

    public function edit(Request $request, int $id): View
    {
        $signature = $this->tenantQuery(EmailSignature::class)->findOrFail($id);
        $this->authorize('update', $signature);

        return view('email-signatures.edit', compact('signature'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $signature = $this->tenantQuery(EmailSignature::class)->findOrFail($id);
        $this->authorize('update', $signature);

        $data = $this->validatedData($request);

        if ($request->boolean('remove_image')) {
            $this->deleteImage($signature);
            $data['image_path'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteImage($signature);
            $data['image_path'] = $this->storeImage($request);
        }

        $data['is_default'] = $request->boolean('is_default') || $signature->is_default;
        $signature->update($data);

        if ($signature->is_default) {
            $this->makeDefault($signature);
        }

        return redirect()->route('email-signatures.index')->with('success', 'Signature updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $signature = $this->tenantQuery(EmailSignature::class)->findOrFail($id);
        $this->authorize('delete', $signature);

        $wasDefault = $signature->is_default;
        $this->deleteImage($signature);
        $signature->delete();

        if ($wasDefault) {
            $next = $this->tenantQuery(EmailSignature::class)->orderBy('name')->first();
            if ($next) {
                $this->makeDefault($next);
            }
        }

        return redirect()->route('email-signatures.index')->with('success', 'Signature deleted.');
    }

    public function setDefault(Request $request, int $id): RedirectResponse
    {
        $signature = $this->tenantQuery(EmailSignature::class)->findOrFail($id);
        $this->authorize('update', $signature);

        $this->makeDefault($signature);

        return redirect()->route('email-signatures.index')->with('success', 'Default signature updated.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'body' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_default' => 'nullable|boolean',
            'remove_image' => 'nullable|boolean',
        ]);

        unset($data['image'], $data['remove_image']);

        return $data;
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('email-signatures', 'public') ?: null;
    }

    private function deleteImage(EmailSignature $signature): void
    {
        if ($signature->image_path && Storage::disk('public')->exists($signature->image_path)) {
            Storage::disk('public')->delete($signature->image_path);
        }
    }

    private function makeDefault(EmailSignature $signature): void
    {
        $this->tenantQuery(EmailSignature::class)
            ->whereKeyNot($signature->id)
            ->update(['is_default' => false]);

        $signature->forceFill(['is_default' => true])->save();
    }
}
