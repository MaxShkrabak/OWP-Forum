import client from "./client";

export function normalizeReport(r) {
  return {
    ...r,
    reportId: r.reportId ?? r.ReportID ?? r.ReportId,
    postId: r.postId,
    commentId: r.commentId ?? r.CommentID ?? r.CommentId,
    reason: r.reason ?? r.Reason ?? "",
    createdAt: r.createdAt ?? r.CreatedAt ?? "",
    source: r.source ?? r.Source ?? "Post",
    reporterId:
      r.reporterId ?? r.reporter?.id ?? r.ReporterId ?? r.ReportUserID ?? null,
    reporterName:
      r.reporterName ?? r.reporter?.fullName ?? r.ReporterName ?? "",
    contentTitle: r.contentTitle ?? r.postTitle ?? r.ContentTitle ?? "",
    contentAuthorId: r.contentAuthorId ?? r.ContentAuthorId ?? null,
    contentAuthorName:
      r.contentAuthorName ?? r.postAuthor ?? r.ContentAuthorName ?? "",
  };
}

export async function fetchReports() {
  const { data } = await client.get("/reports");
  return data;
}

export async function resolveReport(reportId) {
  const { data } = await client.patch(`/reports/${reportId}/resolve`);
  return data;
}

export async function getReportTags() {
  const { data } = await client.get("/reports/tags");
  return data.tags || [];
}

export async function submitReport(reportData) {
  try {
    const { data } = await client.post("/reports", reportData);
    return data;
  } catch (error) {
    return {
      ok: false,
      error: error.response?.data?.error || "Failed to submit report",
    };
  }
}
