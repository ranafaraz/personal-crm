<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialProvider;
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

        $accounts = SocialAccount::where('user_id', $user->id)
            ->with('provider')
            ->get()
            ->keyBy(fn ($a) => $a->provider->key);

        return view('social-studio.connections', compact('providers', 'accounts'));
    }

    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('linkedin_oauth_state', $state);

        return redirect($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        // CSRF / state validation
        $storedState = $request->session()->pull('linkedin_oauth_state');
        if (! $storedState || ! hash_equals($storedState, (string) $request->input('state'))) {
            return redirect()->route('social-studio.connections')
                ->with('error', 'LinkedIn authorization failed: invalid state. Please try again.');
        }

        if ($request->has('error')) {
            return redirect()->route('social-studio.connections')
                ->with('error', 'LinkedIn authorization declined: ' . $request->input('error_description', 'unknown error'));
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()->route('social-studio.connections')
                ->with('error', 'LinkedIn authorization failed: missing code parameter.');
        }

        try {
            $tokenData = $this->oauth->exchangeCode($code);
            $identity  = $this->oauth->resolveMemberIdentity($tokenData['access_token']);
            $this->oauth->storeConnection($request->user(), $tokenData, $identity);

            return redirect()->route('social-studio.connections')
                ->with('success', "LinkedIn connected as {$identity['display_name']}.");

        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('social-studio.connections')
                ->with('error', 'LinkedIn connection failed. Please try again.');
        }
    }

    public function disconnect(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $this->oauth->disconnect($account);

        return redirect()->route('social-studio.connections')
            ->with('success', 'LinkedIn account disconnected.');
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)
            ->with('provider')
            ->findOrFail($id);

        if (! $account->provider->isEnabled()) {
            return back()->with('error', 'This provider is not yet enabled.');
        }

        try {
            $identity = $this->oauth->resolveMemberIdentity($account->access_token_encrypted);
            $account->update([
                'status'           => 'connected',
                'last_verified_at' => now(),
                'display_name'     => $identity['display_name'],
                'provider_account_urn' => $identity['urn'],
            ]);
            return back()->with('success', 'Connection verified successfully.');
        } catch (\Throwable) {
            $account->update(['status' => 'reauthorization_required']);
            return back()->with('error', 'Token verification failed. Please reconnect LinkedIn.');
        }
    }
}
