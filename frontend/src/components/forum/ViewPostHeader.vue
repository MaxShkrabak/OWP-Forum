<script setup>
import UserRole from "@/components/user/UserRole.vue";

/**
 * FILLER DATA ONLY
 * Styles intentionally reused from PostCard.vue
 */

// Filler data
const title = "Test2";
const tags = ["Discussion", "Information", "Research"];
const dateText = "9/15/2025";
const timeText = "6:12 PM";
const authorName = "Joe Joey";
const authorRole = "USER";

// Use the same avatar system as PostCard.vue
function getAvatarSrc(file) {
  return new URL(
    `../../assets/img/user-pfps-premade/${file}`,
    import.meta.url
  ).href;
}

// Filler avatar file (must exist in user-pfps-premade)
const avatarFile = "tree.png";
</script>

<template>
  <header class="top-header">
    <!-- Left: Title + tags -->
    <div class="left">
      <div class="post-title">{{ title }}</div>

      <div class="tags">
        <span v-for="t in tags" :key="t" class="post-tag">{{ t }}</span>
      </div>
    </div>

    <!-- Middle: Date / Time -->
    <div class="datetime">
      <div class="date">{{ dateText }}</div>
      <div class="time">{{ timeText }}</div>
    </div>

    <!-- Right: Author (same as PostCard) -->
    <div class="author-info-wrap">
      <div class="text-end d-flex flex-column align-items-end">
        <span class="author-name text-truncate">{{ authorName }}</span>
        <UserRole :role="authorRole" />
      </div>

      <div class="avatar-box shadow-sm">
        <img
          :src="getAvatarSrc(avatarFile)"
          class="avatar-img"
          alt="user"
        />
      </div>
    </div>
  </header>
</template>

<style scoped>
/* Header container */
.top-header {
  width: 100%;
  height: 100%;
  display: grid;
  grid-template-columns: 1fr 140px 240px;
  align-items: center;
  gap: 12px;
  padding: 8px 14px;
  background: #fff;
  border: 3px solid #000;
  border-radius: 6px;

  /* IMPORTANT: override parent .text-center */
  text-align: left;
}

/* Left block */
.left {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  text-align: left; /* force left alignment */
}

.post-title {
  font-size: 18px;
  font-weight: 800;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: left; /* force left alignment */
}

/* Tags */
.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

/* SAME AS PostCard.vue */
.post-tag {
  background: #2e6c44;
  color: white;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 4px;
  white-space: nowrap;
}

/* Date/time */
.datetime {
  text-align: right;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.1;
}

/* Author block (same as PostCard.vue) */
.author-info-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-direction: row;
  justify-self: end;
}

.author-name {
  font-weight: 700;
  font-size: 0.75rem;
  color: #1a1a1b;
  max-width: 140px;
}

.avatar-box {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  background: #eee;
  flex-shrink: 0;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Responsive */
@media (max-width: 820px) {
  .top-header {
    grid-template-columns: 1fr 120px 210px;
  }
}

@media (max-width: 620px) {
  .top-header {
    grid-template-columns: 1fr;
    height: auto;
    row-gap: 10px;
  }

  .datetime,
  .author-info-wrap {
    justify-self: start;
    text-align: left;
  }
}
</style>
