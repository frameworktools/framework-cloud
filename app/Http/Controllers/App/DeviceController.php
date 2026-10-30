<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CliDeviceCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The browser half of fw login: a signed-in user enters the code from the
 * terminal and approves or denies the request.
 */
class DeviceController extends Controller
{
    public function show(Request $request): Response
    {
        $typed = (string) $request->query('code', '');
        $found = null;
        $error = null;

        if ($typed !== '') {
            try {
                $found = $this->pending($typed);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('Device', [
            'code' => $found?->displayCode() ?? strtoupper($typed),
            'request' => $found ? [
                'client_name' => $found->client_name,
                'created_at' => $found->created_at->diffForHumans(),
                'created_at_iso' => $found->created_at->toIso8601String(),
            ] : null,
            'error' => $error,
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $code = $this->pending($this->validated($request));

        $code->forceFill(['status' => CliDeviceCode::APPROVED, 'user_id' => $request->user()->id])->save();

        return to_route('device')->with('status', 'device-approved');
    }

    public function deny(Request $request): RedirectResponse
    {
        $code = $this->pending($this->validated($request));

        $code->forceFill(['status' => CliDeviceCode::DENIED])->save();

        return to_route('device')->with('status', 'device-denied');
    }

    private function validated(Request $request): string
    {
        return $request->validate(['code' => ['required', 'string', 'max:20']])['code'];
    }

    /**
     * The live, pending request for a typed code, or a validation error
     * that says why not.
     */
    private function pending(string $typed): CliDeviceCode
    {
        $code = CliDeviceCode::findByUserCode($typed);

        $message = match (true) {
            ! $code => 'That code doesn\'t match a sign-in request. Check the code in your terminal and try again.',
            $code->isExpired() => 'That code has expired. Run fw login again to get a new one.',
            $code->status !== CliDeviceCode::PENDING => 'That code has already been used. Run fw login again to get a new one.',
            default => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['code' => $message]);
        }

        return $code;
    }
}
