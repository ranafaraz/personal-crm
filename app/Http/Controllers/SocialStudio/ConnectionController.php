<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialOAuthApp;
use App\Models\SocialProvider;
use App\Services\Social\LinkedInOAuthException;
use App\Services\Social\LinkedInOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function __construct(private LinkedInOAuthService $oauth) {}

    public function index(Request $request): View
    {
        $user      = $request->user();
        $providers = SocialProvider::all();

        // All configured OAuth apps for this user
        $oauthApps = SocialOAuthApp::where('user_id', $user->id)
            ->with('accounts.provider')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        // All connected accounts grouped by provider key
        $accounts = SocialAccount::where('user_id', $user->id)
            ->with(['provider', 'oauthApp'])
            ->get();

        return view('social-studio.connections', compact('providers', 'oauthApps', 'accounts'));
    }

    /** Start OAuth for a specific app config. */
    public function connect(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Resolve which OAuth app to use
        $appId = $request->input('app_id');
        $app   = $appId
            ? SocialOAuthApp::where('user_id', $user->id)->findOrFail($appId)
            : SocialOAuthApp::where('user_id', $user->id)->where('is_default', true)->first()
                ?? SocialOAuthApp::where('user_id', $user->id)->first();

        if (! $app) {
            return redirect()->route('social-studio.connections')
                ->with('error', 'No LinkedIn app configured. Add your LinkedIn Developer App credentials first.');
        }

        $state = Str::random(40);
        $request->session()->put('linkedin_oauth_state', $state);
        $request->session()->put('linkedin_oauth_app_id', $app->id);

        return redirect($this->oauth->authorizationUrl($app, $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $storedState = $request->session()->pull('linkedin_oauth_state');
        $appId       = $request->session()->pull('linkedin_oauth_app_id');

        try {
            $this->validateCallbackRequest($request, $storedState, $appId);

            $app       = SocialOAuthApp::where('user_id', $request->user()->id)->findOrFail($appId);
            $tokenData = $this->oauth->exchangeCode($app, $request->input('code'));
            $identity  = $this->oauth->resolveMemberIdentity($tokenData['access_token']);
            $this->oauth->storeConnection($request->user(), $app, $tokenData, $identity);

            return redirect()->route('social-studio.connections')
                ->with('success', "LinkedIn connected as {$identity['display_name']} via \"{$app->label}\".");

        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('social-studio.connections')
                ->with('error', $e->getMessage());
        }
    }

    /** @throws LinkedInOAuthException */
    private function validateCallbackRequest(Request $request, ?string $storedState, mixed $appId): void
    {
        if (! $storedState || ! hash_equals($storedState, (string) $request->input('state'))) {
            throw new LinkedInOAuthException('LinkedIn authorization failed: invalid state. Please try again.');
        }
        if ($request->has('error')) {
            throw new LinkedInOAuthException('LinkedIn authorization declined: ' . $request->input('error_description', 'unknown error'));
        }
        if (! $request->input('code')) {
            throw new LinkedInOAuthException('LinkedIn authorization failed: missing code parameter.');
        }
        if (! $appId) {
            throw new LinkedInOAuthException('OAuth app session expired. Please try connecting again.');
        }
    }

    public function disconnect(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $this->oauth->disconnect($account);

        return redirect()->route('social-studio.connections')
            ->with('success', 'LinkedIn account disconnected.');
    }

    public function setDefault(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $account->makeDefault();

        return back()->with('success', "Default account set to {$account->display_name}.");
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)
            ->with(['provider', 'oauthApp'])
            ->findOrFail($id);

        if (! $account->provider->isEnabled()) {
            return back()->with('error', 'This provider is not yet enabled.');
        }

        try {
            $identity = $this->oauth->resolveMemberIdentity($account->access_token_encrypted);
            $account->update([
                'status'               => 'connected',
                'last_verified_at'     => now(),
                'display_name'         => $identity['display_name'],
                'provider_account_urn' => $identity['urn'],
            ]);
            return back()->with('success', 'Connection verified successfully.');
        } catch (\Throwable) {
            $account->update(['status' => 'reauthorization_required']);
            return back()->with('error', 'Token verification failed. Please reconnect LinkedIn.');
        }
    }
}
