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

export async function fetchPosts({ categoryId = null, limit, sort = 'latest', page = 1, userId = null} = {}) {
  let endpoint = "/posts";

  if(categoryId) {
    endpoint = `/categories/${categoryId}/posts`;
  } else if (userId) {
     endpoint = `/profile/${userId}/posts`;
  }
  
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