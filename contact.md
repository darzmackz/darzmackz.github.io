---
layout: page
title: "Contact"
description: "Get in touch — I'd love to hear from you."
permalink: /contact/
---

Have a question, want to collaborate, or just want to say hello? Fill in the form below
or reach out through one of the other channels listed here.

<div class="contact-grid">

  <div class="contact-info">
    <h3>📬 Contact Details</h3>

    <div class="contact-item">
      <span class="contact-item-icon">📧</span>
      <div>
        <strong>Email</strong>
        <p><a href="mailto:darzmackz@gmail.com">darzmackz@gmail.com</a></p>
      </div>
    </div>

    <div class="contact-item">
      <span class="contact-item-icon">🐙</span>
      <div>
        <strong>GitHub</strong>
        <p><a href="https://github.com/darzmackz" target="_blank" rel="noopener">github.com/darzmackz</a></p>
      </div>
    </div>

    <div class="contact-item">
      <span class="contact-item-icon">🎬</span>
      <div>
        <strong>YouTube</strong>
        <p><a href="https://www.youtube.com/@metaxenopy?sub_confirmation=1" target="_blank" rel="noopener">@metaxenopy</a></p>
      </div>
    </div>

  </div>

  <div class="contact-form">
    <h3>✉️ Send a Message</h3>
    <!-- This form uses Formspree — replace YOUR_FORM_ID with your actual Formspree form ID -->
    <!-- Sign up at https://formspree.io to get a form ID -->
    <form action="https://formspree.io/f/YOUR_FORM_ID" method="POST">
      <div class="form-group">
        <label for="contact-name">Your Name</label>
        <input type="text" id="contact-name" name="name" placeholder="Jane Smith" required />
      </div>
      <div class="form-group">
        <label for="contact-email">Your Email</label>
        <input type="email" id="contact-email" name="email" placeholder="jane@example.com" required />
      </div>
      <div class="form-group">
        <label for="contact-subject">Subject</label>
        <input type="text" id="contact-subject" name="subject" placeholder="What's it about?" />
      </div>
      <div class="form-group">
        <label for="contact-message">Message</label>
        <textarea id="contact-message" name="message" placeholder="Your message here…" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Send Message 🚀</button>
    </form>
  </div>

</div>

---

> **Note:** The contact form uses [Formspree](https://formspree.io) for handling submissions.
> Replace `YOUR_FORM_ID` in the HTML source with your own Formspree form ID to activate it.

