<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PrivacyPolicy;
use Illuminate\Http\Request;

/** The staff data privacy policy: read and agree (all staff), write and publish (administrator). */
class PrivacyPolicyController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('privacy-policy.show', [
            'html' => PrivacyPolicy::html(),
            'published' => PrivacyPolicy::published(),
            'version' => PrivacyPolicy::version(),
            'needsAgreement' => PrivacyPolicy::needsAgreement($user),
            'agreedAt' => $user->privacy_policy_version === PrivacyPolicy::version() ? $user->privacy_policy_agreed_at : null,
            'agreed' => $user->role === 'admin' && PrivacyPolicy::published()
                ? User::whereIn('role', PrivacyPolicy::STAFF)->where('is_active', true)->where('privacy_policy_version', PrivacyPolicy::version())->count()
                : null,
            'staff' => $user->role === 'admin'
                ? User::whereIn('role', PrivacyPolicy::STAFF)->where('is_active', true)->count()
                : null,
        ]);
    }

    public function agree(Request $request)
    {
        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'Tick the box to confirm you have read the policy.']);
        abort_unless(PrivacyPolicy::published(), 404);

        $user = $request->user();
        $user->forceFill(['privacy_policy_version' => PrivacyPolicy::version(), 'privacy_policy_agreed_at' => now()])->save();
        AuditLogger::log('privacy.policy_agreed', $user, "{$user->name} agreed to the data privacy policy (version " . PrivacyPolicy::version() . ')');

        return redirect()->intended(url('/dashboard'))->with('success', 'Thank you. You agreed to the data privacy policy.');
    }

    public function edit()
    {
        return view('privacy-policy.edit', [
            'text' => PrivacyPolicy::text(),
            'published' => PrivacyPolicy::published(),
            'version' => PrivacyPolicy::version(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:50', 'max:20000'],
            'publish' => ['nullable', 'boolean'],
        ], ['text.min' => 'The policy is too short.']);

        $text = trim(str_replace("\r\n", "\n", $data['text']));
        $changed = $text !== trim((string) Setting::get(PrivacyPolicy::TEXT, ''));
        Setting::put(PrivacyPolicy::TEXT, $text);

        // Publishing makes this text the current version: every staff account must agree to it again.
        if ($request->boolean('publish')) {
            $version = now()->format('Y-m-d H:i:s');
            Setting::put(PrivacyPolicy::VERSION, $version);
            \App\Support\Navigation::refreshNotifications();   // the "not published yet" reminder goes away at once
            AuditLogger::log('privacy.policy_published', null, "Published the data privacy policy (version {$version}); staff must agree again");

            return redirect()->route('privacy-policy.show')->with('success', 'Policy published. Every staff member will be asked to agree on their next page.');
        }

        if ($changed) {
            AuditLogger::log('privacy.policy_edited', null, 'Edited the data privacy policy text (not published)');
        }

        return redirect()->route('privacy-policy.edit')->with('success', PrivacyPolicy::published()
            ? 'Changes saved. Staff are not asked again until you publish.'
            : 'Draft saved. Publish it when it is ready.');
    }
}
