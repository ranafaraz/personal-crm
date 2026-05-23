<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $templates = $this->tenantQuery(EmailTemplate::class)
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('email-templates.index', compact('templates'));
    }

    public function create(): View
    {
        return view('email-templates.create');
    }

    public function store(StoreEmailTemplateRequest $request): RedirectResponse
    {
        $template = EmailTemplate::create($this->tenantData($request->validated()));

        return redirect()->route('email-templates.show', $template->id)
            ->with('success', 'Template created successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $template = $this->tenantQuery(EmailTemplate::class)->findOrFail($id);

        $this->authorize('view', $template);

        return view('email-templates.show', compact('template'));
    }

    public function edit(Request $request, int $id): View
    {
        $template = $this->tenantQuery(EmailTemplate::class)->findOrFail($id);

        $this->authorize('update', $template);

        return view('email-templates.edit', compact('template'));
    }

    public function update(UpdateEmailTemplateRequest $request, int $id): RedirectResponse
    {
        $template = $this->tenantQuery(EmailTemplate::class)->findOrFail($id);

        $this->authorize('update', $template);

        $template->update($request->validated());

        return redirect()->route('email-templates.show', $template->id)
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $template = $this->tenantQuery(EmailTemplate::class)->findOrFail($id);

        $this->authorize('delete', $template);

        $template->delete();

        return redirect()->route('email-templates.index')
            ->with('success', 'Template deleted.');
    }

    public function duplicate(Request $request, int $id): RedirectResponse
    {
        $template = $this->tenantQuery(EmailTemplate::class)->findOrFail($id);

        $this->authorize('view', $template);

        $copy = $template->replicate();
        $copy->name = $template->name . ' (Copy)';
        $copy->times_used = 0;
        $copy->save();

        return redirect()->route('email-templates.edit', $copy->id)
            ->with('success', 'Template duplicated. You can now edit the copy.');
    }
}
