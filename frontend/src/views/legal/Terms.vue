<template>
  <div class="legal-container">
    <h1>Terms of Service</h1>
    <p class="muted">Last updated: February 27, 2026</p>

    <h2>Purpose</h2>
    <p>
      The Office of Water Programs (OWP) Forum is provided by California State University, Sacramento to support
      discussion related to OWP programs, training, certifications, and services.
    </p>

    <h2>Acceptable use</h2>
    <p>You agree not to:</p>
    <ul>
      <li>Harass, threaten, or target individuals or groups</li>
      <li>Post discriminatory, abusive, or unlawful content</li>
      <li>Share confidential or personal information without permission</li>
      <li>Upload copyrighted material without authorization</li>
      <li>Post spam, scams, malware, or attempt to abuse the system</li>
    </ul>

    <h2>Content and moderation</h2>
    <p>
      You are responsible for the content you submit. Administrators and moderators may remove content and restrict
      accounts that violate these Terms or applicable university policies.
    </p>

    <h2>Changes</h2>
    <p>
      These Terms may be updated. Continued use of the forum after updates constitutes acceptance of the updated Terms.
    </p>

    <h2>Contact</h2>
    <p>
      Questions? Contact the OWP Forum administrators.
    </p>

    <div class="back-container">
      <RouterLink class="back-btn" to="/">
        ← Back to accept
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.legal-container {
  max-width: 900px;
  margin: 40px auto;
  padding: 20px;
  line-height: 1.6;
}

.muted {
  color: #666;
}

h1 {
  margin-bottom: 6px;
}

h2 {
  margin-top: 22px;
  margin-bottom: 8px;
}

.back-container {
  margin-top: 40px;
  text-align: center;
}

.back-btn {
  display: inline-block;
  padding: 10px 18px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  background-color: #043927;
  color: white;
  transition: 0.2s ease;
}

.back-btn:hover {
  background-color: #032a1c;
}
</style>