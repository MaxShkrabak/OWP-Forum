import client from "./client";
import { timeAgo } from '@/utils/timeAgo';

export const formatCommentData = (comment) => ({
  ...comment,
  id: comment.commentId,
  author: `${comment.user.firstName} ${comment.user.lastName}`,
  role: comment.user?.role || 'user',
  time: timeAgo(comment.createdAt * 1000),
  text: comment.content,
  replyCount: comment.replyCount || 0,
  replies: []
});

export const fetchComments = async (postId, page = 1, limit = 10, sort = 'latest') => {
  try {
    const response = await client.get(
      `/posts/${postId}/comments?page=${page}&limit=${limit}&sort=${encodeURIComponent(sort)}`,
    );
    return response.data; 
  } catch (error) {
    console.error("Error fetching comments:", error.response?.data?.error || error.message);
    throw error;
  }
};

export const submitComment = async (
  postId,
  content,
  parentCommentId = null,
) => {
  try {
    const payload = { content };

    if (parentCommentId) {
      payload.parentCommentId = parentCommentId;
    }

    const response = await client.post(`/posts/${postId}/comments`, payload);

    return response.data;
  } catch (error) {
    console.error(
      "Error posting comment:",
      error.response?.data?.error || error.message,
    );
    throw error;
  }
};

export const updateComment = async (commentId, content) => {
  try {
    const response = await client.put(`/comments/${commentId}`, { content });
    return response.data;
  } catch (error) {
    console.error(
      "Error updating comment:",
      error.response?.data?.error || error.message,
    );
    throw error;
  }
};

export const voteComment = async (commentId, dir) => {
  try {
    const response = await client.post(`/comments/${commentId}/vote`, { dir });
    return response.data;
  } catch (error) {
    console.error(
      "Error voting:",
      error.response?.data?.error || error.message,
    );
    throw error;
  }
};

// TODO: Maybe add a limit to it same as comments
export const fetchCommentReplies = async (parentId) => {
  try {
    const response = await client.get(`/comments/${parentId}/replies`);
    return response.data; 
  } catch (error) {
    console.error("Error fetching replies:", error.response?.data?.error || error.message);
    throw error;
  }
};