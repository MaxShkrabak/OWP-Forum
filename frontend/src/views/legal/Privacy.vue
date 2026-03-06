<template>
  <div class="legal-container">
    <h1>Privacy Notice</h1>
    <p class="muted">Last updated: February 27, 2026</p>

    <h2>What we collect</h2>
    <ul>
      <li>Account information (e.g., name, email, role)</li>
      <li>Forum activity (posts, comments, likes, reports)</li>
      <li>Uploaded files/images (if you upload them)</li>
      <li>Technical logs (IP address, timestamps) for security and troubleshooting</li>
    </ul>

    <h2>How we use it</h2>
    <ul>
      <li>Operate and secure the forum</li>
      <li>Provide moderation tools (reports, bans, audit logs)</li>
      <li>Improve reliability and performance</li>
      <li>Investigate misuse or abuse</li>
    </ul>

    <h2>Sharing</h2>
    <p>
      We do not sell personal information. Data may be shared with authorized CSU/Sac State personnel or approved
      service providers as required to operate the system, maintain security, or comply with applicable law/policy.
    </p>

    <h2>Retention</h2>
    <p>
      Data is retained as necessary for operational, administrative, and legal purposes. Some data may remain in backups
      for a limited time.
    </p>

    <h2>Contact</h2>
    <p>
      Questions about this notice? Contact the OWP Forum administrators.
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