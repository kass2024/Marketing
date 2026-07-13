<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaAutoSyncService;
use App\Services\Meta\WhatsAppBusinessAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class WhatsAppAccountsController extends Controller
{
    public function __construct(
        protected WhatsAppBusinessAccountService $whatsapp,
        protected MetaAutoSyncService $autoSync
    ) {}

    public function index(Request $request): View
    {
        // Always sync WhatsApp assets when opening this page
        $this->autoSync->syncAlways();

        $connection = $this->whatsapp->connection();
        $error = null;
        $accounts = [];

        try {
            $this->whatsapp->resolveBusinessManagerId($connection);
            $accounts = $this->whatsapp->listWabas();
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $selectedId = (string) ($request->query('waba') ?: ($connection?->whatsapp_business_id ?? ($accounts[0]['id'] ?? '')));
        $selected = collect($accounts)->firstWhere('id', $selectedId);
        $detail = null;
        $phones = [];

        if ($selectedId !== '') {
            try {
                $detail = $this->whatsapp->getWaba($selectedId) ?? $selected;
                $phones = $this->whatsapp->listPhoneNumbers($selectedId);
            } catch (ValidationException $e) {
                $error = $error ?: collect($e->errors())->flatten()->first();
                $detail = $detail ?: $selected;
            } catch (\Throwable $e) {
                $error = $error ?: $e->getMessage();
                $detail = $detail ?: $selected;
            }
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $accounts = array_values(array_filter($accounts, function ($a) use ($search) {
                return str_contains(strtolower($a['name'].' '.$a['id']), strtolower($search));
            }));
        }

        return view('admin.meta.whatsapp.index', [
            'connection' => $connection,
            'accounts' => $accounts,
            'selectedId' => $selectedId,
            'detail' => $detail,
            'phones' => $phones,
            'search' => $search,
            'error' => $error,
            'pendingPhoneId' => old('phone_number_id', session('pending_phone_number_id')),
            'lastSyncedAt' => now()->toDateTimeString(),
        ]);
    }

    public function linkWaba(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string|max:64',
        ]);

        try {
            $this->autoSync->syncAlways();
            $result = $this->whatsapp->importExistingWaba($data['waba_id']);
            $this->autoSync->syncAlways();

            return redirect()
                ->route('admin.meta.whatsapp.index', [
                    'waba' => $result['waba']['id'] ?? $data['waba_id'],
                    'tab' => 'phones',
                ])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('show_link_waba', true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput()->with('show_link_waba', true);
        }
    }

    public function createWaba(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_name' => 'required|string|max:128',
            'currency' => 'nullable|string|size:3',
        ]);

        try {
            $result = $this->whatsapp->createOwnedWaba(
                $data['waba_name'],
                strtoupper($data['currency'] ?? 'CAD')
            );
            $this->autoSync->syncAlways();

            return redirect()
                ->route('admin.meta.whatsapp.index', [
                    'waba' => $result['waba']['id'] ?? null,
                    'tab' => 'phones',
                ])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('show_create_waba', true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput()->with('show_create_waba', true);
        }
    }

    public function requestClientWaba(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_business_id' => 'required|string|max:64',
            'waba_name' => 'required|string|max:128',
        ]);

        try {
            $result = $this->whatsapp->requestClientWaba(
                $data['client_business_id'],
                $data['waba_name']
            );
            $this->autoSync->syncAlways();

            return redirect()
                ->route('admin.meta.whatsapp.index', ['tab' => 'phones'])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('show_request_waba', true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput()->with('show_request_waba', true);
        }
    }

    public function addPhone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number' => 'required|string|max:32',
            'verified_name' => 'required|string|max:128',
        ]);

        try {
            $result = $this->whatsapp->addPhoneNumber(
                $data['waba_id'],
                $data['phone_number'],
                $data['verified_name'],
                true
            );

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', $result['message'])
                ->with('pending_phone_number_id', $result['phone_number_id']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function resendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
            'code_method' => 'nullable|in:SMS,VOICE',
        ]);

        try {
            $result = $this->whatsapp->requestVerificationCode(
                $data['phone_number_id'],
                $data['code_method'] ?? 'SMS'
            );

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', $result['message'])
                ->with('pending_phone_number_id', $data['phone_number_id']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function verifyPhone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
            'code' => 'required|string|min:4|max:12',
        ]);

        try {
            $result = $this->whatsapp->verifyAndRegister(
                $data['phone_number_id'],
                $data['code'],
                $data['waba_id']
            );

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('pending_phone_number_id', $data['phone_number_id']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function setDefault(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
        ]);

        try {
            $this->whatsapp->setAsPlatformDefault($data['phone_number_id'], $data['waba_id']);

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', 'Platform default WhatsApp number updated. Ads will deliver to this number.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
