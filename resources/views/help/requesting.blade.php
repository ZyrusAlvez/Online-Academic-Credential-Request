{{-- Help: Requesting a Credential (REQ-22-03, 04). --}}
<x-faq-item question="How do I request a credential?">
    <p>Open <a href="{{ route('student.requests.create') }}" wire:navigate>New Request</a> and fill in its sections:</p>
    <ol>
        <li><strong>What Do You Need?</strong> The credential, its purpose, and the receiving school when the credential needs one.</li>
        <li><strong>Documentary Requirements</strong>: copies, if you have them (optional).</li>
        <li><strong>Personal Information</strong>, <strong>Academic Information</strong> and your <strong>Valid ID</strong>, filled in from your profile after your first request.</li>
        <li><strong>Who Will Transact?</strong> You, or an authorized representative.</li>
    </ol>
    <p>Choose <strong>Review request</strong> to see a summary, tick the data privacy box, then choose <strong>Submit request</strong>. Your request gets a tracking number such as <strong>cr26-00042</strong>; use it whenever you contact the Office of the Registrar.</p>
</x-faq-item>

<x-faq-item question="Where do I see a credential's fee, processing time and requirements?">
    <p>Choose the credential on New Request: its card shows the fee, the processing period in working days, its documentary requirements and the offices it passes through.</p>
</x-faq-item>

<x-faq-item question="My purpose isn't in the list.">
    <p>Choose <strong>Others (please specify)</strong> at the end of the list and type your purpose.</p>
</x-faq-item>

<x-faq-item question="Why does it ask for a receiving school?">
    <p>Transfer credentials, such as a TOR for Transfer Credentials, are issued for a specific school. Give its complete name.</p>
</x-faq-item>

<x-faq-item question="Do I have to upload the documentary requirements?">
    <p>No, the uploads are optional. Copies help the evaluator, but the Office of the Registrar may still ask you to bring the originals or complete your clearance in person.</p>
    <p>You can add up to {{ \App\Models\RequestDocument::MAX_PER_REQUIREMENT }} files per requirement, each a JPG, PNG, WebP or PDF of up to 5 MB. Choosing files again adds to the ones already listed; use <strong>Remove</strong> to take one out. Select a file's name to view it.</p>
</x-faq-item>

<x-faq-item question="How do I request more than one copy?">
    <p>One request is for one document. If you need more copies, submit another request for each.</p>
</x-faq-item>

<x-faq-item question="What happens after I submit?">
    <p>The Office of the Registrar evaluates your request: your identity, your academic record, the requirements and, if you named one, your representative. It then decides one of four things, and you are notified:</p>
    <ul>
        <li><strong>Approved</strong>: you get payment instructions, or, for a free credential, your request is accepted for processing right away.</li>
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
    <p>Only when the Office of the Registrar returns it to you for clarification: then <strong>Edit Request</strong> lets you change the credential, purpose, receiving school, your details and ID, and add requirement copies before resubmitting.</p>
    <p>Otherwise, cancel it (while you still can) and submit a new one.</p>
</x-faq-item>

<x-faq-item question="Can I cancel a request?">
    <p>Yes, from its page, until it is <strong>accepted for processing</strong> (that is, until your payment is verified, or the evaluation is done for a free credential). Giving a reason is optional. After acceptance, the offices have started on your document and it can no longer be cancelled.</p>
</x-faq-item>
