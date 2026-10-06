<?php

namespace Tests\Feature;

use App\Models\BlacklistNumber;
use App\Models\Campaign;
use App\Models\CampaignPhoneState;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Role;
use App\Models\Team;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Services\Dialer\MailingQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TenantCampaignListCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_contact_list_with_contacts(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Junho',
                'description' => 'Leads de junho',
                'status' => 'active',
                'contacts' => "Maria;31999990001\nJoao;31999990002",
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status');

        $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista Junho')->sole();

        $this->assertSame('active', $list->status);
        $this->assertSame(2, $list->contacts()->count());
        $this->assertTrue($list->contacts()->where('phone', '31999990001')->exists());
        $this->assertSame('pending', $list->contacts()->where('phone', '31999990001')->sole()->status);
        $this->assertSame(0, $list->contacts()->where('phone', '31999990001')->sole()->attempts_count);
    }

    public function test_contact_list_import_sanitizes_deduplicates_and_applies_tenant_blacklist(): void
    {
        [$admin] = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);

        BlacklistNumber::create([
            'company_id' => $admin->company_id,
            'phone' => '31999990002',
            'reason' => 'DNC',
            'status' => 'active',
        ]);
        BlacklistNumber::create([
            'company_id' => $otherCompany->id,
            'phone' => '31999990003',
            'reason' => 'Outro tenant',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Higienizada',
                'description' => 'Com blacklist',
                'status' => 'active',
                'contacts' => implode("\n", [
                    'Maria;(31) 99999-0001',
                    'Maria duplicada;31999990001',
                    'Bloqueado;31999990002',
                    'Outro tenant;31999990003',
                    'Invalido;12345',
                ]),
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '2 contatos importados')
                && str_contains($message, '1 bloqueado')
                && str_contains($message, '1 invalido')
                && str_contains($message, '1 duplicado'));

        $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista Higienizada')->sole();

        $this->assertSame(2, $list->contacts()->count());
        $this->assertTrue($list->contacts()->where('phone', '31999990001')->exists());
        $this->assertTrue($list->contacts()->where('phone', '31999990003')->exists());
        $this->assertFalse($list->contacts()->where('phone', '31999990002')->exists());
        $this->assertFalse($list->contacts()->where('phone', '12345')->exists());
    }

    public function test_contact_list_import_normalizes_country_code_and_legacy_mobile_numbers(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Telefones Brasileiros',
                'status' => 'active',
                'contacts' => implode("\n", [
                    'Celular antigo;(31) 8888-0001',
                    'Celular E164;+55 (31) 98888-0002',
                    'Fixo;(31) 3333-4444',
                    'DDD invalido;(20) 99999-0001',
                    'Celular invalido;(31) 91111-0001',
                ]),
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '3 contatos importados')
                && str_contains($message, '2 invalido'));

        $phones = ContactList::query()
            ->where('company_id', $admin->company_id)
            ->where('name', 'Lista Telefones Brasileiros')
            ->sole()
            ->contacts()
            ->orderBy('phone')
            ->pluck('phone')
            ->all();

        $this->assertSame(['3133334444', '31988880001', '31988880002'], $phones);
    }

    public function test_tenant_admin_can_import_csv_with_multiple_phones_and_selected_extra_columns(): void
    {
        [$admin] = $this->tenantAdmin();

        $file = UploadedFile::fake()->createWithContent(
            'leads.csv',
            "Nome;Telefone 1;Telefone 2;CPF;Ignorar\nMaria;+55 (31) 99999-0001;31 8888-0002;123;fora\nJoao;12345;31977770003;456;fora"
        );

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista CSV',
                'description' => 'Importada',
                'status' => 'active',
                'import_file' => $file,
                'separator' => ';',
                'delimiter' => '"',
                'import_columns' => [
                    ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                    ['index' => 1, 'enabled' => '1', 'name' => 'Telefone 1', 'type' => 'phone'],
                    ['index' => 2, 'enabled' => '1', 'name' => 'Telefone 2', 'type' => 'phone'],
                    ['index' => 3, 'enabled' => '1', 'name' => 'CPF', 'type' => 'extra'],
                    ['index' => 4, 'name' => 'Ignorar', 'type' => 'extra'],
                ],
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '2 leads importados')
                && str_contains($message, '3 telefones importados')
                && str_contains($message, '1 invalido'));

        $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista CSV')->sole();
        $maria = $list->contacts()->where('name', 'Maria')->sole();
        $joao = $list->contacts()->where('name', 'Joao')->sole();

        $this->assertSame('31999990001', $maria->phone);
        $this->assertSame(['Nome' => 'Maria', 'CPF' => '123'], $maria->extra);
        $this->assertSame(['31999990001', '31988880002'], $maria->phones()->pluck('phone')->all());
        $this->assertSame('31977770003', $joao->phone);
        $this->assertSame(['31977770003'], $joao->phones()->pluck('phone')->all());
        $this->assertArrayNotHasKey('Ignorar', $maria->extra);
    }

    public function test_csv_import_only_deduplicates_phones_inside_the_same_lead(): void
    {
        [$admin] = $this->tenantAdmin();
        $file = UploadedFile::fake()->createWithContent(
            'leads.csv',
            "Nome;Tel1;Tel2\nMaria;31999990001;31999990001\nJoao;31999990001;31988880002"
        );

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Duplicidade Por Lead',
                'status' => 'active',
                'import_file' => $file,
                'separator' => ';',
                'delimiter' => '"',
                'import_columns' => [
                    ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                    ['index' => 1, 'enabled' => '1', 'name' => 'Tel1', 'type' => 'phone'],
                    ['index' => 2, 'enabled' => '1', 'name' => 'Tel2', 'type' => 'phone'],
                ],
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '2 leads importados')
                && str_contains($message, '3 telefones importados')
                && str_contains($message, '1 duplicado'));

        $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista Duplicidade Por Lead')->sole();

        $this->assertSame(2, $list->contacts()->count());
        $this->assertSame(['31999990001'], $list->contacts()->where('name', 'Maria')->sole()->phones()->pluck('phone')->all());
        $this->assertSame(['31999990001', '31988880002'], $list->contacts()->where('name', 'Joao')->sole()->phones()->pluck('phone')->all());
    }

    public function test_csv_import_requires_at_least_one_phone_column(): void
    {
        [$admin] = $this->tenantAdmin();
        $file = UploadedFile::fake()->createWithContent('leads.txt', "Nome;Telefone\nMaria;31999990001");

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Sem Telefone',
                'status' => 'active',
                'import_file' => $file,
                'separator' => ';',
                'delimiter' => '"',
                'import_columns' => [
                    ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                    ['index' => 1, 'enabled' => '1', 'name' => 'Telefone', 'type' => 'extra'],
                ],
            ])
            ->assertSessionHasErrors('import_columns');

        $this->assertDatabaseMissing('contact_lists', [
            'company_id' => $admin->company_id,
            'name' => 'Lista Sem Telefone',
        ]);
    }

    public function test_csv_import_validation_reopens_the_list_modal_with_visible_feedback(): void
    {
        [$admin] = $this->tenantAdmin();

        $response = $this->followingRedirects()->from(route('tenant.lists.index'))->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Sem Feedback Oculto',
                'status' => 'active',
                'import_file' => UploadedFile::fake()->createWithContent('leads.txt', "Nome;Telefone\nMaria;31999990001"),
                'import_columns' => [
                    ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                    ['index' => 1, 'enabled' => '1', 'name' => 'Telefone', 'type' => 'extra'],
                ],
            ]);

        $response->assertOk()
            ->assertSee('A lista nao foi importada.')
            ->assertSee('Selecione pelo menos uma coluna de telefone.');
    }

    public function test_csv_import_rejects_empty_file_and_file_without_header(): void
    {
        [$admin] = $this->tenantAdmin();

        foreach (['vazio.csv' => '', 'sem-cabecalho.csv' => "\n31999990001"] as $name => $content) {
            $this->actingAs($admin)
                ->post(route('tenant.lists.store'), [
                    'name' => 'Lista '.$name,
                    'status' => 'active',
                    'import_file' => UploadedFile::fake()->createWithContent($name, $content),
                    'separator' => ';',
                    'delimiter' => '"',
                    'import_columns' => [
                        ['index' => 0, 'enabled' => '1', 'name' => 'Telefone', 'type' => 'phone'],
                    ],
                ])
                ->assertSessionHasErrors('import_file');

            $this->assertDatabaseMissing('contact_lists', [
                'company_id' => $admin->company_id,
                'name' => 'Lista '.$name,
            ]);
        }
    }

    public function test_csv_import_explains_the_mailing_file_size_limit(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Acima do Limite',
                'status' => 'active',
                'import_file' => UploadedFile::fake()->create('grande.csv', 20481, 'text/csv'),
            ])
            ->assertSessionHasErrors([
                'import_file' => 'O arquivo de mailing pode ter no maximo 20 MB.',
            ]);
    }

    public function test_csv_import_applies_blacklist_to_all_phone_columns(): void
    {
        [$admin] = $this->tenantAdmin();
        BlacklistNumber::create([
            'company_id' => $admin->company_id,
            'phone' => '31988880002',
            'reason' => 'Bloqueado',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Blacklist Multiphone',
                'status' => 'active',
                'import_file' => UploadedFile::fake()->createWithContent(
                    'leads.csv',
                    "Nome;Tel1;Tel2\nMaria;31999990001;31988880002",
                ),
                'separator' => ';',
                'delimiter' => '"',
                'import_columns' => [
                    ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                    ['index' => 1, 'enabled' => '1', 'name' => 'Tel1', 'type' => 'phone'],
                    ['index' => 2, 'enabled' => '1', 'name' => 'Tel2', 'type' => 'phone'],
                ],
            ])
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 bloqueado'));

        $list = ContactList::query()
            ->where('company_id', $admin->company_id)
            ->where('name', 'Lista Blacklist Multiphone')
            ->sole();

        $this->assertSame(['31999990001'], $list->contacts()->sole()->phones()->pluck('phone')->all());
    }

    public function test_contact_can_store_multiple_ordered_phones_for_a_lead(): void
    {
        [$admin] = $this->tenantAdmin();

        $list = ContactList::create([
            'company_id' => $admin->company_id,
            'name' => 'Lista Telefones',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Maria',
            'phone' => '31999990001',
            'status' => 'pending',
        ]);

        $contact->phones()->createMany([
            [
                'company_id' => $admin->company_id,
                'phone' => '31999990001',
                'column_name' => 'Telefone principal',
                'position' => 0,
                'is_primary' => true,
                'status' => 'pending',
            ],
            [
                'company_id' => $admin->company_id,
                'phone' => '31988880002',
                'column_name' => 'Telefone extra',
                'position' => 1,
                'is_primary' => false,
                'status' => 'pending',
            ],
        ]);

        $this->assertSame([
            '31999990001',
            '31988880002',
        ], $contact->fresh()->phones()->pluck('phone')->all());
        $this->assertTrue($contact->fresh()->phones()->where('phone', '31999990001')->sole()->is_primary);
        $this->assertFalse($contact->fresh()->phones()->where('phone', '31988880002')->sole()->is_primary);
    }

    public function test_tenant_admin_can_view_contact_lists_index(): void
    {
        [$admin] = $this->tenantAdmin();

        ContactList::create([
            'company_id' => $admin->company_id,
            'name' => 'Lista Existente',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.lists.index'))
            ->assertOk()
            ->assertSee('Lista Existente')
            ->assertSee('Nova lista')
            ->assertSee('Blacklist aplicada automaticamente')
            ->assertSee('upload')
            ->assertSee('Separador')
            ->assertSee('Delimitador')
            ->assertSee('Telefone')
            ->assertSee('x-bind:aria-label="`Usar coluna ${position + 1}: ${column.name || \'sem nome\'}`"', false)
            ->assertSee('x-bind:aria-label="`Tipo da coluna ${position + 1}: ${column.name || \'sem nome\'}`"', false)
            ->assertSee('x-bind:aria-label="`Nome da coluna ${position + 1}: ${column.name || \'sem nome\'}`"', false);
    }

    public function test_campaign_form_shows_wizard_schedule_review_and_list_weights(): void
    {
        [$admin] = $this->tenantAdmin();
        ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Wizard', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index'))
            ->assertOk()
            ->assertSee('Etapa 1')
            ->assertSee('Etapa 2')
            ->assertSee('Etapa 3')
            ->assertSee('Etapa 4')
            ->assertSee('Agenda operacional')
            ->assertSee('Peso')
            ->assertSee('Revisao da campanha');
    }

    public function test_campaign_index_uses_icon_only_start_and_pause_actions(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista de acoes', 'status' => 'active']);
        $draft = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha para iniciar',
            'dialing_mode' => 'power',
            'status' => 'draft',
        ]);
        $active = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha ativa',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        foreach ([$draft, $active] as $campaign) {
            $campaign->teams()->attach($team, ['company_id' => $admin->company_id]);
            $campaign->contactLists()->attach($list, ['company_id' => $admin->company_id]);
        }

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index'))
            ->assertOk()
            ->assertSee('title="Iniciar campanha"', false)
            ->assertSee('title="Pausar campanha"', false)
            ->assertDontSee('Rodar discagem')
            ->assertDontSee('>Pausar<', false);
    }

    public function test_active_campaign_explains_when_a_route_validation_blocks_dialing(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista de discagem', 'status' => 'active']);
        $route = TelephonyRoute::create([
            'name' => 'Rota em teste', 'tech' => '5001', 'host' => '201.23.69.26', 'transport' => 'udp',
            'authentication_mode' => 'ip', 'dialplan_context' => 'thconect-outbound', 'dialplan_extension' => 's',
            'codecs' => 'alaw,g729', 'enabled' => true, 'connection_status' => 'pending_test',
        ]);
        $admin->company->telephonyRoutes()->attach($route);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id, 'name' => 'Energia ativa', 'dialing_mode' => 'power', 'status' => 'active',
            'fixed_telephony_route_id' => $route->id, 'mobile_telephony_route_id' => $route->id,
        ]);
        $campaign->teams()->attach($team, ['company_id' => $admin->company_id]);
        $campaign->contactLists()->attach($list, ['company_id' => $admin->company_id]);

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index'))
            ->assertOk()
            ->assertSee('Discagem bloqueada')
            ->assertSee('Rota em teste')
            ->assertSee('aguarda uma chamada real de validacao');
    }

    public function test_new_campaign_form_does_not_inherit_an_existing_campaign_configuration(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista da campanha existente']);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha ativa existente',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $campaign->teams()->attach($team, ['company_id' => $admin->company_id]);
        $campaign->contactLists()->attach($list, ['company_id' => $admin->company_id]);

        $response = $this->actingAs($admin)->get(route('tenant.campaigns.index'))->assertOk();
        $document = new \DOMDocument();
        $previousErrorSetting = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        $xpath = new \DOMXPath($document);
        $createModal = $xpath->query('//div[@data-ui-modal and contains(@x-data, "campaign-create")]')->item(0);
        $this->assertNotNull($createModal);

        $name = $xpath->query('.//input[@name="name"]', $createModal)->item(0);
        $status = $xpath->query('.//select[@name="status"]/option[@selected]', $createModal)->item(0);
        $modeInput = $xpath->query('.//select[@name="dialing_mode"]', $createModal)->item(0);
        $amdInput = $xpath->query('.//select[@name="amd_mode"]', $createModal)->item(0);
        $reviewMode = $xpath->query('.//div[p[normalize-space()="Modo"]]/p[2]', $createModal)->item(0);
        $reviewAmd = $xpath->query('.//div[p[normalize-space()="AMD"]]/p[2]', $createModal)->item(0);
        $selectedTeam = $xpath->query('.//input[@name="team_ids[]"][@checked]', $createModal)->length;
        $selectedList = $xpath->query('.//input[@name="list_ids[]"][@checked]', $createModal)->length;

        $this->assertSame('', $name?->getAttribute('value'));
        $this->assertSame('draft', $status?->getAttribute('value'));
        $this->assertSame('dialingMode', $modeInput?->getAttribute('x-model'));
        $this->assertSame('amdMode', $amdInput?->getAttribute('x-model'));
        $this->assertSame('dialingMode', $reviewMode?->getAttribute('x-text'));
        $this->assertSame('amdModeLabel()', $reviewAmd?->getAttribute('x-text'));
        $this->assertSame('power', trim($reviewMode?->textContent ?? ''));
        $this->assertSame(0, $selectedTeam);
        $this->assertSame(0, $selectedList);
    }

    public function test_campaign_index_reads_legacy_mailing_without_materializing_phone_states(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Grande', 'status' => 'active']);
        $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Lead pendente',
            'phone' => '31999990019',
            'status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha de leitura',
            'dialing_mode' => 'power',
            'status' => 'draft',
        ]);
        $campaign->teams()->attach($team, ['company_id' => $admin->company_id]);
        $campaign->contactLists()->attach($list, ['company_id' => $admin->company_id]);

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index'))
            ->assertOk()
            ->assertSee('Pendentes 1 (100,00%)');

        $this->assertDatabaseMissing('campaign_phone_states', [
            'campaign_id' => $campaign->id,
        ]);
    }

    public function test_tenant_admin_can_update_and_inactivate_contact_list(): void
    {
        [$admin] = $this->tenantAdmin();
        $list = ContactList::create([
            'company_id' => $admin->company_id,
            'name' => 'Lista Antiga',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('tenant.lists.update', $list), [
                'name' => 'Lista Atualizada',
                'description' => 'Base revisada',
                'status' => 'active',
                'contacts' => 'Pedro;31999990003',
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status');

        $list->refresh();

        $this->assertSame('Lista Atualizada', $list->name);
        $this->assertSame(1, $list->contacts()->count());

        $this->actingAs($admin)
            ->delete(route('tenant.lists.destroy', $list))
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $list->fresh()->status);
    }

    public function test_tenant_admin_can_create_campaign_with_multiple_teams_and_lists(): void
    {
        [$admin, $teamA] = $this->tenantAdmin();
        $teamA->update(['operational_type' => 'preview']);
        $teamB = Team::create([
            'company_id' => $admin->company_id,
            'name' => 'Retencao',
            'slug' => 'retencao',
            'operational_type' => 'preview',
        ]);
        $listA = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista A']);
        $listB = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista B']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Vendas',
                'description' => 'Operacao ativa',
                'service_script' => 'Cumprimente o cliente e confirme o interesse.',
                'dialing_mode' => 'preview',
                'status' => 'draft',
                'team_ids' => [$teamA->id, $teamB->id],
                'list_ids' => [$listA->id, $listB->id],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Vendas')->sole();

        $this->assertSame('preview', $campaign->dialing_mode);
        $this->assertSame('Cumprimente o cliente e confirme o interesse.', $campaign->service_script);
        $this->assertSame(2, $campaign->teams()->count());
        $this->assertSame(2, $campaign->contactLists()->count());
    }

    public function test_campaign_create_validation_reopens_the_modal_and_explains_missing_team_and_list(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->from(route('tenant.campaigns.index'))
            ->post(route('tenant.campaigns.store'), [
                '_campaign_form' => 'create',
                'name' => 'Campanha sem publico',
                'dialing_mode' => 'preview',
                'status' => 'draft',
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHasErrors(['team_ids', 'list_ids']);

        $this->assertDatabaseMissing('campaigns', [
            'company_id' => $admin->company_id,
            'name' => 'Campanha sem publico',
        ]);
    }

    public function test_tenant_admin_can_configure_campaign_schedule_and_list_weights(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $listA = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Peso A']);
        $listB = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Peso B']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Agenda',
                'dialing_mode' => 'power',
                'status' => 'draft',
                'priority' => 2,
                'team_ids' => [$team->id],
                'list_ids' => [
                    $listA->id => ['selected' => '1', 'weight' => 3],
                    $listB->id => ['selected' => '1', 'weight' => 1],
                ],
                'operational_schedule' => [
                    'monday' => ['enabled' => '1', 'start' => '09:00', 'end' => '18:00'],
                    'saturday' => ['enabled' => '1', 'start' => '10:00', 'end' => '14:00'],
                    'sunday' => ['start' => '09:00', 'end' => '12:00'],
                ],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Agenda')->sole();

        $this->assertSame([
            'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'saturday' => ['enabled' => true, 'start' => '10:00', 'end' => '14:00'],
        ], $campaign->operational_schedule);
        $this->assertSame(3, (int) $campaign->contactLists()->where('contact_lists.id', $listA->id)->sole()->pivot->weight);
        $this->assertSame(1, (int) $campaign->contactLists()->where('contact_lists.id', $listB->id)->sole()->pivot->weight);
    }

    public function test_tenant_admin_can_configure_campaign_lead_phone_strategy(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Telefones Lead']);

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index'))
            ->assertOk()
            ->assertSee('Estrategia de telefones do lead')
            ->assertSee('Distribuicao entre operadores')
            ->assertSee('Mais ocioso')
            ->assertSee('Horizontal desativado')
            ->assertSee('Horizontal ativado')
            ->assertSee('desativado tenta principais primeiro; ativado tenta alternativos do mesmo lead antes de seguir.');

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Telefones Lead',
                'dialing_mode' => 'power',
                'lead_phone_strategy' => 'horizontal',
                'agent_distribution_strategy' => 'balanced',
                'status' => 'draft',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Telefones Lead')->sole();

        $this->assertSame('horizontal', $campaign->lead_phone_strategy);
        $this->assertSame('balanced', $campaign->agent_distribution_strategy);

        $team->update(['operational_type' => 'preview']);

        $this->actingAs($admin)
            ->put(route('tenant.campaigns.update', $campaign), [
                'name' => 'Campanha Telefones Lead Atualizada',
                'dialing_mode' => 'preview',
                'lead_phone_strategy' => 'vertical',
                'agent_distribution_strategy' => 'random',
                'status' => 'active',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('vertical', $campaign->fresh()->lead_phone_strategy);
        $this->assertSame('random', $campaign->fresh()->agent_distribution_strategy);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Telefones Lead Invalida',
                'dialing_mode' => 'power',
                'lead_phone_strategy' => 'diagonal',
                'status' => 'draft',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
            ])
            ->assertSessionHasErrors('lead_phone_strategy');

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Distribuicao Invalida',
                'dialing_mode' => 'power',
                'agent_distribution_strategy' => 'por-equipe',
                'status' => 'draft',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
            ])
            ->assertSessionHasErrors('agent_distribution_strategy');
    }

    public function test_campaign_creation_ignores_dynamic_service_fields_sent_manually(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Campos']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Campos',
                'dialing_mode' => 'power',
                'status' => 'draft',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
                'service_fields_schema' => [
                    ['enabled' => '1', 'key' => 'observacao', 'label' => 'Observacao', 'type' => 'text'],
                    ['enabled' => '1', 'key' => 'quantidade', 'label' => 'Quantidade', 'type' => 'number'],
                    ['enabled' => '1', 'key' => 'data', 'label' => 'Data', 'type' => 'date'],
                    ['enabled' => '1', 'key' => 'cpf', 'label' => 'CPF', 'type' => 'cpf', 'required' => '1'],
                    ['enabled' => '1', 'key' => 'cnpj', 'label' => 'CNPJ', 'type' => 'cnpj'],
                    ['enabled' => '1', 'key' => 'telefone', 'label' => 'Telefone', 'type' => 'phone'],
                    ['enabled' => '1', 'key' => 'email', 'label' => 'E-mail', 'type' => 'email'],
                    ['enabled' => '1', 'key' => 'valor', 'label' => 'Valor', 'type' => 'currency'],
                ],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Campos')->sole();

        $this->assertNull($campaign->service_fields_schema);

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index'))
            ->assertOk()
            ->assertDontSee('Campos dinamicos')
            ->assertDontSee('CPF Cliente');
    }

    public function test_campaign_creation_ignores_unknown_dynamic_service_field_type(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Tipo Invalido']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Tipo Invalido',
                'dialing_mode' => 'power',
                'status' => 'draft',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
                'service_fields_schema' => [
                    ['enabled' => '1', 'key' => 'arquivo', 'label' => 'Arquivo', 'type' => 'file'],
                ],
            ])
            ->assertRedirect(route('tenant.campaigns.index'));

        $this->assertDatabaseHas('campaigns', ['name' => 'Campanha Tipo Invalido']);
    }

    public function test_tenant_admin_can_update_and_finish_campaign(): void
    {
        [$admin, $teamA] = $this->tenantAdmin();
        $teamB = Team::create(['company_id' => $admin->company_id, 'name' => 'Suporte', 'slug' => 'suporte']);
        $listA = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista A']);
        $listB = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista B']);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Antiga',
            'dialing_mode' => 'preview',
            'status' => 'draft',
        ]);
        $campaign->teams()->sync([$teamA->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$listA->id => ['company_id' => $admin->company_id]]);

        $this->actingAs($admin)
            ->put(route('tenant.campaigns.update', $campaign), [
                'name' => 'Campanha Atualizada',
                'description' => 'Operacao revisada',
                'dialing_mode' => 'power',
                'status' => 'active',
                'priority' => 4,
                'team_ids' => [$teamA->id, $teamB->id],
                'list_ids' => [$listA->id, $listB->id],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign->refresh();

        $this->assertSame('Campanha Atualizada', $campaign->name);
        $this->assertSame('power', $campaign->dialing_mode);
        $this->assertSame(2, $campaign->teams()->count());
        $this->assertSame(2, $campaign->contactLists()->count());

        $this->actingAs($admin)
            ->delete(route('tenant.campaigns.destroy', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('finished', $campaign->fresh()->status);
    }

    public function test_tenant_admin_can_run_operational_campaign_actions_and_duplicate_campaign(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Operacional']);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Operacional',
            'dialing_mode' => 'preview',
            'status' => 'draft',
            'priority' => 3,
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.start', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->starts_at);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.pause', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('paused', $campaign->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.restart', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('active', $campaign->fresh()->status);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.duplicate', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $copy = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Operacional - copia')->sole();

        $this->assertSame('draft', $copy->status);
        $this->assertSame('preview', $copy->dialing_mode);
        $this->assertSame(3, $copy->priority);
        $this->assertSame([$team->id], $copy->teams()->pluck('teams.id')->all());
        $this->assertSame([$list->id], $copy->contactLists()->pluck('contact_lists.id')->all());

        $this->actingAs($admin)
            ->delete(route('tenant.campaigns.destroy', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign->refresh();

        $this->assertSame('finished', $campaign->status);
        $this->assertNotNull($campaign->ends_at);
    }

    public function test_campaign_operational_actions_are_tenant_scoped(): void
    {
        [$admin] = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherCampaign = Campaign::create([
            'company_id' => $otherCompany->id,
            'name' => 'Campanha de Outro Tenant',
            'dialing_mode' => 'preview',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.start', $otherCampaign))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.duplicate', $otherCampaign))
            ->assertNotFound();

        $this->assertSame('draft', $otherCampaign->fresh()->status);
    }

    public function test_mailing_queue_selects_next_contact_and_marks_attempt_with_cooldown(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Fila', 'status' => 'active']);
        $future = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Cooldown',
            'phone' => '31999990001',
            'status' => 'pending',
            'next_attempt_at' => now()->addHour(),
        ]);
        $ready = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Pronto',
            'phone' => '31999990002',
            'status' => 'pending',
        ]);
        $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Atendido',
            'phone' => '31999990003',
            'status' => 'answered',
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Fila',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id, 'weight' => 2]]);

        $service = app(MailingQueueService::class);

        $this->assertSame($ready->id, $service->nextContactForCampaign($campaign)->id);
        $this->assertSame([
            'pending' => 2,
            'dialing' => 0,
            'delivered' => 1,
            'abandoned' => 0,
            'voicemail_pre' => 0,
            'voicemail_post' => 0,
            'no_answer' => 0,
            'failed' => 0,
        ], $service->summaryForCampaign($campaign));

        $service->markAttempt($ready, now()->addMinutes(30), campaign: $campaign);
        $ready->refresh();

        $this->assertSame('dialing', $ready->status);
        $this->assertSame(1, $ready->attempts_count);
        $this->assertNotNull($ready->last_attempt_at);
        $this->assertNotNull($ready->next_attempt_at);
        $this->assertSame($future->id, $future->fresh()->id);
        $this->assertNull($service->nextContactForCampaign($campaign));
    }

    public function test_tenant_admin_can_recycle_campaign_mailing_and_keep_tenant_scope(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Reciclagem', 'status' => 'active']);
        $recyclable = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Reciclar',
            'phone' => '31999990004',
            'status' => 'unproductive',
            'attempts_count' => 2,
            'last_result' => 'ocupado',
            'last_attempt_at' => now()->subHour(),
            'next_attempt_at' => now()->addHour(),
        ]);
        $answered = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Atendido',
            'phone' => '31999990005',
            'status' => 'answered',
            'attempts_count' => 1,
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Reciclagem',
            'dialing_mode' => 'preview',
            'status' => 'paused',
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherCampaign = Campaign::create([
            'company_id' => $otherCompany->id,
            'name' => 'Campanha Outra',
            'dialing_mode' => 'preview',
            'status' => 'paused',
        ]);
        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.recycle', $campaign), [
                'list_id' => $list->id,
                'statuses' => ['no_answer'],
                'request_key' => (string) \Illuminate\Support\Str::uuid(),
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 contato reciclado'));

        $recyclableState = CampaignPhoneState::query()
            ->where('campaign_id', $campaign->id)
            ->where('contact_id', $recyclable->id)
            ->sole();
        $answered->refresh();

        $this->assertSame('pending', $recyclableState->status);
        $this->assertNull($recyclableState->next_attempt_at);
        $this->assertSame(0, $recyclableState->attempts_count);
        $this->assertSame('unproductive', $recyclable->fresh()->status);
        $this->assertSame('answered', $answered->status);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.recycle', $otherCampaign))
            ->assertNotFound();
    }

    public function test_tenant_admin_can_trigger_simulated_campaign_dialer_from_campaign(): void
    {
        $this->travelTo(now()->next('Monday')->setTime(10, 0));

        [$admin, $team] = $this->tenantAdmin();
        $agent = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $admin->role_id,
            'user_type' => 'agent',
            'extension' => '1300',
            'status' => 'active',
        ]);
        \App\Models\AgentWebRtcPresence::create([
            'company_id' => $admin->company_id,
            'user_id' => $agent->id,
            'connection_id' => (string) \Illuminate\Support\Str::uuid(),
            'registered' => true,
            'last_seen_at' => now(),
        ]);
        $team->users()->attach($agent, ['company_id' => $admin->company_id, 'membership_type' => 'agent']);
        $agent->agentStatuses()->create([
            'company_id' => $admin->company_id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Discador', 'status' => 'active']);
        $contact = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Lead Acionado',
            'phone' => '31999990020',
            'status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Acionamento',
            'dialing_mode' => 'power',
            'status' => 'active',
            'operational_schedule' => [
                'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.dial', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 chamada criada'));

        $this->assertDatabaseHas('calls', [
            'company_id' => $admin->company_id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990020',
        ]);
        $this->assertSame('in_call', $agent->fresh()->currentAgentStatus->status);
        $this->assertSame('dialing', $contact->fresh()->status);
    }

    public function test_tenant_admin_sees_campaign_outside_schedule_state_on_index(): void
    {
        $this->travelTo(now()->next('Monday')->setTime(20, 0));

        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Agenda Visivel', 'status' => 'active']);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Estado Agenda',
            'dialing_mode' => 'power',
            'status' => 'active',
            'operational_schedule' => [
                'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index'))
            ->assertOk()
            ->assertSee('Campanha Estado Agenda')
            ->assertSee('Fora da agenda');
    }

    public function test_campaign_index_filters_by_search_status_mode_team_and_list_with_tenant_scope(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        [, $otherTeam] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Segmentada', 'status' => 'active']);
        $otherList = ContactList::create(['company_id' => $otherTeam->company_id, 'name' => 'Lista Outra Empresa', 'status' => 'active']);
        $matching = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Retencao Especial',
            'description' => 'Operacao filtravel',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $matching->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $matching->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);
        Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Pausada Fora',
            'dialing_mode' => 'preview',
            'status' => 'paused',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index', [
                'q' => 'Retencao',
                'status' => 'active',
                'dialing_mode' => 'power',
                'team_id' => $team->id,
                'list_id' => $list->id,
            ]))
            ->assertOk()
            ->assertSee('Campanha Retencao Especial')
            ->assertDontSee('Campanha Pausada Fora')
            ->assertDontSee('Lista Outra Empresa');

        $this->actingAs($admin)
            ->get(route('tenant.campaigns.index', ['team_id' => $otherTeam->id, 'list_id' => $otherList->id]))
            ->assertSessionHasErrors(['team_id', 'list_id']);
    }

    public function test_contact_list_index_filters_by_search_status_and_campaign_with_tenant_scope(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        [, $otherTeam] = $this->tenantAdmin();
        $matching = ContactList::create([
            'company_id' => $admin->company_id,
            'name' => 'Lista Grandes Contas',
            'description' => 'Base filtravel',
            'status' => 'active',
        ]);
        $hidden = ContactList::create([
            'company_id' => $admin->company_id,
            'name' => 'Lista Inativa',
            'status' => 'inactive',
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Vinculada',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$matching->id => ['company_id' => $admin->company_id]]);
        $otherCampaign = Campaign::create([
            'company_id' => $otherTeam->company_id,
            'name' => 'Campanha Outra Empresa',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.lists.index', [
                'q' => 'Grandes',
                'status' => 'active',
                'campaign_id' => $campaign->id,
            ]))
            ->assertOk()
            ->assertSee('Lista Grandes Contas')
            ->assertDontSee('Lista Inativa')
            ->assertDontSee('Campanha Outra Empresa');

        $this->actingAs($admin)
            ->get(route('tenant.lists.index', ['campaign_id' => $otherCampaign->id]))
            ->assertSessionHasErrors('campaign_id');
    }

    public function test_tenant_admin_cannot_trigger_simulated_dialer_outside_campaign_schedule(): void
    {
        $this->travelTo(now()->next('Monday')->setTime(20, 0));

        [$admin, $team] = $this->tenantAdmin();
        $agent = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $admin->role_id,
            'user_type' => 'agent',
            'extension' => '1301',
            'status' => 'active',
        ]);
        $team->users()->attach($agent, ['company_id' => $admin->company_id, 'membership_type' => 'agent']);
        $agent->agentStatuses()->create([
            'company_id' => $admin->company_id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Agenda', 'status' => 'active']);
        $contact = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Lead Fora Agenda',
            'phone' => '31999990021',
            'status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Agenda Bloqueada',
            'dialing_mode' => 'power',
            'status' => 'active',
            'operational_schedule' => [
                'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.dial', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'fora da agenda operacional'));

        $this->assertDatabaseMissing('calls', [
            'company_id' => $admin->company_id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
        ]);
        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
        $this->assertSame('pending', $contact->fresh()->status);
    }

    public function test_campaign_cannot_use_team_or_list_from_another_company(): void
    {
        [$admin] = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherTeam = Team::create(['company_id' => $otherCompany->id, 'name' => 'Outra Equipe', 'slug' => 'outra-equipe']);
        $otherList = ContactList::create(['company_id' => $otherCompany->id, 'name' => 'Outra Lista']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Invalida',
                'dialing_mode' => 'preview',
                'status' => 'draft',
                'team_ids' => [$otherTeam->id],
                'list_ids' => [$otherList->id],
            ])
            ->assertSessionHasErrors(['team_ids.0', 'list_ids.0']);
    }

    public function test_tenant_admin_can_configure_advanced_campaign_dialing_settings(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $admin->company->licenses()->firstOrFail()->update(['max_concurrent_calls' => 8]);
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Avancada']);
        $fixedRoute = TelephonyRoute::create([
            'name' => 'Rota fixa', 'tech' => '5006', 'host' => 'sbc.fixa.test', 'transport' => 'udp',
            'authentication_mode' => 'ip', 'dialplan_context' => 'thconect-outbound', 'dialplan_extension' => 's', 'codecs' => 'alaw,g729',
        ]);
        $mobileRoute = TelephonyRoute::create([
            'name' => 'Rota movel', 'tech' => '7033', 'host' => 'sbc.movel.test', 'transport' => 'udp',
            'authentication_mode' => 'ip', 'dialplan_context' => 'thconect-outbound', 'dialplan_extension' => 's', 'codecs' => 'alaw,g729',
        ]);
        $admin->company->telephonyRoutes()->attach([$fixedRoute->id, $mobileRoute->id]);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Avancada',
                'dialing_mode' => 'power',
                'status' => 'draft',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
                'max_attempts_per_contact' => 3,
                'retry_delay_minutes' => 20,
                'wrap_up_seconds' => 240,
                'max_concurrent_calls' => 4,
                'amd_mode' => 'simulated',
                'fixed_telephony_route_id' => $fixedRoute->id,
                'mobile_telephony_route_id' => $mobileRoute->id,
            ])
            ->assertRedirect(route('tenant.campaigns.index'));

        $campaign = Campaign::query()->where('company_id', $admin->company_id)->where('name', 'Campanha Avancada')->sole();

        $this->assertSame(3, $campaign->max_attempts_per_contact);
        $this->assertSame(20, $campaign->retry_delay_minutes);
        $this->assertSame(240, $campaign->wrap_up_seconds);
        $this->assertNull($campaign->max_concurrent_calls);
        $this->assertSame('simulated', $campaign->amd_mode);
        $this->assertSame($fixedRoute->id, $campaign->fixed_telephony_route_id);
        $this->assertSame($mobileRoute->id, $campaign->mobile_telephony_route_id);
    }

    public function test_campaign_cannot_override_company_concurrent_limit(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $admin->company->licenses()->firstOrFail()->update(['max_concurrent_calls' => 2]);
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Contrato']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Acima Contrato',
                'dialing_mode' => 'power',
                'status' => 'draft',
                'team_ids' => [$team->id],
                'list_ids' => [$list->id],
                'max_concurrent_calls' => 3,
            ])
            ->assertRedirect(route('tenant.campaigns.index'));

        $campaign = Campaign::query()->where('company_id', $admin->company_id)->where('name', 'Campanha Acima Contrato')->sole();
        $this->assertNull($campaign->max_concurrent_calls);
    }

    private function tenantAdmin(): array
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador',
            'is_admin' => true,
        ]);
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Vendas',
            'slug' => 'vendas',
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => (string) (1000 + User::query()->count()),
            'status' => 'active',
        ]);

        return [$admin, $team];
    }
}
