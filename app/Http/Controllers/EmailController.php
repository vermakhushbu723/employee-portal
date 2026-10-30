<?php

namespace App\Http\Controllers;

use App\Mail\PortalEmail;
use App\Models\EmailTemplate;
use App\Models\Meeting;
use App\Models\SentEmail;
use App\Services\AuditLogger;
use App\Support\MailPool;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Send Email popup (Meetings page, Email Templates page) + admin's e-mail templates.
 *   Everyone: send an e-mail (template or own text), see what they sent.
 *   Admin: create / edit / switch off templates, see every e-mail sent from the portal.
 */
class EmailController extends Controller
{
    /** Admin: Email Templates + compose + history. */
    public function index(Request $request)
    {
        $category = array_key_exists((string) $request->query('category'), EmailTemplate::CATEGORIES) ? $request->query('category') : null;

        return view('emails.index', [
            'templates' => EmailTemplate::when($category, fn ($q) => $q->where('category', $category))->orderBy('category')->orderBy('name')->get(),
            'counts' => EmailTemplate::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'total' => EmailTemplate::count(),
            'category' => $category,
            'history' => SentEmail::with('user:id,name,employee_code')->latest()->limit(30)->get(),
            'composeTemplates' => EmailTemplate::forCompose(),
        ]);
    }

    public function storeTemplate(Request $request, AuditLogger $audit)
    {
        $t = EmailTemplate::create($this->templateData($request) + ['created_by' => $request->user()->id]);
        $audit->log('email.template_create', "Created e-mail template \"{$t->name}\"");

        return back()->with('success', "Template \"{$t->name}\" saved.");
    }

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $template->update($this->templateData($request));

        return back()->with('success', "Template \"{$template->name}\" updated.");
    }

    public function destroyTemplate(EmailTemplate $template)
    {
        $template->delete();

        return back()->with('success', "Template \"{$template->name}\" deleted.");
    }

    /** Send from the popup. To = one or more addresses (comma separated). */
    public function send(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'meeting_id' => ['nullable', 'integer', 'exists:meetings,id'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv,txt'],
        ]);
        $user = $request->user();
        $to = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $data['to'])))));
        $bad = array_filter($to, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));
        if (! $to || $bad || count($to) > 10) {
            throw ValidationException::withMessages(['to' => $bad ? 'Not a valid e-mail: '.implode(', ', $bad) : 'Enter 1 to 10 e-mail addresses.']);
        }
        $meeting = $data['meeting_id'] ?? null ? Meeting::find($data['meeting_id']) : null;
        abort_if($meeting && $meeting->user_id !== $user->id && $user->role->portal() === 'employee', 403);

        $file = $request->file('attachment');
        $path = $file?->store('email-files/'.$user->id, 'local');
        $ok = MailPool::ready() && MailPool::send($to, new PortalEmail($data['subject'], $data['body'], $user, $user->replyEmail(),
            $path ? storage_path('app/private/'.$path) : null, $file?->getClientOriginalName()), "Portal e-mail by user #{$user->id}");

        SentEmail::create(['user_id' => $user->id, 'meeting_id' => $meeting?->id, 'lead_id' => $meeting?->lead_id,
            'to' => implode(', ', $to), 'subject' => $data['subject'], 'body' => $data['body'], 'status' => $ok ? 'sent' : 'failed']);
        $audit->log('email.send', ($ok ? 'Sent' : 'Failed to send')." e-mail \"{$data['subject']}\" to ".implode(', ', $to));

        $msg = $ok ? 'E-mail sent to '.implode(', ', $to).'.' : 'E-mail could not be sent — the portal mailbox is not working. Ask admin to open Email Check.';
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $msg], $ok ? 200 : 502);
        }

        return back()->with($ok ? 'success' : 'error', $msg);
    }

    private function templateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(EmailTemplate::CATEGORIES))],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'active' => ['nullable', 'boolean'],
        ]) + ['active' => $request->boolean('active', true)];
    }
}
