<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(TicketAttachment::DISK));

function tauUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

/**
 * @return array<string, mixed>
 */
function tauPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => Category::factory()->create()->id,
        'title' => 'Laptop tidak bisa menyala',
        'description' => 'Sudah dicas semalaman.',
        'priority' => 'high',
    ], $overrides);
}

it('saves files sent while creating a ticket', function () {
    $employee = tauUser(Role::Employee);

    $this->actingAs($employee)
        ->post(route('tickets.store'), tauPayload([
            'files' => [UploadedFile::fake()->image('foto.png'), UploadedFile::fake()->image('foto2.jpg')],
        ]))
        ->assertSessionHasNoErrors();

    $ticket = Ticket::query()->sole();
    $files = TicketAttachment::query()->get();

    expect($files)->toHaveCount(2)
        ->and($files->pluck('ticket_id')->unique()->all())->toBe([$ticket->id])
        ->and($files->pluck('uploaded_by')->unique()->all())->toBe([$employee->id])
        ->and($files->pluck('comment_id')->filter()->all())->toBe([]);
});

it('still creates a ticket without any file', function () {
    $this->actingAs(tauUser(Role::Employee))
        ->post(route('tickets.store'), tauPayload())
        ->assertSessionHasNoErrors();

    expect(Ticket::query()->count())->toBe(1)
        ->and(TicketAttachment::query()->count())->toBe(0);
});

it('creates nothing when a file is invalid', function () {
    $this->actingAs(tauUser(Role::Employee))
        ->post(route('tickets.store'), tauPayload([
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ]))
        ->assertSessionHasErrors('files.0');

    expect(Ticket::query()->count())->toBe(0)
        ->and(TicketAttachment::query()->count())->toBe(0);
});

it('rejects more than five files when creating a ticket', function () {
    $this->actingAs(tauUser(Role::Employee))
        ->post(route('tickets.store'), tauPayload([
            'files' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.png"), range(1, 6)),
        ]))
        ->assertSessionHasErrors('files');

    expect(Ticket::query()->count())->toBe(0);
});

it('sends the visible attachments and delete rights to the ticket page', function () {
    $employee = tauUser(Role::Employee);
    $technician = tauUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id]);
    $note = TicketComment::factory()->internal()->create(['ticket_id' => $ticket->id, 'user_id' => $technician->id]);

    TicketAttachment::factory()->create(['ticket_id' => $ticket->id, 'uploaded_by' => $employee->id, 'original_name' => 'milik-pelapor.png']);
    TicketAttachment::factory()->create(['ticket_id' => $ticket->id, 'uploaded_by' => $technician->id, 'original_name' => 'milik-teknisi.png']);
    TicketAttachment::factory()->create(['ticket_id' => $ticket->id, 'comment_id' => $note->id, 'uploaded_by' => $technician->id, 'original_name' => 'internal.png']);

    // Pelapor: tidak melihat lampiran catatan internal, hanya bisa menghapus miliknya.
    $this->actingAs($employee)->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attachments', 2)
            ->where('attachments.0.name', 'milik-pelapor.png')
            ->where('attachments.0.uploader', $employee->name)
            ->where('attachments.0.can_delete', true)
            ->where('attachments.1.can_delete', false)
            ->where('can.attach', true)
        );

    $this->actingAs($employee)->get(route('tickets.show', $ticket))->assertDontSee('internal.png');

    $this->actingAs($technician)->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->has('attachments', 3));
});
