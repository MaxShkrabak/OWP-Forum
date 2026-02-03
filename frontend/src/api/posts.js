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

export async function getCategories() {
  const { data } = await client.get("/verify/categories");

  return (data.items || []).map((cat) => ({
    categoryId: Number(cat.CategoryID),
    name: cat.Name,
  }));
}

export async function fetchPosts({ categoryId = null, limit = 5, sort = 'latest', page = 1 } = {}) {
  const endpoint = categoryId 
    ? `/categories/${categoryId}/posts` 
    : "/posts";

  const { data } = await client.get(endpoint, {
    params: { limit, sort, page }
  });

  if (data.posts) {
    data.posts = data.posts.map(post => ({
      ...post,
      likeCount: post.likeCount ?? 0,
      commentCount: post.commentCount ?? 0,
      tags: post.tags || []
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
  const res = await client.get(`/get_post/${id}`);
  const data = res?.data;
  if (data?.ok) {
    return data;
  }
}