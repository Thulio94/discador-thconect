<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactListController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('tenant.lists.index', [
            'lists' => ContactList::query()
                ->where('company_id', $companyId)
                ->with('contacts')
                ->withCount(['contacts', 'campaigns'])
                ->latest()
                ->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId));

        $list = ContactList::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        $this->syncContacts($list, $data['contacts'] ?? '');

        return redirect()->route('tenant.lists.index')->with('status', 'Lista criada com sucesso.');
    }

    public function update(Request $request, ContactList $list): RedirectResponse
    {
        abort_unless($list->company_id === $request->user()->company_id, 403);

        $data = $request->validate($this->rules($list->company_id, $list));

        $list->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        if (array_key_exists('contacts', $data)) {
            $this->syncContacts($list, $data['contacts'] ?? '');
        }

        return redirect()->route('tenant.lists.index')->with('status', 'Lista atualizada com sucesso.');
    }

    public function destroy(Request $request, ContactList $list): RedirectResponse
    {
        abort_unless($list->company_id === $request->user()->company_id, 403);

        $list->update(['status' => 'inactive']);

        return redirect()->route('tenant.lists.index')->with('status', 'Lista inativada com sucesso.');
    }

    private function rules(int $companyId, ?ContactList $list = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('contact_lists')->where('company_id', $companyId)->ignore($list)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'contacts' => ['nullable', 'string'],
        ];
    }

    private function syncContacts(ContactList $list, string $contacts): void
    {
        collect(preg_split('/\r\n|\r|\n/', $contacts) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->each(function (string $line) use ($list): void {
                [$name, $phone] = str_contains($line, ';')
                    ? array_pad(array_map('trim', explode(';', $line, 2)), 2, null)
                    : [null, $line];

                $phone = preg_replace('/\D+/', '', (string) $phone);

                if ($phone === '') {
                    return;
                }

                $list->contacts()->updateOrCreate(
                    ['phone' => $phone],
                    [
                        'company_id' => $list->company_id,
                        'name' => $name ?: null,
                        'status' => 'new',
                    ]
                );
            });
    }
}
