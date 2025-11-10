<script setup>
import { isLoggedIn } from "@/api/auth";
import UserIcon from "@/assets/img/user-pfps-premade/pfp-0.png";
import { ref } from "vue";
import { fullName, userAvatar } from '@/stores/userStore';

const role = ref('User')
</script>

<template>
    <!--Signed in User Card-->
        <div
          class="user-cards border border-2 border-dark-subtle rounded-1 my-3"
          v-if="isLoggedIn"
        >
          <!--Puts Card into a row-->
          <div class="row p-3">
            <!--First part using 5 of the 12 Cols, for PFP and role under it-->
            <div class="col-5 avatar align-center">
              <!--PFP-->
              <h5 class="pfp">
                <img :src="userAvatar" alt="icon" class="img-fluid" />
              </h5>
              <!--Conditional role badges based on var 'role'-->
              <h5 v-if="role === 'User'">
                <span class="badge text-bg-success">User</span>
              </h5>
              <h5 v-else-if="role === 'Student'">
                <span class="badge text-bg-primary">Student</span>
              </h5>
              <h5 v-else-if="role === 'Moderator'">
                <span class="badge text-bg-warning">Moderator</span>
              </h5>
              <h5 v-else-if="role === 'Admin'">
                <span class="badge text-bg-danger">Admin</span>
              </h5>
            </div>

            <!--Second part of User-Card using the last 7 cols-->
            <div class="col-7 text-start align-center">
              <!--Name-->
              <div class="row fw-bold"><h5>{{ fullName }}</h5></div>
              <!--Activity report-->
              <div class="row text-nowrap">
                <span
                  >Posts: 222 <br />
                  Likes: 222 <br />
                  Comments: 222</span
                >
              </div>
            </div>
          </div>
        </div>
        <!--NOT Signed in User-Card-->
        <div
          class="user-cards border border-2 border-dark-subtle rounded-1 my-3"
          v-else
        >
          <div class="row p-3">
            <!--PFP and role part-->
            <div class="col-5 avatar align-center">
              <h5 class="pfp">
                <img :src="UserIcon" alt="icon" class="img-fluid" />
              </h5>
              <h5><span class="badge text-bg-success">Guest</span></h5>
            </div>
            <!--Welcome part-->
            <div class="col-7">
              <div class="row pt-2 text-start fw-bold">
                <h5>Welcome!</h5>
                <br />
                <span
                  >Here, you will find your activity report, once you
                  <RouterLink to="/login" class="sign-in-link"
                    >sign in</RouterLink
                  >!</span
                >
              </div>
            </div>
          </div>
        </div>
</template>

<style>
.avatar img {
  width: 130px;
  border-radius: 50%;
}

.sign-in-link {
  color: #087B0B;
  font-weight: 700;
  text-decoration: none;
}

.sign-in-link:hover {
  text-decoration: underline;
}
</style>
