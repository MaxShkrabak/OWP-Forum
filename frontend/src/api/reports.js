import client from "./client";

/**
 * @param {object} [opts]
 * @param {number} [opts.page] — When set with perPage (or either alone), server paginates and returns total.
 * @param {number} [opts.perPage]
 * @param {'newest'|'oldest'} [opts.sort]
 */
export async function fetchReports(opts = {}) {
  const params = {};
  if (opts.page != null) params.page = opts.page;
  if (opts.perPage != null) params.perPage = opts.perPage;
  if (opts.sort) params.sort = opts.sort;
  const { data } = await client.get("/reports", { params });
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
