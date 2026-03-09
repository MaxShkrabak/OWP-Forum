import client from "./client";

export async function createPost(payload) {
   const { data } = await client.post("/create-post", payload);
  return data;
}

export async function getTags() {
  const { data } = await client.get("/tags");

  return (data.items || []).map((tag) => ({
    tagId: Number(tag.TagID),
    name: tag.Name,
  }));
}

export async function getFilterTags() {
  const { data } = await client.get("/tags/filter");

  return (data.items || []).map((tag) => ({
    tagId: Number(tag.TagID),
    name: tag.Name,
  }));
}

export async function getCategories() {
  const { data } = await client.get("/verify/categories");

  return (data.items || []).map((cat) => ({
    categoryId: Number(cat.CategoryID),
    name: cat.Name,
  }));
}

export async function fetchPosts({ categoryId = null, limit, sort = 'latest', page = 1, userId = null, tags = null } = {}) {
  let endpoint = "/posts";

  if(categoryId) {
    endpoint = `/categories/${categoryId}/posts`;
  } else if (userId) {
    endpoint = `/profile/${userId}/posts`;
  }
  const params = { limit, sort, page };

  if (Array.isArray(tags) && tags.length > 0) {
    params.q = tags.join(',');
    params.mode = 'tag';
  } else if (tags && typeof tags === 'string') {
    params.q = tags;
    params.mode = 'tag';
  }

  const { data } = await client.get(endpoint, { params });

  if (data.posts) {

    data.posts = data.posts.map((post) => ({
      ...post,
      postId: post.PostID,
      likeCount: post.TotalScore ?? 0,
      commentCount: post.commentCount ?? 0,
      tags: post.tags || [],
    }));
  }

  if (Array.isArray(data.postsByCategory)) {
    data.postsByCategory = data.postsByCategory.map((cat) => ({
      ...cat,
      posts: (cat.posts || []).map((post) => ({
        ...post,
        postId: post.postId ?? post.PostID,
        likeCount: post.likeCount ?? post.TotalScore ?? 0,
        commentCount: post.commentCount ?? 0,
        tags: post.tags || [],
      })),
    }));
  }

  return data;
}

export async function votePost(PostID, action) {
  if (!PostID) {
    return { ok: false, error: "Missing Post ID" };
  }
  const { data } = await client.post(`/posts/${PostID}/vote`, { 
    action: action 
  });

  return data;
}

export async function getPost(id) {
  const res = await client.get(`/get-post/${id}`);
  const data = res?.data;
  if (data?.ok) {
    return data.post;
  }
}

export async function fetchLikedPosts({ userId, limit, sort = "latest", page = 1 } = {}) {
  if (!userId) throw new Error("Missing userId");

  const { data } = await client.get(`/profile/${userId}/liked-posts`, {
    params: { limit, sort, page },
  });

  // Normalize like fetchPosts()
  if (data?.posts) {
    data.posts = data.posts.map((post) => ({
      ...post,
      postId: post.PostID,
      likeCount: post.TotalScore ?? 0,
      commentCount: post.commentCount ?? 0,
      tags: post.tags || [],
    }));
  }

  return data;
}