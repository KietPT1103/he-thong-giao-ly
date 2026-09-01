<?php

namespace App\Http\Controllers\Api;

use App\Services\AuditLogger;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MfaController extends ApiController
{
    public function show(Request $request)
    {
        return $this->success([
            'enabled' => $request->user()->mfa_confirmed_at !== null,
            'confirmed_at' => $request->user()->mfa_confirmed_at,
        ]);
    }

    public function setup(Request $request, TotpService $totp, AuditLogger $audit)
    {
        $secret = $totp->generateSecret();
        $request->session()->put('auth.mfa_setup_secret', $secret);
        $audit->record($request, 'mfa.setup_started', $request->user());

        return $this->success([
            'secret' => $secret,
            'otpauth_uri' => $totp->uri($secret, $request->user()->email),
        ]);
    }

    public function confirm(Request $request, TotpService $totp, AuditLogger $audit)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = (string) $request->session()->get('auth.mfa_setup_secret');
        if ($secret === '' || ! $totp->verify($secret, $data['code'])) {
            $audit->record($request, 'mfa.confirm_failed', $request->user());

            return response()->json(['success' => false, 'message' => 'Mã xác thực không hợp lệ.', 'code' => 'INVALID_MFA_CODE'], 422);
        }

        $codes = $totp->recoveryCodes();
        $request->user()->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $codes),
            'mfa_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('auth.mfa_setup_secret');
        $audit->record($request, 'mfa.enabled', $request->user());

        return $this->success(['recovery_codes' => $codes], 'Đã bật xác thực hai lớp.');
    }

    public function destroy(Request $request, AuditLogger $audit)
    {
        $request->user()->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
        ])->save();
        $audit->record($request, 'mfa.disabled', $request->user());

        return $this->success(null, 'Đã tắt xác thực hai lớp.');
    }

    public function recoveryCodes(Request $request, TotpService $totp, AuditLogger $audit)
    {
        abort_unless($request->user()->mfa_confirmed_at !== null, 422, 'MFA chưa được bật.');
        $codes = $totp->recoveryCodes();
        $request->user()->forceFill([
            'mfa_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $codes),
        ])->save();
        $audit->record($request, 'mfa.recovery_codes_regenerated', $request->user());

        return $this->success(['recovery_codes' => $codes], 'Đã tạo lại mã khôi phục.');
    }
}
