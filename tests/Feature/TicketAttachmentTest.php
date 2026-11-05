<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(TicketAttachment::DISK));

function taUserA(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

function taTicketA(User $employee, User $technician, array $attributes = []): Ticket
{
    return Ticket::factory()->assignedTo($technician)->create(array_merge(['user_id' => $employee->id], $attributes));
}

function taPdf(string $name = 'laporan.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
}

/** Lampiran nyata: baris database plus file di disk. */
function taStored(Ticket $ticket, User $uploader, ?TicketComment $comment = null): TicketAttachment
{
    $path = 'ticket-attachments/'.$ticket->id.'/'.fake()->uuid().'.png';
    Storage::disk(TicketAttachment::DISK)->put($path, 'isi-file');

    return TicketAttachment::factory()->create([
        'ticket_id' => $ticket->id,
        'comment_id' => $comment?->id,
        'uploaded_by' => $uploader->id,
        'stored_path' => $path,
        'original_name' => 'bukti.png',
    ]);
}

it('redirects guests to the login page', function () {
    $ticket = Ticket::factory()->create();
    $attachment = TicketAttachment::factory()->create(['ticket_id' => $ticket->id]);

    $this->post(route('tickets.attachments.store', $ticket))->assertRedirect('/login');
    $this->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertRedirect('/login');
    $this->delete(route('tickets.attachments.destroy', [$ticket, $attachment]))->assertRedirect('/login');
});

it('stores uploaded files privately under a random name and records them', function () {
    $employee = taUserA(Role::Employee);
    $ticket = taTicketA($employee, taUserA(Role::ItSupport));

    $this->actingAs($employee)
        ->post(route('tickets.attachments.store', $ticket), [
            'files' => [UploadedFile::fake()->image('layar error.png'), taPdf()],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tickets.show', $ticket));

    $rows = TicketAttachment::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->original_name)->toBe('layar error.png')
        ->and($rows[0]->mime_type)->toBe('image/png')
        ->and($rows[1]->mime_type)->toBe('application/pdf')
        ->and($rows[1]->uploaded_by)->toBe($employee->id)
        ->and($rows[1]->comment_id)->toBeNull();

    foreach ($rows as $row) {
        expect($row->stored_path)->toStartWith('ticket-attachments/'.$ticket->id.'/')
            ->and($row->stored_path)->not->toContain('layar')
            ->and($row->stored_path)->not->toContain('laporan');

        expect(Storage::disk(TicketAttachment::DISK)->exists($row->stored_path))->toBeTrue();
    }
});

/** File sungguhan di disk, agar tipenya dibaca dari isi, bukan dari nama. */
function taRealFile(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ta');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

/**
 * Daftar file bermasalah, dibuat saat dibutuhkan (bukan di dataset).
 *
 * @return array<int, UploadedFile>
 */
function taBadFiles(string $case): array
{
    return match ($case) {
        'executable' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        'script renamed to png' => [taRealFile('evil.png', '<?php echo "x";')],
        'text file' => [UploadedFile::fake()->createWithContent('catatan.txt', 'halo')],
        'over 5 MB' => [UploadedFile::fake()->image('besar.png')->size(5121)],
        'none' => [],
        'too many' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.png"), range(1, 6)),
    };
}

it('rejects files with a wrong type, disguised content or too large a size', function (string $case) {
    $employee = taUserA(Role::Employee);
    $ticket = taTicketA($employee, taUserA(Role::ItSupport));

    $this->actingAs($employee)
        ->post(route('tickets.attachments.store', $ticket), ['files' => taBadFiles($case)])
        ->assertSessionHasErrors();

    expect(TicketAttachment::query()->count())->toBe(0);
})->with(['executable', 'script renamed to png', 'text file', 'over 5 MB', 'none', 'too many']);

it('accepts a file of exactly 5 MB', function () {
    $employee = taUserA(Role::Employee);
    $ticket = taTicketA($employee, taUserA(Role::ItSupport));

    $this->actingAs($employee)
        ->post(route('tickets.attachments.store', $ticket), [
            'files' => [UploadedFile::fake()->image('pas.jpg')->size(5120)],
        ])
        ->assertSessionHasNoErrors();

    expect(TicketAttachment::query()->count())->toBe(1);
});

it('lets the requester, assigned technician and admin upload but nobody else', function () {
    $employee = taUserA(Role::Employee);
    $technician = taUserA(Role::ItSupport);
    $ticket = taTicketA($employee, $technician);
    $closed = taTicketA($employee, $technician, ['status' => TicketStatus::Closed]);
    $file = fn () => ['files' => [UploadedFile::fake()->image('a.png')]];

    foreach ([$employee, $technician, taUserA(Role::Admin)] as $user) {
        $this->actingAs($user)->post(route('tickets.attachments.store', $ticket), $file())->assertSessionHasNoErrors();
    }

    $this->actingAs(taUserA(Role::Employee))->post(route('tickets.attachments.store', $ticket), $file())->assertForbidden();
    $this->actingAs(taUserA(Role::ItSupport))->post(route('tickets.attachments.store', $ticket), $file())->assertForbidden();
    $this->actingAs($employee)->post(route('tickets.attachments.store', $closed), $file())->assertForbidden();

    expect(TicketAttachment::query()->count())->toBe(3);
});

it('attaches files to the sender\'s own comment on this ticket only', function () {
    $employee = taUserA(Role::Employee);
    $technician = taUserA(Role::ItSupport);
    $ticket = taTicketA($employee, $technician);

    $own = TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $employee->id]);
    $others = TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $technician->id]);
    $elsewhere = TicketComment::factory()->create(['user_id' => $employee->id]);
    $file = fn (int $commentId) => ['files' => [UploadedFile::fake()->image('a.png')], 'comment_id' => $commentId];

    $this->actingAs($employee)->post(route('tickets.attachments.store', $ticket), $file($own->id))->assertSessionHasNoErrors();
    $this->actingAs($employee)->post(route('tickets.attachments.store', $ticket), $file($others->id))->assertSessionHasErrors('comment_id');
    $this->actingAs($employee)->post(route('tickets.attachments.store', $ticket), $file($elsewhere->id))->assertSessionHasErrors('comment_id');

    expect(TicketAttachment::query()->sole()->comment_id)->toBe($own->id);
});

it('downloads the file under its original name for people who can see the ticket', function () {
    $employee = taUserA(Role::Employee);
    $technician = taUserA(Role::ItSupport);
    $ticket = taTicketA($employee, $technician);
    $attachment = taStored($ticket, $employee);

    foreach ([$employee, $technician, taUserA(Role::Admin)] as $user) {
        $this->actingAs($user)
            ->get(route('tickets.attachments.download', [$ticket, $attachment]))
            ->assertOk()
            ->assertDownload('bukti.png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
});

it('refuses downloads by people who cannot see the ticket', function () {
    $employee = taUserA(Role::Employee);
    $ticket = taTicketA($employee, taUserA(Role::ItSupport));
    $attachment = taStored($ticket, $employee);

    $this->actingAs(taUserA(Role::Employee))->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
    $this->actingAs(taUserA(Role::ItSupport))->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
});

it('returns 404 when the attachment belongs to another ticket or its file is gone', function () {
    $employee = taUserA(Role::Employee);
    $ticket = taTicketA($employee, taUserA(Role::ItSupport));
    $other = taTicketA($employee, taUserA(Role::ItSupport));
    $foreign = taStored($other, $employee);
    $missing = taStored($ticket, $employee);
    Storage::disk(TicketAttachment::DISK)->delete($missing->stored_path);

    $this->actingAs($employee)->get(route('tickets.attachments.download', [$ticket, $foreign]))->assertNotFound();
    $this->actingAs($employee)->get(route('tickets.attachments.download', [$ticket, $missing]))->assertNotFound();
});

it('keeps files attached to internal notes away from the requester', function () {
    $employee = taUserA(Role::Employee);
    $technician = taUserA(Role::ItSupport);
    $ticket = taTicketA($employee, $technician);
    $note = TicketComment::factory()->internal()->create(['ticket_id' => $ticket->id, 'user_id' => $technician->id]);
    $attachment = taStored($ticket, $technician, $note);

    $this->actingAs($employee)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
    $this->actingAs($technician)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertOk();

    $names = fn (User $user) => TicketAttachment::query()->visibleTo($user)->pluck('id')->all();

    expect($names($employee))->toBe([])
        ->and($names($technician))->toBe([$attachment->id]);
});

it('lets only the uploader or an admin delete, and removes the file', function () {
    $employee = taUserA(Role::Employee);
    $technician = taUserA(Role::ItSupport);
    $ticket = taTicketA($employee, $technician);
    $mine = taStored($ticket, $employee);
    $theirs = taStored($ticket, $technician);

    // Pelapor tidak boleh menghapus lampiran milik teknisi.
    $this->actingAs($employee)->delete(route('tickets.attachments.destroy', [$ticket, $theirs]))->assertForbidden();

    $this->actingAs($employee)
        ->delete(route('tickets.attachments.destroy', [$ticket, $mine]))
        ->assertRedirect(route('tickets.show', $ticket));

    expect(Storage::disk(TicketAttachment::DISK)->exists($mine->stored_path))->toBeFalse();
    expect(TicketAttachment::query()->find($mine->id))->toBeNull();

    $this->actingAs(taUserA(Role::Admin))->delete(route('tickets.attachments.destroy', [$ticket, $theirs]))->assertRedirect();

    expect(TicketAttachment::query()->count())->toBe(0);
});

it('does not delete from a closed ticket or through another ticket', function () {
    $employee = taUserA(Role::Employee);
    $technician = taUserA(Role::ItSupport);
    $closed = taTicketA($employee, $technician, ['status' => TicketStatus::Closed]);
    $open = taTicketA($employee, $technician);
    $onClosed = taStored($closed, $employee);
    $onOpen = taStored($open, $employee);

    $this->actingAs($employee)->delete(route('tickets.attachments.destroy', [$closed, $onClosed]))->assertForbidden();
    $this->actingAs($employee)->delete(route('tickets.attachments.destroy', [$closed, $onOpen]))->assertNotFound();

    expect(TicketAttachment::query()->count())->toBe(2);
});
