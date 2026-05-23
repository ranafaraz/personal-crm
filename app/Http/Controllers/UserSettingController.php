<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $setting = $request->user()->setting
            ?? UserSetting::firstOrCreate(['user_id' => $request->user()->id]);

        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('settings.edit', compact('setting', 'emailAccounts'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone'                 => 'nullable|string|max:100',
            'date_format'              => 'nullable|string|max:30',
            'default_follow_up_days'   => 'nullable|integer|min:1|max:365',
            'default_email_account_id' => 'nullable|integer|exists:email_accounts,id',
            'notify_on_reply'          => 'nullable|boolean',
            'notify_on_bounce'         => 'nullable|boolean',
        ]);

        // Ensure the selected account belongs to this user
        if (!empty($data['default_email_account_id'])) {
            $this->tenantQuery(EmailAccount::class)
                ->findOrFail($data['default_email_account_id']);
        }

        $setting = $request->user()->setting
            ?? UserSetting::firstOrCreate(['user_id' => $request->user()->id]);

        $setting->update($data);

        return redirect()->route('settings.edit')->with('success', 'Settings saved.');
    }
}
