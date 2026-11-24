{{-- Help: Requesting a Credential (REQ-22-03, 04). --}}
<x-faq-item question="How do I request a credential?">
    <p>Open <a href="{{ route('student.requests.create') }}" wire:navigate>New Request</a> and fill in its sections:</p>
    <ol>
        <li><strong>What Do You Need?</strong> The credential, its purpose, and the receiving school when the credential needs one. Choose <strong>Add another credential</strong> to ask for more in the same request.</li>
        <li><strong>Personal Information</strong>, <strong>Academic Information</strong> and your <strong>Valid ID</strong>, filled in from your profile after your first request.</li>
        <li><strong>Who Will Transact?</strong> You, or an authorized representative.</li>
    </ol>
    <p>Choose <strong>Review request</strong> to see a summary, tick the data privacy box, then choose <strong>Submit request</strong>. Your request gets a tracking number such as <strong>rc26-00042</strong>; use it whenever you contact the Office of the Registrar.</p>
</x-faq-item>

<x-faq-item question="Can I request several credentials at once?">
    <p>Yes. On New Request, choose <strong>Add another credential</strong> for each one, for example a Transcript of Records and a Certification of Enrollment. They are evaluated together and paid once, for the total.</p>
    <p>Once accepted, each credential goes through its own offices and may be ready on a different day. Your request page shows a tab for each one.</p>
</x-faq-item>

<x-faq-item question="How many requests can I have at a time?">
    <p>Up to {{ \App\Models\CredentialRequest::OPEN_LIMIT }} open credentials, counting each credential in a request. A credential stops counting once it is released, declined or cancelled; then you can request again.</p>
</x-faq-item>

<x-faq-item question="Where do I see a credential's fee and processing time?">
    <p>Choose the credential on New Request: its card shows the fee, the processing period in working days and the offices it passes through.</p>
</x-faq-item>

<x-faq-item question="What purpose should I give?">
    <p>Be specific, such as <strong>Employment</strong>, <strong>Further studies</strong> or a named board examination. General purposes such as “personal copy”, “for reference”, “for evaluation”, “upon request” or “for whatever legal purpose it may serve” are not accepted: the request is returned to you, which delays it.</p>
</x-faq-item>

<x-faq-item question="My purpose isn't in the list.">
    <p>Choose <strong>Others (please specify)</strong> at the end of the list and type your purpose. Make it specific.</p>
</x-faq-item>

<x-faq-item question="Why does it ask for a receiving school?">
    <p>Transfer credentials, such as the Certificate of Transfer, are issued for a specific school, and its name is printed on them. Give its complete name, for example <strong>St. Michael's College of Laguna</strong>.</p>
</x-faq-item>

<x-faq-item question="Why does it ask for terms and an academic year?">
    <p>The Semestral Report of Grades is for the terms of one academic year. Tick each term you need; the fee is per term. For another academic year, add it as another credential.</p>
</x-faq-item>

<x-faq-item question="Do I need to submit documents?">
    <p>Not online. The Office of the Registrar checks your records when it evaluates your request.</p>
    <p>If it needs a document from you, such as your Form 137, your request page says what to bring. Submit the originals in person at the Office of the Registrar, or send the representative you named on New Request with a Special Power of Attorney (SPA) and the valid IDs of you both.</p>
</x-faq-item>

<x-faq-item question="How do I request more than one copy?">
    <p>One credential is one document. For another copy of the same credential, submit another request.</p>
</x-faq-item>

<x-faq-item question="What happens after I submit?">
    <p>The Office of the Registrar evaluates your request: your identity, your academic record and, if you named one, your representative. It then decides one of four things, and you are notified:</p>
    <ul>
        <li><strong>Approved</strong>: you get payment instructions.</li>
        <li><strong>Returned for clarification</strong>: something needs correcting. The request page says what; choose <strong>Edit Request</strong>, fix it and resubmit.</li>
        <li><strong>Pending compliance</strong>: you need to visit the Office of the Registrar (see below).</li>
        <li><strong>Declined</strong>: the request can't go ahead; the reason is on its page.</li>
    </ul>
</x-faq-item>

<x-faq-item question="It says “Visit the Office of the Registrar”. What do I do?">
    <p>Some originals or your clearance must be submitted in person. Your request page lists exactly what to bring, and ticks off each item as the office receives it. Go yourself, or send the representative you named on New Request with a Special Power of Attorney (SPA) and the valid IDs of you both.</p>
    <p>There is nothing to do online: once everything is received, the Office of the Registrar moves your request on to payment.</p>
</x-faq-item>

<x-faq-item question="Can I change a request after submitting it?">
    <p>Only when the Office of the Registrar returns it to you for clarification: then <strong>Edit Request</strong> lets you change the credential, purpose, receiving school, and your details and ID before resubmitting.</p>
    <p>Otherwise, cancel it (while you still can) and submit a new one.</p>
</x-faq-item>

<x-faq-item question="Can I cancel a request?">
    <p>Yes, with <strong>Cancel request</strong> at the top of its page, until it is <strong>accepted for processing</strong> (that is, until your payment is verified). This cancels every credential in the request. Giving a reason is optional. After acceptance, the offices have started on your documents and it can no longer be cancelled.</p>
</x-faq-item>
