<script setup lang="ts">
useApfSeo({
  title: 'Contact Us | APF Press',
  description: 'Contact APF Press for orders, manuscript inquiries, editorial questions, and mailing information.',
  path: '/contact-us/'
})

const submitted = ref(false)
const form = reactive({
  name: '',
  email: '',
  subject: '',
  message: ''
})

function submitContact() {
  const subject = encodeURIComponent(form.subject || 'APF Press inquiry')
  const body = encodeURIComponent([
    `Name: ${form.name}`,
    `Email: ${form.email}`,
    '',
    form.message
  ].join('\n'))

  submitted.value = true

  if (import.meta.client) {
    window.location.href = `mailto:apf.press@rogers.com?subject=${subject}&body=${body}`
  }
}
</script>

<template>
  <div>
    <PageHero
      eyebrow="Contact"
      title="Get in Touch with APF Press"
      text="Have a question about orders, manuscripts, or APF Press titles? Reach out and the team will respond with clear next steps."
    />

    <section class="content-band">
      <VContainer>
        <div class="contact-grid">
          <aside class="contact-panel">
            <p class="eyebrow">
              Contact details
            </p>
            <h2>Orders & manuscript inquiries</h2>

            <div class="contact-row">
              <VIcon icon="mdi-account-edit-outline" />
              <div>
                <h3>Editorial Team</h3>
                <p>Senior Editor: R. Doyle, PhD</p>
                <p>Managing Editor: Andrew Urie, PhD</p>
              </div>
            </div>

            <div class="contact-row">
              <VIcon icon="mdi-phone-outline" />
              <div>
                <h3>Phone</h3>
                <a href="tel:14168171266">416-817-1266</a>
              </div>
            </div>

            <div class="contact-row">
              <VIcon icon="mdi-email-outline" />
              <div>
                <h3>Email</h3>
                <a href="mailto:apf.press@rogers.com">apf.press@rogers.com</a>
              </div>
            </div>

            <div class="contact-row">
              <VIcon icon="mdi-map-marker-outline" />
              <div>
                <h3>Mailing Address</h3>
                <p>Richard Dean</p>
                <a href="https://maps.app.goo.gl/DWWC1PgfELjNBKGB7">
                  4 Carnegie Ct. Toronto, ON M2M 1W2
                </a>
              </div>
            </div>
          </aside>

          <form class="contact-form" @submit.prevent="submitContact">
            <p class="eyebrow">
              Send a message
            </p>
            <h2>Start an inquiry</h2>
            <div class="contact-form__fields">
              <VTextField v-model="form.name" label="Name" autocomplete="name" required />
              <VTextField v-model="form.email" label="Email" type="email" autocomplete="email" required />
              <VTextField v-model="form.subject" label="Subject" required />
              <VTextarea v-model="form.message" label="Message" rows="6" required />
              <VBtn type="submit" color="secondary" size="large" prepend-icon="mdi-send-outline">
                Send Email
              </VBtn>
              <VAlert v-if="submitted" type="success" variant="tonal">
                Your email client should open with the message ready to send.
              </VAlert>
            </div>
          </form>
        </div>
      </VContainer>
    </section>
  </div>
</template>
