<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialActivityLog;
use App\Models\SocialOAuthApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OAuthAppController extends Controller
{
    public function index(Request $request): View
    {
        $apps = SocialOAuthApp::where('user_id', $request->user()->id)
            ->with('accounts')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return view('social-studio.oauth-apps.index', compact('apps'));
    }

    public function create(): View
    {
        $redirectUri = config('app.url') . '/social-studio/connections/callback';
        return view('social-studio.oauth-apps.create', compact('redirectUri'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'         => 'required|string|max:255',
            'client_id'     => 'required|string|max:255',
            'client_secret' => 'required|string|max:500',
            'scopes'        => 'nullable|string|max:500',
            'is_default'    => 'boolean',
        ]);

        $user = $request->user();

        $isFirst = ! SocialOAuthApp::where('user_id', $user->id)->exists();

        if ($isFirst || ! empty($data['is_default'])) {
            SocialOAuthApp::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $app = SocialOAuthApp::create([
            'tenant_id'               => $user->tenant_id,
            'user_id'                 => $user->id,
            'provider_key'            => 'linkedin',
            'label'                   => $data['label'],
            'client_id'               => $data['client_id'],
            'client_secret_encrypted' => $data['client_secret'],
            'redirect_uri'            => config('app.url') . '/social-studio/connections/callback',
            'scopes'                  => $data['scopes'] ?? 'w_member_social openid profile email',
            'is_default'              => $isFirst || ! empty($data['is_default']),
            'is_active'               => true,
        ]);

        SocialActivityLog::record(
            $user->id, $user->tenant_id,
            'oauth_app_created', SocialOAuthApp::class, $app->id,
            "LinkedIn OAuth app created: {$app->label}"
        );

        return redirect()->route('social-studio.connections')
            ->with('success', "LinkedIn app \"{$app->label}\" added. Click Connect to link your account.");
    }

    public function edit(Request $request, int $id): View
    {
        $app         = SocialOAuthApp::where('user_id', $request->user()->id)->findOrFail($id);
        $redirectUri = config('app.url') . '/social-studio/connections/callback';
        return view('social-studio.oauth-apps.edit', compact('app', 'redirectUri'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $app  = SocialOAuthApp::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'label'         => 'required|string|max:255',
            'client_id'     => 'required|string|max:255',
            'client_secret' => 'nullable|string|max:500',
            'scopes'        => 'nullable|string|max:500',
        ]);

        $update = [
            'label'     => $data['label'],
            'client_id' => $data['client_id'],
            'scopes'    => $data['scopes'] ?? $app->scopes,
        ];

        if (! empty($data['client_secret'])) {
            $update['client_secret_encrypted'] = $data['client_secret'];
        }

        $app->update($update);

        return redirect()->route('social-studio.connections')
            ->with('success', "LinkedIn app \"{$app->label}\" updated.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $app = SocialOAuthApp::where('user_id', $request->user()->id)->findOrFail($id);

        // Disconnect any linked accounts before deleting
        $app->accounts()->update([
            'status'                  => 'disconnected',
            'access_token_encrypted'  => null,
            'refresh_token_encrypted' => null,
        ]);

        $label = $app->label;
        $app->delete();

        // Promote another app to default if this was it
        if ($app->is_default) {
            SocialOAuthApp::where('user_id', $request->user()->id)
                ->first()
                ?->update(['is_default' => true]);
        }

        return redirect()->route('social-studio.connections')
            ->with('success', "LinkedIn app \"{$label}\" removed.");
    }

    public function setDefault(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        SocialOAuthApp::where('user_id', $user->id)->update(['is_default' => false]);
        SocialOAuthApp::where('user_id', $user->id)->where('id', $id)->update(['is_default' => true]);

        return back()->with('success', 'Default LinkedIn app updated.');
    }
}
