<script setup>
import logo from '@/assets/SacState-whitelogo.png'
import instagram from "@/assets/instagram.png"
</script>

<template>
    <div class="footer">
        <div class="row">
            <div class="sacstate">
                <a href="https://www.csus.edu/">
                    <img :src="logo" alt="Sacramento State logo">
                </a>
            </div>
            <div class="divider"></div>
            <div class="footercol1" style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif;font-size: 14px;">
                <strong>Office of Water Programs</strong>
                <br>
                3020 State University Drive
                <br>
                Modoc Hall Suite 1001
                <br>
                Sacramento, CA 95819
                <br>
                Phone: (916) 278-6142
                <br>
                Fax: (916) 278-5959
                <br>
                Email: 
                <a href="mailto:wateroffice@owp.csus.edu">wateroffice@owp.csus.edu</a>
                <br>
                <a href="">
                    <img :src="instagram" height="16" width="16">
                </a>
                <p>© Office of Water Programs | 2025 All Rights Reserved.</p>
            </div>
            <div class="footercol2">
                <div class="footer-links">
                    <nav>
                        <ul class="nav-links">
                            <li><a href="#">About Us</a></li>
                            <li><a href="#">Contact Us</a></li>
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Return Policy</a></li>
                            <li><a href="#">Careers</a></li>
                            <li><a href="#">FAQ</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
            <div class="footercol3"></div>
        </div>
    </div>
</template>

<style>
.divider {
    width: .1%;
    background-color: white;
    margin-left: 2%;
    margin-right: 2%;
}
.footer  {
    background-color: black;
    position: relative;
    bottom: 0%;
    height: 200px;
    padding: 4em;
    font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif;
    font-size: 14px;

}
.sacstate {
    width:15%;
    img{
        width: 100%;
    }
}
.row {
    display: flex;
    justify-content: center;
}

.footercol1{
    color: white;
    line-height: 1.5;
    a {
        color: #008453;
        font-weight: 700;
    }
}
.footercol2 ul {
    list-style: none;
    line-height: 2.0;
    font-family: 'Lato', Arial, Helvetica, sans-serif;
    font-weight: 700;
    a {
        color: #a3bdb4;
        text-decoration: none;
    }
}
</style>