<script setup>
import logo from '@/assets/img/png/sac-state-whitelogo.png'
import instagram from "@/assets/img/svg/Instagram-Glyph-White-Logo.wine.svg"
import efcSacState from "@/assets/img/png/efc-sac-state.png"
import facebook from "@/assets/img/svg/Facebook-f_Logo-White-Logo.wine.svg"
import linkedin from "@/assets/img/svg/linked-logo-wht.svg"

</script>

<template>
  <footer class="footer">
    <div class="footer-container">
      <!-- Left Section -->
      <div class="footer-left">
        <a href="https://www.csus.edu/">
          <img :src="logo" alt="Sacramento State logo" class="footer-logo" />
        </a>
      </div>

      <div class="footer-divider"></div>

      <!-- Middle Info Section -->
      <div class="footer-middle">
        <p><strong>Office of Water Programs</strong></p>
        <p>
          3020 State University Drive<br />
          Modoc Hall Suite 1001<br />
          Sacramento, CA 95819
        </p>
        <p>
          Phone: (916) 278-6142<br />
          Fax: (916) 278-5959<br />
          Email:
          <a href="mailto:wateroffice@owp.csus.edu">wateroffice@owp.csus.edu</a>
        </p>
        <a href="#" class="social-icon">
          <img :src="facebook" alt="Facebook"/>
          <img :src="linkedin" alt="LinkedIn"/>
          <img :src="instagram" alt="Instagram" />
        </a>
        <p class="copyright">
          © Office of Water Programs | 2025 All Rights Reserved.
        </p>
      </div>

      <!-- Middle Links Section -->
      <div class="footer-middle-links">
        <ul>
          <li><a href="#">About Us</a></li>
          <li><a href="#">Contact Us</a></li>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Return Policy</a></li>
          <li><a href="#">Careers</a></li>
          <li><a href="#">FAQ</a></li>
        </ul>
      </div>

      <!-- Right Section -->
      <div class="footer-right">
        <img :src="efcSacState" alt="EFC Logo" class="efc-logo" />
        <ul>
            <li><a href="#">College of Engineering & Computer Science</a></li>
            <li><a href="#">Department of Civil Engineering</a></li>
        </ul>
      </div>

    </div>
  </footer>
</template>

<style scoped>
.footer {
  background-color: #000;
  color: white;
  padding: 3em 6em;
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  font-size: 14px;
}

.footer-container {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 2em;
}

.footer-left {
  flex: 1;
  display: flex;
  justify-content: center;
}

.footer-logo {
  width: 100%;
  max-width: 200px;
}

.footer-divider {
  width: 1px;
  background-color: white;
  height: 180px;
}

.footer-middle {
  flex: 1;
  line-height: 1.6;
}

.footer-middle-links {
  flex: 1;
}

a {
  color: #008453;
  font-weight: 700;
  text-decoration: none;
}

.social-icon img {
  width: 24px;
  height: 24px;
  margin-top: 0.5em;
}

.footer-right {
  flex: 1;
}

ul {
  list-style: none;
  padding: 0;
  margin: 0;
  line-height: 2;
}

a {
  color: #a3bdb4;
  text-decoration: none;
  font-family: 'Lato', Arial, Helvetica, sans-serif;
  font-weight: 700;
}

a:hover {
  color: #ffffff;
  text-decoration: underline;
}

.copyright {
  margin-top: 1em;
  font-size: 13px;
  color: #d3d3d3;
}

/* Responsive adjustments */
@media (max-width: 900px) {
  .footer-container {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .footer-divider {
    display: none;
  }

  .footer-left,
  .footer-middle,
  .footer-right {
    flex: 1 1 100%;
  }

  .footer-right ul {
    padding-top: 1em;
  }
}
</style>
