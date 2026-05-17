<template>
  <footer class="site-footer">
    <VContainer>
      <div class="footer-grid">
        <div>
          <img
            class="footer-logo"
            src="https://apfpress.com/wp-content/uploads/2025/07/apf_logo_2-orig.png"
            alt="APF Press"
          >
          <p class="footer-copy">
            Academic publishing for critical inquiry, social justice, human rights, and public scholarship.
          </p>
        </div>

        <div class="footer-links">
          <h2>Browse</h2>
          <NuxtLink to="/shop-print-books/">Print Books</NuxtLink>
          <NuxtLink to="/shop-ebooks/">E-books</NuxtLink>
          <NuxtLink to="/editorial-board/">Editorial Board</NuxtLink>
        </div>

        <div class="footer-links">
          <h2>Contact</h2>
          <a href="tel:14168171266">416-817-1266</a>
          <a href="mailto:apf.press@rogers.com">apf.press@rogers.com</a>
          <a href="https://maps.app.goo.gl/DWWC1PgfELjNBKGB7">4 Carnegie Ct. Toronto, ON</a>
        </div>
      </div>

      <div class="footer-bottom">
        <span>Copyright {{ new Date().getFullYear() }} APF Press.</span>
        <span>Since 1990.</span>
      </div>
    </VContainer>
  </footer>
</template>
