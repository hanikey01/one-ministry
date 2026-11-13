<x-layouts.public :settings="$settings" :features="$features" title="Contact">
    <section class="public-heading">
        <p>CONTACT</p>
        <h1>We would love to hear from you.</h1>
        <span>Send a message and the ministry team will follow up using the details you choose to share.</span>
    </section>

    <section class="public-page-contact-form public-contact-card" aria-labelledby="contact-form-title">
        <div>
            <h2 id="contact-form-title">Send a message</h2>
            <p>Tell us how we can help. Fields marked required are needed to respond.</p>
        </div>
        @if(request()->query('contact') === 'received')
            <p class="public-page-form-success" role="status">Thank you. Your message has been received.</p>
        @endif
        <form class="public-contact-form" method="POST" action="{{ url('/api/v1/public/contact') }}">
            <label>First name <input name="first_name" required maxlength="100" autocomplete="given-name"></label>
            <label>Last name <input name="last_name" maxlength="100" autocomplete="family-name"></label>
            <label>Email address <input name="email" type="email" maxlength="254" autocomplete="email"></label>
            <label>Phone <input name="phone" maxlength="40" autocomplete="tel"></label>
            <label class="public-contact-message">How can we help? <textarea name="message" rows="5" maxlength="2000" placeholder="Share your question or request"></textarea></label>
            <label class="public-page-consent"><input name="consent_to_contact" type="checkbox" value="1" required>I agree that the ministry may contact me about this request.</label>
            <button class="button" type="submit">Send message</button>
        </form>
    </section>
</x-layouts.public>
