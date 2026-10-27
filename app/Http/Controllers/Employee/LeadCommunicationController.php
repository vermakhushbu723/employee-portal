<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadCommunication;
use App\Services\LeadCommunicationService;
use App\Services\LeadReviewService;
use Illuminate\Http\Request;

/** Employee actions on own leads: call (talk time), WhatsApp, e-mail, voice note, inline purpose / remark, details panel. */
class LeadCommunicationController extends Controller
{
    public function __construct(private LeadCommunicationService $comms) {}

    public function panel(Request $request, int $lead)
    {
        $lead = $this->lead($request, $lead)->load(['department', 'corrector', 'communications.user', 'meetings', 'activities.user']);

        return view('employee.leads.partials.panel', [
            'lead' => $lead,
            'templates' => LeadCommunicationService::templates($lead, $request->user()),
        ]);
    }

    /** Ready WhatsApp / e-mail texts for this lead (filled in). */
    public function templates(Request $request, int $lead)
    {
        $lead = $this->lead($request, $lead);

        return response()->json(LeadCommunicationService::templates($lead, $request->user()) + ['email_to' => $lead->email, 'hr' => $lead->isHr()]);
    }

    public function inline(Request $request, int $lead)
    {
        $lead = $this->lead($request, $lead);
        $data = $request->validate([
            'purpose' => ['sometimes', 'nullable', 'string', 'max:150'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        $lead->update($data);

        return response()->json(['ok' => true, 'message' => 'Saved.']);
    }

    public function startCall(Request $request, int $lead)
    {
        return response()->json(['id' => $this->comms->startCall($this->lead($request, $lead), $request->user())->id]);
    }

    public function endCall(Request $request, int $lead, int $call)
    {
        $lead = $this->lead($request, $lead);
        $data = $request->validate(['seconds' => ['required', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:500']]);
        $comm = LeadCommunication::where('lead_id', $lead->id)->where('user_id', $request->user()->id)->where('channel', 'call')->findOrFail($call);
        $this->comms->endCall($comm, (int) $data['seconds'], $data['note'] ?? null);

        return response()->json(['ok' => true, 'talk' => gmdate('i:s', min(3599, (int) $data['seconds']))]);
    }

    public function whatsapp(Request $request, int $lead)
    {
        $lead = $this->lead($request, $lead);
        $text = $request->validate(['text' => ['required', 'string', 'max:4000']])['text'];
        [$comm, $link] = $this->comms->whatsapp($lead, $request->user(), $text);

        return response()->json([
            'ok' => $comm->status === 'sent',
            'link' => $link,
            'message' => $comm->status === 'sent' ? 'WhatsApp sent to '.$lead->name.'.' : 'Your WhatsApp is not linked in the portal — opening WhatsApp to send it.',
        ]);
    }

    public function email(Request $request, int $lead)
    {
        $lead = $this->lead($request, $lead);
        $data = $request->validate([
            'to' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'extensions:pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv,txt'],
        ]);
        if (! $lead->email) {
            $lead->update(['email' => $data['to']]);
        }
        $comm = $this->comms->email($lead, $request->user(), $data['to'], $data['subject'], $data['body'], $request->file('attachment'));

        return response()->json(['ok' => $comm->status === 'sent', 'message' => $comm->status === 'sent' ? 'E-mail sent to '.$data['to'].'.' : 'E-mail could not be sent right now (saved in history).'], $comm->status === 'sent' ? 200 : 502);
    }

    public function voiceNote(Request $request, int $lead)
    {
        $lead = $this->lead($request, $lead);
        $data = $request->validate([
            // Browsers name recorded audio differently (webm / ogg / mp4 on iPhone), so the extension is checked
            'audio' => ['required', 'file', 'max:8192', 'extensions:webm,ogg,oga,mp3,m4a,mp4,wav,aac'],
            'seconds' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->comms->voiceNote($lead, $request->user(), $request->file('audio'), (int) ($data['seconds'] ?? 0));

        return response()->json(['ok' => true, 'message' => 'Voice note saved.']);
    }

    /** Play a voice note / download an e-mail file: the employee, or admin / their manager. */
    public function file(Request $request, LeadCommunication $comm)
    {
        $user = $request->user();
        $lead = $comm->lead;
        $own = $lead && ($lead->assigned_to === $user->id || $lead->created_by === $user->id);
        abort_unless($own || ($lead && app(LeadReviewService::class)->canReview($user, $lead)), 403);
        abort_unless($comm->file_path && \Storage::disk('local')->exists($comm->file_path), 404);

        return \Storage::disk('local')->response($comm->file_path);
    }

    private function lead(Request $request, int $id): Lead
    {
        return Lead::ownedBy($request->user())->findOrFail($id);
    }
}
